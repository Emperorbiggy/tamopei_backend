<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Crypt;

class BvnEncryptionServiceProvider extends ServiceProvider
{
    /**
     * Register the application's services.
     *
     * @return void
     */
    public function register()
    {
        // Register the encryption methods as a singleton
        $this->app->singleton('BvnEncryption', function ($app) {
            return new class {
                /**
                 * Encrypt the BVN.
                 *
                 * @param string $bvn
                 * @return string
                 */
                public function encryptBVN($bvn)
                {
                    $cipher = "aes-256-cbc";
                    $key = config('app.key'); // Encryption key from the .env or config
                    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
                    $encryptedBvn = openssl_encrypt($bvn, $cipher, $key, 0, $iv);
                    return base64_encode($encryptedBvn . '::' . $iv);
                }

                /**
                 * Decrypt the BVN.
                 *
                 * @param string $encryptedBvn
                 * @return string
                 */
                public function decryptBVN($encryptedBvn)
                {
                    $cipher = "aes-256-cbc";
                    list($encryptedData, $iv) = explode('::', base64_decode($encryptedBvn), 2);
                    $key = config('app.key'); // Decryption key from the .env or config
                    return openssl_decrypt($encryptedData, $cipher, $key, 0, $iv);
                }
            };
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

