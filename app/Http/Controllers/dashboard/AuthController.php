<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\SignUpRequest;
use App\Http\Requests\SignInRequest;
use App\Mail\Auth\EmailConfirmationMail;
use App\Mail\Auth\ResetPasswordMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    public function login(SignInRequest $request)
    {
        try{
            $validatedData = $request->validated();

            if (Auth::attempt($request->only('email', 'password'))) {
                $user = Auth::user();
               /* if (!$user->hasVerifiedEmail()) {
                    Auth::logout(); // Immediately log out the user
                    return response()->json(['email_verified' => false], 403); // Custom response
                }*/
                $request->session()->regenerate();

                return response()->json([
                    'message' => 'Login successful',
                    'role' => $user->role,
                ], 200);
            }
            return response()->json(['error' => __('auth.failed')], 401);
         }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => __('auth.Successfully logged out')]);
    }


    public function user()
    {
        return response()->json(Auth::user());
    }


    public function register(SignUpRequest $request)
    {
        try {

            $lang = request()->header('Accept-Language');  // Defaults to English if not provided
            app()->setLocale($lang);

            // Proceed with registration
            $validatedData = $request->validated();

            $user = new User;
            $user->name = request()->name;
            $user->prenom = request()->prenom;
            $user->tel =  str_replace(' ', '', request()->tel);
            //$user->whatsapp =  'https://wa.me/' . str_replace(' ', '', request()->tel);
            $user->email = request()->email;
            $user->role = 'client';
            $user->password = Hash::make(request()->password);
            $user->remember_token = Str::random(60);
            $user->email_verified_at = null;

            $lang = request()->header('Accept-Language');
            $user->save();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
                'message' => 'Please check your email for verification link.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function verifyEmail($token)
    {
        try {
            $frontendUrl = config('app.frontend_url');
            $user = User::where('remember_token', $token)->first();

            if (!$user) {
                $errorMessage = __('auth.Invalid token');
                return redirect("{$frontendUrl}/auth/verify-email?verified=false&errorMessage=" . urlencode($errorMessage));
            }

            if ($user->email_verified_at) {
                $errorMessage = __('auth.Email is already verified');
                return redirect("{$frontendUrl}/auth/verify-email?verified=false&errorMessage=" . urlencode($errorMessage));
            }

            if ($user->email_verification_expires_at < now()) {
                $errorMessage = __('auth.Verification link has expired');
                return redirect("{$frontendUrl}/auth/verify-email?verified=false&errorMessage=" . urlencode($errorMessage));
            }

            // Mark the email as verified
            $user->email_verified_at = now();
            $user->email_verification_expires_at = null; // Clear the expiration date
            $user->save();

            // Automatically log the user in using Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirect to the Angular app with the token and success flag
            return redirect("{$frontendUrl}/auth/verify-email?verified=true&token=" . urlencode($token));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    public function sendResetPasswordLink(Request $request)
    {
        try {
            // Validate email
            $request->validate(['email' => 'required|email']);

            $email = $request->email;

            // Check if the user exists
            $user = User::where('email', $email)->first();
            if (!$user) {
                return response()->json(['error' => __("auth.This Email doesn't exist")], Response::HTTP_NOT_FOUND);
            }

            // Check for existing token
            $existingToken = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->where('type', 'password')
                ->first();

            if ($existingToken && Carbon::parse($existingToken->expires_at)->isFuture()) {
                $token = $existingToken->token; // Reuse existing valid token
            } else {
                // Generate a new token
                $token = Str::random(60);

                // Save the token
                DB::table('password_reset_tokens')->updateOrInsert(
                    ['email' => $email, 'type' => 'password'],
                    [
                        'token' => $token,
                        'expires_at' => Carbon::now()->addMinutes(2880), // Token valid for 48 hours
                        'created_at' => Carbon::now()
                    ]
                );
            }

            $lang = request()->header('Accept-Language');
            // Send email with reset token
            Mail::to($email)->send(new ResetPasswordMail($token,$lang));

            return response()->json(['message' => __('auth.sent successfully')], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => __('auth.An error occurred, please try again later')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function resetPassword(ChangePasswordRequest $request)
    {
        try {
            // Validate the reset token
            $tokenRecord = PasswordResetToken::where('token', $request->resetToken)->first();

            if (!$tokenRecord) {
                return response()->json(['error' => __('auth.Token not found')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Verify if the token has expired
            if ($tokenRecord->expires_at < now()) {
                return response()->json(['error' => __('auth.Token has expired')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Find the user and update the password
            $user = User::where('email', $tokenRecord->email)->first();
            if (!$user) {
                return response()->json(['error' => __('auth.User not found')], Response::HTTP_NOT_FOUND);
            }

            // Hash and update the password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the token record after successful password change
            $tokenRecord->delete();

            return response()->json(['message' => __('auth.Password Successfully Changed')], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendVerificationEmail(Request $request)
    {
        try {
            // Find user by email
            $user = User::where('email', $request->email)->first();

            // Check if the user exists
            if (!$user) {
                return response()->json(['error' => __('auth.User not found.')], Response::HTTP_NOT_FOUND);
            }

            // Check if the user's email is already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json(['error' => __('auth.User already verified.')], Response::HTTP_BAD_REQUEST);
            }

            // Generate a new verification token
            $user->remember_token = Str::random(60); // Generate a random token
            $user->email_verification_expires_at = now()->addHours(48); // Set expiration to 48 hours
            $user->save();

            $lang = request()->header('Accept-Language');
            // Resend the verification email
            Mail::to($user->email)->send(new EmailConfirmationMail($user,$lang));

            return response()->json(['message' => __('auth.mail_sent_again')], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
