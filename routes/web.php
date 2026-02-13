<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// All non-API routes serve the React SPA
Route::fallback(function () {
    return response()->file(public_path('index.html'));
});

require __DIR__.'/auth.php';
