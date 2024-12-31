<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NairaTransfer extends Controller
{
    public function transfer(Request $request)
    {
        $response = [
            'status' => 'error',
            'message' => 'Unknown error',
            'requestData' => null,
            'headers' => null,
            'apiResponse' => null,
            'detailedError' => null, // For debugging detailed errors
        ];

        $requiredFields = ['token', 'accountNumbers', 'amount', 'bankCode', 'sessionId', 'description'];
        $data = $request->only($requiredFields);

        $missingFields = array_diff($requiredFields, array_keys($data));
        if (!empty($missingFields)) {
            $response['message'] = 'Missing fields: ' . implode(', ', $missingFields);
            return response()->json($response, 400);
        }

        try {
            $token = $data['token'];
            $user = DB::table('user_credentials')->where('token', $token)->first();

            if (!$user) {
                $response['message'] = 'Invalid token.';
                return response()->json($response, 401);
            }

            $userId = $user->user_id;

            $paymentReference = 'Tamopei_' . $userId . '_' . now()->format('YmdHis');

            $urlMain = "https://api.safehavenmfb.com/transfers";
            $amountInNaira = floatval(str_replace(',', '', $data['amount']));
            $dataMain = [
                "saveBeneficiary" => true,
                "beneficiaryBankCode" => $data['bankCode'],
                "beneficiaryAccountNumber" => $data['accountNumbers'],
                "amount" => $amountInNaira,
                "nameEnquiryReference" => $data['sessionId'],
                "debitAccountNumber" => "0113693092",
                "narration" => $data['description'],
                "paymentReference" => $paymentReference,
            ];

            $headers = [
                'ClientID' => '9e0763f989fd3090583662e05117fdca',
                'Authorization' => 'Bearer ' . app('AccessToken'),
                'Accept' => 'application/json',
            ];

            $response['requestData'] = $dataMain;
            $response['headers'] = $headers;

            $transferResponse = Http::withHeaders($headers)
                ->post($urlMain, $dataMain);

            $apiResponse = $transferResponse->json();
            $response['apiResponse'] = $apiResponse;

            // Check if transfer succeeded
            if ($transferResponse->successful() && $apiResponse['message'] === 'Approved or completed successfully') {
                // Deduct from wallet
                try {
                    $wallet = DB::table('wallet')->where('user_id', $userId)->first();

                    if (!$wallet) {
                        throw new \Exception('Wallet not found for this user.');
                    }

                    $newBalance = floatval($wallet->Naira) - $amountInNaira;

                    if ($newBalance < 0) {
                        throw new \Exception('Insufficient wallet balance.');
                    }

                    DB::table('wallet')->where('user_id', $userId)->update(['Naira' => $newBalance]);

                   try {
                        DB::table('micro_transfer')->insert([
                            'account' => $apiResponse['data']['account'],
                            'amount' => $apiResponse['data']['amount'],
                            'fee' => $apiResponse['data']['fees'] ?? 0, // Default fee to 0 if missing
                            'client' => $apiResponse['data']['client'],
                            'createdAt' => now()->toDateTimeString(), // Use current timestamp
                            'createdBy' => $userId,
                            'creditAccountName' => $apiResponse['data']['creditAccountName'],
                            'creditAccountNumber' => $apiResponse['data']['creditAccountNumber'],
                            'debitAccountName' => $apiResponse['data']['debitAccountName'],
                            'debitAccountNumber' => $apiResponse['data']['debitAccountNumber'],
                            'nameEnquiryReference' => $apiResponse['data']['nameEnquiryReference'],
                            'narration' => $apiResponse['data']['narration'],
                            'paymentReference' => $apiResponse['data']['paymentReference'],
                            'status' => $apiResponse['data']['status'],
                            'sessionId' => $apiResponse['data']['sessionId'],
                        ]);
                    } catch (\Exception $e) {
                        throw new \Exception('Failed to insert transfer data into Micro_transfer table: ' . $e->getMessage());
                    }


                    $response['status'] = 'success';
                    $response['message'] = 'Transfer successful and logged.';
                    $response['newWalletBalance'] = $newBalance;
                } catch (\Exception $e) {
                    $response['detailedError'] = $e->getMessage();
                    $response['message'] = 'Error during wallet deduction or data insertion.';
                }
            } else {
                $response['message'] = 'Transfer failed: ' . $apiResponse['message'];
            }
        } catch (\Exception $e) {
            Log::error('Transfer error: ' . $e->getMessage());
            $response['detailedError'] = $e->getMessage();
            $response['message'] = 'Error processing the transfer.';
        }

        $response['accessToken'] = app('AccessToken');

        return response()->json($response);
    }
}
