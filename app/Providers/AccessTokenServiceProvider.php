<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AccessTokenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Function to get access token
        $this->app->singleton('AccessToken', function () {
            return $this->getAccessToken();
        });
    }

    /**
     * Function to get access token
     *
     * @return string
     */
    public function getAccessToken()
    {
        $url = 'https://api.safehavenmfb.com/oauth2/token';

        $headers = [
            'accept: application/json',
            'content-type: application/json',
        ];

        $data = [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOi8vZGFzaGJvYXJkLnRhbW9wZWkuY29tLm5nIiwic3ViIjoiOWUwNzYzZjk4OWZkMzA5MDU4MzY2MmUwNTExN2ZkY2EiLCJhdWQiOiJodHRwczovL2FwaS5zYWZlaGF2ZW5tZmIuY29tIiwiaWF0IjoxNzA2MjEwNjI5LCJleHAiOjE4MDAxMDU1NjV9.QNYPYZ8v6_rHxg5i5xTw8Kx2xBTGjapzryfkqSVswTFli_xbhmhZHOqZL9ri56Gr5VCv2X5sEcyE2JUBmszTCxYioGtIU-_OiYSCXon4ds5JiXsFEQKk61X3HfuCrF9_PSOxDm9HInyjFnQSMTVK6npadrSXM7icdCBj8kK4_R4',
            'client_id' => '9e0763f989fd3090583662e05117fdca',
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
            echo 'Curl error: ' . curl_error($ch);
        }

        // Close cURL session
        curl_close($ch);

        // Decode the JSON response
        $responseData = json_decode($response, true);

        // Return the access token
        return $responseData['access_token'];
    }
}

