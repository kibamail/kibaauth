<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Client;

Route::get('/dashboard', function (Request $request) {
    $client = $request->session()->pull('client', []);

    if (array_key_exists('client_id', $client)) {
        return redirect(
              route('passport.authorizations.authorize', $client)
        );
    }

    if ($request->user() && $request->user()->isAdministrator()) {
        $clients = Client::all();
        return Inertia::render('Dashboard', [
            'clients' => $clients
        ]);
    }

    return abort(404);
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/passport.php';
require __DIR__.'/workspaces.php';
