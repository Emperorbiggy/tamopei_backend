<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dynamic route to serve CSS files from the public/css/ directory
Route::get('/css/{filename}', function ($filename) {
    // Get the full path to the CSS file in the public/css/ directory
    $path = public_path('css/' . $filename);

    // Check if the file exists and return it
    if (file_exists($path)) {
        return response()->file($path);
    } else {
        // If file does not exist, return a 404 error
        abort(404);
    }
});
Route::get('/test-notification', function () {
    broadcast(new \App\Events\NewNotification('This is a test notification!'));
    return 'Notification sent!';
});

