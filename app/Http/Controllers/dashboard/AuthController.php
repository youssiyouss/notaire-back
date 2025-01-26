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
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{

    public function login(SignInRequest $request)
    {
        try{
        $credentials = $request->only('email', 'password');

        // Check if the email exists in the database
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {
            return response()->json(['error' => "Cette adresse mail n'existe pas."], 404);
        }

        // Attempt to authenticate with provided credentials
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Le mot de passe fourni est incorrect.'], 401);
        }

        // Successful login
        return response()->json([
            'message' => 'Login successful.',
            'role' => $user->role,
            'token' => $token,
        ]);

        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    public function user()
    {
        return response()->json(Auth::user());
    }


    public function register(SignUpRequest $request)
    {
        try {

            // Proceed with registration
            $validatedData = $request->validated();

           // Create the user with basic information
            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'tel' => str_replace(' ', '', $request->tel),
                'password' => Hash::make($request->password),
                'role' => 'client',  // Default role for client
            ]);
            // Create the client profile without additional info at first
            Client::create([
                'user_id' => $user->id,
                // other client-specific fields can be added later during profile completion
            ]);

           $token = auth()->login($user);

            return response()->json([
                'message' => 'Please check your email for verification link',
                'user' => $user,
                'token' => $token,
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
