<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyUserController extends Controller
{
    /**
     * Verify user based on the provided token and user information.
     */
    public function verifyUser(Request $request)
    {
        // Log the incoming request data for debugging purposes
        Log::info('Incoming request to verify user', $request->all());

        // Validate the incoming request
        $request->validate([
            'userInfo' => 'required|string',  // Ensure userInfo is a string
            'token' => 'required|string',     // Ensure token is a string
        ]);

        // Retrieve userInfo and token from the request body
        $token = $request->input('token');  // Get token from the JSON body
        $userInfo = $request->input('userInfo');  // Get userInfo from the JSON body

        try {
            // Log token and user info for debugging purposes
            Log::info("Token: $token, UserInfo: $userInfo");

            // Retrieve user associated with the token
            $user = DB::table('user_credentials')->where('token', $token)->first(['user_id']);

            if (!$user) {
                // Log invalid token attempt
                Log::warning('Invalid token received', ['token' => $token]);

                // Log the response before returning
                $response = response()->json(['status' => 'error', 'message' => 'Invalid token or user not found'], 404);
                Log::info('Response: ' . $response->getContent());

                return $response;
            }

            $user_id = $user->user_id;

            // Check if the user is trying to transfer to themselves
            if (is_numeric($userInfo) && $userInfo == $user_id) {
                $response = response()->json([
                    'status' => 'error',
                    'message' => 'You cannot initiate a transfer to yourself'
                ], 400);

                // Log the response
                Log::info('Response: ' . $response->getContent());
                return $response;
            }

            // If userInfo starts with @, treat it as a username
            if (strpos($userInfo, '@') === 0) {
                // Remove the @ symbol and treat it as a username
                $username = ltrim($userInfo, '@');
                $targetUser = DB::table('user_credentials')
                    ->where('username', $username)
                    ->first(['user_id', 'first_name', 'middle_name', 'last_name']);

                if ($targetUser) {
                    // Check if the target user is the same as the requester
                    if ($targetUser->user_id == $user_id) {
                        $response = response()->json([
                            'status' => 'error',
                            'message' => 'You cannot initiate a transfer to yourself'
                        ], 400);

                        // Log the response
                        Log::info('Response: ' . $response->getContent());
                        return $response;
                    }

                    // Return the full name of the target user
                    $fullName = trim($targetUser->first_name . ' ' . $targetUser->middle_name . ' ' . $targetUser->last_name);
                    $response = response()->json([
                        'status' => 'success',
                        'fullName' => $fullName
                    ]);

                    // Log the response
                    Log::info('Response: ' . $response->getContent());
                    return $response;
                } else {
                    $response = response()->json([
                        'status' => 'error',
                        'message' => 'User not found'
                    ], 404);

                    // Log the response
                    Log::info('Response: ' . $response->getContent());
                    return $response;
                }
            }

            // If userInfo is numeric, treat it as a user_id
            if (is_numeric($userInfo)) {
                $targetUser = DB::table('user_credentials')
                    ->where('user_id', $userInfo)
                    ->first(['first_name', 'middle_name', 'last_name']);

                if ($targetUser) {
                    $fullName = trim($targetUser->first_name . ' ' . $targetUser->middle_name . ' ' . $targetUser->last_name);
                    $response = response()->json([
                        'status' => 'success',
                        'fullName' => $fullName
                    ]);

                    // Log the response
                    Log::info('Response: ' . $response->getContent());
                    return $response;
                } else {
                    $response = response()->json([
                        'status' => 'error',
                        'message' => 'User not found'
                    ], 404);

                    // Log the response
                    Log::info('Response: ' . $response->getContent());
                    return $response;
                }
            }

            // Handle the case where the userInfo is neither a valid username nor user_id
            $response = response()->json([
                'status' => 'error',
                'message' => 'Invalid user information'
            ], 400);

            // Log the response
            Log::info('Response: ' . $response->getContent());
            return $response;

        } catch (\Exception $e) {
            // Log the error and return a generic error message
            Log::error("Error verifying user: " . $e->getMessage(), ['exception' => $e]);

            $response = response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred'
            ], 500);

            // Log the response
            Log::info('Response: ' . $response->getContent());
            return $response;
        }
    }
}
