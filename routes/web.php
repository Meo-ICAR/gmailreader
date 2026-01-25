<?php

use App\Http\Controllers\GmailController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/gmail/login', [GmailController::class, 'login'])->name('gmail.login');
Route::get('/oauth/gmail/callback', [GmailController::class, 'callback']);
Route::get('/gmail/privacy-list', [GmailController::class, 'list'])->name('gmail.list');
Route::get('/gmail/debug-labels', [GmailController::class, 'debugLabels']);
Route::get('/dev-login', function () {
    // Cerchiamo l'utente creato con il seeder
    $user = User::where('email', 'hassistosrl@gmail.com')->first();

    if (!$user) {
        return "Errore: l'utente  non esiste. Hai lanciato il seeder?";
    }

    Auth::login($user);
    return "Sei loggato come Admin! <br><a href='/gmail/login'>Ora clicca qui per collegare Google Gmail</a>";
});
