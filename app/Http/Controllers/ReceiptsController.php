<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptsController extends Controller
{
    public function getReceipt(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'transactionId' => 'required|string',
            'transactionType' => 'required|string|in:Tamopei_Transfer,Bank_Transfer,Momo_Transfer',
        ]);

        $transactionId = $request->input('transactionId');
        $transactionType = $request->input('transactionType');
        
        // Log the received data (for debugging purposes)
        Log::info("Received transactionId: $transactionId");
        Log::info("Received transactionType: $transactionType");

        // Determine the table and column based on the transaction type
        switch ($transactionType) {
            case 'Tamopei_Transfer':
                $tableName = 'wallet_transaction';
                $columnName = 'transaction_id';
                break;

            case 'Bank_Transfer':
                $tableName = 'Micro_transfer';
                $columnName = 'paymentReference';
                break;

            case 'Momo_Transfer':
                $tableName = 'momo_transaction';
                $columnName = 'transaction_id';
                break;

            default:
                // Invalid transaction type
                Log::error("Invalid transaction type: $transactionType");
                return response()->json(['status' => 'error', 'message' => "Invalid transaction type: $transactionType"], 400);
        }

        try {
            // Query the database for the transaction
            $result = DB::table($tableName)->where($columnName, $transactionId)->first();

            if ($result) {
                // If it's a Tamopei_Transfer, fetch additional user details
                if ($transactionType === 'Tamopei_Transfer') {
                    $receiver = DB::table('user_credentials')->where('user_id', $result->receiver_id)->first();
                    $sender = DB::table('user_credentials')->where('user_id', $result->sender_id)->first();

                    $result->receiver_name = $receiver ? trim($receiver->first_name . ' ' . $receiver->middle_name . ' ' . $receiver->last_name) : 'Unknown';
                    $result->sender_name = $sender ? trim($sender->first_name . ' ' . $sender->middle_name . ' ' . $sender->last_name) : 'Unknown';
                }

                // Log the result for receipt
                Log::info("Transaction details: " . print_r($result, true));

                // Return the transaction data as JSON
                return response()->json($result);

            } else {
                // If no receipt is found
                $errorMessage = "Receipt not found for Transaction ID: $transactionId, Type: $transactionType";
                Log::error($errorMessage);
                return response()->json(['status' => 'error', 'message' => $errorMessage], 404);
            }

        } catch (\Exception $e) {
            // Log any exceptions and return error message
            Log::error("Exception: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
