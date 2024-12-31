<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreatePasscodeController extends Controller
{
    public function createPasscode(Request $request)
    {
        // Initialize response data
        $response = ['success' => false, 'message' => ''];

        // Get input data
        $data = $request->json()->all();

        Log::debug("Received create passcode request", $data);  // Log incoming data

        // Validate input
        $token = $data['token'] ?? '';
        $passcode = $data['passcode'] ?? '';

        if (empty($token) || empty($passcode)) {
            $response['message'] = 'Token and passcode are required';
            return response()->json($response);
        }

        try {
            // Update the passcode in the database using the token
            $updatedRows = DB::table('user_credentials')
                ->where('token', $token)
                ->update(['passcode' => $passcode]);

            if ($updatedRows > 0) {
                $response['success'] = true;
                $response['message'] = 'Passcode successfully created';
            } else {
                $response['message'] = 'Invalid token or no changes made';
            }
        } catch (\Exception $e) {
            // Log error and return a message
            Log::error("Error creating passcode: " . $e->getMessage());
            $response['message'] = 'Database error';
        }

        return response()->json($response);
    }
}
