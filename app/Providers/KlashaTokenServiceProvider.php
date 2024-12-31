<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class KlashaTokenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Function to get Klasha token
        $this->app->singleton('KlashaToken', function () {
            return $this->getKlashaToken();
        });
    }

    /**
     * Function to get Klasha token
     *
     * @return string|null
     */
    public function getKlashaToken()
    {
        $url = 'https://gate.klasapps.com/auth/account/v2/login';

        $headers = [
            'Content-Type: application/json',
            'X-Forwarded-For: 91.134.244.67',
        ];

        $data = [
            'username' => 'tamopei6@gmail.com', // Replace with your actual username
            'password' => 'Ade;12345', // Replace with your actual password
        ];

        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute cURL session
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            curl_close($ch);
            return null; // Return null if there was a cURL error
        }

        // Close cURL session
        curl_close($ch);

        // Decode the JSON response
        $responseData = json_decode($response, true);

        // Extract token
        $token = $responseData['data']['token'] ?? null;

        // Clean the token if it exists
        if ($token) {
            // Remove any extra quotes or unwanted characters from the token
            $cleanToken = trim($token, '"');

            // Ensure the token doesn't contain surrounding quotes
            return $cleanToken;
        }

        // Return null if no token found
        return null;
    }
}
