<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationSuccessEmail;
use App\Mail\VerificationCodeEmail; // Import the verification code email class

class OtpVerificationController extends Controller
{
    public function verifyOtp(Request $request)
    {
        $response = ['success' => false, 'message' => ''];

        // Get input data
        $logData = $request->json()->all();
        
        Log::debug("Received OTP verification request", $logData);  // Log incoming data

        if (isset($logData['token'], $logData['verification_code'])) {
            $token = $logData['token'];
            $verificationCode = $logData['verification_code'];

            Log::debug("Token and verification code provided", compact('token', 'verificationCode'));  // Log token and verification code

            try {
                // Query for user with the provided token
                $user = DB::table('user_credentials')->where('token', $token)->first();

                if ($user) {
                    // Check if verification code matches
                    if ($verificationCode === $user->verification_code) {
                        // Update verification status
                        DB::table('user_credentials')->where('token', $token)->update(['verification_status' => 1]);

                        Log::info("Verification successful for user with token: {$token}");  // Log successful verification

                        // Send success email with dynamic username
                        $this->sendVerificationSuccessEmail($user->email, $user->username);

                        // Set success response
                        $response['success'] = true;
                        $response['message'] = "Verification successful!";
                    } else {
                        $response['message'] = "Invalid verification code.";
                        Log::warning("Invalid verification code provided for token: {$token}");  // Log invalid code
                    }
                } else {
                    $response['message'] = "Invalid token.";
                    Log::warning("Invalid token: {$token}");  // Log invalid token
                }
            } catch (\Exception $e) {
                // Log the exception message
                Log::error("Error during OTP verification process: " . $e->getMessage());
                $response['message'] = "An error occurred. Please try again later.";
            }
        } else {
            $response['message'] = "Token or verification code not set.";
            Log::warning("Token or verification code missing in the request data");  // Log missing data
        }

        return response()->json($response);
    }

    // Send Verification Success Email
    private function sendVerificationSuccessEmail($email, $username)
    {
        try {
            // Send the verification success email with dynamic username (using token to get username)
            Mail::to($email)->send(new VerificationSuccessEmail($username));
            Log::info("Verification success email sent to {$email}");  // Log successful email sending
        } catch (\Exception $e) {
            Log::error("Failed to send verification email to {$email}: " . $e->getMessage());  // Log email send failure
        }
    }
}
