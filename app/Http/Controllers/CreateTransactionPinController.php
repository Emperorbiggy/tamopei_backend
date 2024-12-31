<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CreateTransactionPinController extends Controller
{
    public function createTransactionPin(Request $request)
    {
        // Initialize response data
        $response = ['success' => false, 'message' => ''];

        // Get input data
        $data = $request->json()->all();

        Log::debug("Received create transaction pin request", $data);  // Log incoming data

        // Validate input
        $token = $data['token'] ?? '';
        $pin = $data['pin'] ?? '';

        if (empty($token) || empty($pin)) {
            $response['message'] = 'Token and pin are required';
            return response()->json($response);
        }

        try {
            // Update the pin in the database using the token
            $updatedRows = DB::table('user_credentials')
                ->where('token', $token)
                ->update(['pin' => $pin]);

            if ($updatedRows > 0) {
                $response['success'] = true;
                $response['message'] = 'Pin successfully created';
            } else {
                $response['message'] = 'Invalid token or no changes made';
            }
        } catch (Exception $e) {
            // Log error and return a message
            Log::error("Error creating transaction pin: " . $e->getMessage());
            $response['message'] = 'Database error';
        }

        return response()->json($response);
    }
}
