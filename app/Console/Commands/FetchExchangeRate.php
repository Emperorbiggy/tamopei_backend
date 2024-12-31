<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class FetchExchangeRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-exchange-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch the exchange rate and store it in the database';

    // Secret key for API request
    protected $secretKey;

    public function __construct()
    {
        parent::__construct();
        // Fetch the secret key from config
        $this->secretKey = Config::get('app.map_secret_key');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Define the currency pairs you want to fetch
        $currencyPairs = [
            ['paycurrency' => 'NGN', 'currency' => 'KES'],
            ['paycurrency' => 'NGN', 'currency' => 'USD'],
            ['paycurrency' => 'NGN', 'currency' => 'GHS'],
            ['paycurrency' => 'NGN', 'currency' => 'XAF'],
            ['paycurrency' => 'GHS', 'currency' => 'USD'],
            ['paycurrency' => 'XAF', 'currency' => 'USD'],
            ['paycurrency' => 'XAF', 'currency' => 'GHS'],
            ['paycurrency' => 'XAF', 'currency' => 'KES'],
            ['paycurrency' => 'KES', 'currency' => 'USD'],
            ['paycurrency' => 'KES', 'currency' => 'GHS'],
            
            // Add more pairs here
        ];

        // Loop through each pair and fetch the exchange rates
        foreach ($currencyPairs as $pair) {
            $paycurrency = $pair['paycurrency'];
            $currency = $pair['currency'];
            $amount = 1000; // Example: Amount to convert (no longer used in insert)

            // Output some details about the API request for debugging
            $this->info("Requesting exchange rate for {$paycurrency} to {$currency} with amount: {$amount}");

            try {
                // Make the HTTP POST request to fetch exchange rate
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post('https://sandbox.api.maplerad.com/v1/fx/quote', [
                    'source_currency' => $paycurrency,
                    'target_currency' => $currency,
                    'amount' => $amount,
                ]);

                // Check if the response is successful
                if ($response->successful()) {
                    $data = $response->json('data');

                    // Output the response data for debugging
                    $this->info('API Response: ' . json_encode($data));

                    // Extract the exchange rate and calculate the inverse rate
                    $rate = $data['rate'] ?? null;
                    $inverseRate = $rate ? 1 / $rate : null;

                    // Extract only the currency (remove amount from source and target)
                    $sourceCurrency = $data['source']['currency'] ?? null;
                    $targetCurrency = $data['target']['currency'] ?? null;

                    // Check if the exchange rate already exists for this source/target pair
                    $existingRate = DB::table('rates')
                        ->where('source', $sourceCurrency)
                        ->where('target', $targetCurrency)
                        ->first();

                    // If the exchange rate exists, update it, otherwise insert a new record
                    if ($existingRate) {
                        // Update the existing rate
                        DB::table('rates')
                            ->where('source', $sourceCurrency)
                            ->where('target', $targetCurrency)
                            ->update([
                                'rate' => $rate,
                                'inverse_rate' => $inverseRate,
                                'updated_at' => now(),
                            ]);
                        $this->info("Exchange rate updated for {$sourceCurrency} to {$targetCurrency}.");
                    } else {
                        // Insert the new rate
                        DB::table('rates')->insert([
                            'reference' => $data['reference'],
                            'source' => $sourceCurrency,
                            'target' => $targetCurrency,
                            'rate' => $rate,
                            'inverse_rate' => $inverseRate,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->info("Exchange rate fetched and stored successfully for {$sourceCurrency} to {$targetCurrency}.");
                    }
                } else {
                    // Output error message if the request fails
                    $this->error("Failed to fetch the exchange rate for {$paycurrency} to {$currency}. HTTP Status: " . $response->status());
                    $this->info('Response: ' . $response->body());
                }
            } catch (\Exception $e) {
                // Handle any exceptions and output the error
                $this->error("An error occurred while fetching the exchange rate for {$paycurrency} to {$currency}: " . $e->getMessage());
            }
        }
    }
}
