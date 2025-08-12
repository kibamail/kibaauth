<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::get('/dashboard', function (Request $request) {
    $client = $request->session()->pull('client', []);

    if (array_key_exists('client_id', $client)) {
        return redirect(
              route('passport.authorizations.authorize', $client)
        );
    }

    Log::info('Dashboard accessed', $client);

    return abort(404);
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/passport.php';
