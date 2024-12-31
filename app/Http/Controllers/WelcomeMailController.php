<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class WelcomeMailController extends Controller
{
    public function sendWelcomeEmail(Request $request)
    {
        // Initialize response
        $response = ['success' => false, 'message' => ''];

        // Log the incoming request for debugging purposes
        Log::info('Incoming request to send welcome email', $request->all());

        // Validate the incoming request for the token
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            $response['message'] = 'Token is required';
            Log::error('Validation failed', $validator->errors()->toArray());
            return response()->json($response, 400); // Return a 400 status code for validation error
        }

        $token = $request->input('token');

        try {
            // Query the user_credentials table to find a user with the provided token
            $user = DB::table('user_credentials')->where('token', $token)->first();

            if (!$user) {
                $response['message'] = 'Token not found';
                Log::warning("Token not found for the token: $token");
                return response()->json($response, 404); // Return a 404 status code if user not found
            }

            // Send welcome email using Laravel's Mail facade
            $emailSent = $this->sendVerificationEmail($user->email, $user->username);

            if ($emailSent) {
                $response['success'] = true;
                $response['message'] = 'Welcome email sent successfully';
            } else {
                $response['message'] = 'Failed to send welcome email';
                Log::error("Failed to send welcome email to: {$user->email}");
            }

        } catch (Exception $e) {
            // Log the exception and return an error message
            Log::error("Error sending welcome email: " . $e->getMessage(), ['exception' => $e]);
            $response['message'] = 'An unexpected error occurred';
        }

        return response()->json($response);
    }

    private function sendVerificationEmail($email, $username)
    {
        try {
            // Send email using Laravel's Mail facade, pointing to the custom mail folder
            Mail::send('emails.welcome', ['username' => $username], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Welcome to Our App!');
            });

            return true;
        } catch (Exception $e) {
            // Log the exception with a more detailed error message
            Log::error("Email could not be sent. Error: " . $e->getMessage(), [
                'email' => $email,
                'username' => $username,
                'exception' => $e
            ]);

            return false;
        }
    }
}
