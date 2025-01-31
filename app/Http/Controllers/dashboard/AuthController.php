<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePassword;
use App\Http\Requests\SignUpRequest;
use App\Http\Requests\SignInRequest;
use App\Mail\Auth\VerifyAccountMail;
use App\Mail\Auth\ResetPasswordMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Models\EmailVerification;
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
            $rememberMe = $request->input('rememberMe', false); // Get rememberMe value from request

            // Check if the email exists in the database
            $user = User::where('email', $credentials['email'])->first();
            if (!$user) {
                return response()->json(['error' =>  __("authController.email_not_found")], 404);
            }

            // Attempt to authenticate with provided credentials
            if (!$token = Auth::guard('api')->attempt($credentials)) {
                return response()->json(['error' => __("authController.incorrect_password")], 401);
            }


            if (!$user->hasVerifiedEmail()) {
                Auth::logout(); // Immediately log out the user
                return response()->json(['email_verified' => false], 403); // Custom response
            }


            // If "Remember Me" is checked, generate a persistent token
            if ($rememberMe) {
                $rememberToken = Str::random(60); // Generate a secure token
                $user->remember_token = $rememberToken;
                $user->save();
            }

            return response()->json([
                'message' => 'Login successful.',
                'role' => $user->role,
                'token' => $token,
                'remember_token' => $rememberMe ? $rememberToken : null, // Send remember token if checked
            ]);

        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }



    public function autoLogin(Request $request)
    {
        $user = User::where('remember_token', $request->remember_token)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid remember token'], 401);
        }

        // Generate new JWT token
        $token = Auth::guard('api')->login($user);

        return response()->json([
            'message' => 'Auto-login successful.',
            'role' => $user->role,
            'token' => $token
        ]);
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

            // Generate a verification token
            $verificationToken = Str::random(64);

            // Store the token in email_verifications table
             EmailVerification::Create([
                'user_id' => $user->id,
                'email' => $user->email,
                'token' => $verificationToken,
                'expires_at' => Carbon::now()->addHours(24)
               ] );


            // Send verification email
            Mail::to($user->email)->send(new \App\Mail\VerifyAccountMail($verificationToken));

            return response()->json([
                'message' => __("authController.employee_registered"),
                'user' => $user,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QueryException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.Database error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.An unexpected error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return response()->json(['error' => __("authController.email_not_found")], Response::HTTP_NOT_FOUND);
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
                        'expires_at' => Carbon::now()->addMinutes(1440), // Token valid for 24 hours
                        'created_at' => Carbon::now()
                    ]
                );
            }


            // Send email with reset token
            Mail::to($email)->send(new \App\Mail\PasswordResetMail($token));

            return response()->json(['message' => __('authController.psw_reset_sent')], Response::HTTP_OK);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QueryException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.Database error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.An unexpected error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function resetPassword(ChangePassword $request)
    {
        try {
            // Validate the reset token
            $tokenRecord = PasswordResetToken::where('token', $request->token)->first();

            if (!$tokenRecord) {
                return response()->json(['error' => __("authController.token_missing")], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Verify if the token has expired
            if ($tokenRecord->expires_at < now()) {
                $tokenRecord->delete();
                return response()->json(['error' => __('authController.token_expried')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Find the user and update the password
            $user = User::where('email', $tokenRecord->email)->first();
            if (!$user) {
                return response()->json(['error' => __("authController.email_not_found")], Response::HTTP_NOT_FOUND);
            }

            // Hash and update the password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the token record after successful password change
            $tokenRecord->delete();

            return response()->json(['message' => __('authController.password_updated')], Response::HTTP_OK);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QueryException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.Database error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.An unexpected error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyEmail($token)
    {
        try {
            $frontendUrl = config('app.frontend_url');
            $verification = EmailVerification::where('token', $token)->first();

            if(!$verification){
                $errorMessage = __('authController.email_already_verified');
                return redirect("{$frontendUrl}/auth/login?verified=false&errorMessage=" . urlencode($errorMessage));

            }else{
                if ($verification->expires_at < Carbon::now()) {
                    $errorMessage = __('authController.expired_link');
                    return redirect("{$frontendUrl}/auth/login?verified=false&errorMessage=" . urlencode($errorMessage));
                }

                $user = User::find($verification->user_id);
                $user->email_verified_at = Carbon::now();
                $user->save();

                // Optionally, delete the verification record after use
                $verification->delete();
                return redirect("{$frontendUrl}/auth/login?verified=true");
            }


        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QueryException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.Database error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.An unexpected error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendVerificationEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $request->email)->first();

            // Check if the user exists
            if (!$user) {
                return response()->json(['error' => __('authController.user_not_found')], Response::HTTP_NOT_FOUND);
            }

            // Check if the user's email is already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json(['error' => __('authController.user_verified')], Response::HTTP_BAD_REQUEST);
            }

             // Check if the verification request has been made within 24 hours
            $verification = EmailVerification::where('user_id', $user->id)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            // If no valid verification token found or expired, create a new token
            if (!$verification) {
                $token = Str::random(64);
                $expiresAt = Carbon::now()->addHours(24);

                EmailVerification::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'email' => $user->email,
                        'token' => $token,
                        'expires_at' => $expiresAt
                    ]
                );
            } else {
                // If a valid token exists within 24 hours, skip token creation
                $token = $verification->token;
            }

            // Resend the verification email
            Mail::to($user->email)->send(new \App\Mail\VerifyAccountMail($token));

            return response()->json(['message' => __('authController.mail_sent_again')], Response::HTTP_OK);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QueryException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.Database error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.An unexpected error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
