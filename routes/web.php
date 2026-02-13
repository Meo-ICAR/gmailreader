<?php

use App\Http\Controllers\GmailController;
use App\Models\User;
use App\Services\GoogleGmailService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/gmail/login', [GmailController::class, 'login'])->name('gmail.login');
Route::get('/oauth/gmail/callback', [GmailController::class, 'callback']);
Route::get('/gmail/privacy-list', [GmailController::class, 'list'])->name('gmail.list');
Route::get('/gmail/debug-labels', [GmailController::class, 'debugLabels']);
Route::get('/gmail/import-db', [GmailController::class, 'importToDb']);

// Rotte per gestione allegati
Route::get('/gmail/attachments', [GmailController::class, 'listAttachments'])->name('gmail.attachments');
Route::get('/gmail/attachments/{id}/download', [GmailController::class, 'downloadAttachment'])->name('gmail.attachment.download');
Route::get('/gmail/attachments/message/{messageId}/download-all', [GmailController::class, 'downloadMessageAttachments'])->name('gmail.message.attachments.download');
Route::get('/gmail/attachments/stats', [GmailController::class, 'attachmentStats'])->name('gmail.attachments.stats');

// AGGIUNGI QUESTA ROTTA:
Route::get('/gmail/start-batch', function (GoogleGmailService $service) {
    // Verifica login per sicurezza
    if (!Auth::check()) {
        return "Devi essere loggato per lanciare il batch! <a href='/dev-login'>Login</a>";
    }

    $result = $service->dispatchBatchImport();

    if ($result) {
        return "<h3>Batch avviato con successo!</h3><p>I Job sono stati inviati alla coda. Assicurati di avere 'php artisan queue:work' attivo nel terminale.</p><a href='/admin/email-interactions'>Vai a Filament</a>";
    } else {
        return "Errore nell'inizializzazione del Client Google. Token scaduto o mancante.";
    }
});
Route::get('/dev-login', function () {
    // Cerchiamo l'utente creato con il seeder
    $user = User::where('email', 'hassistosrl@gmail.com')->first();

    if (!$user) {
        return "Errore: l'utente  non esiste. Hai lanciato il seeder?";
    }

    Auth::login($user);
    return "Sei loggato come Admin! <br><a href='/gmail/login'>Ora clicca qui per collegare Google Gmail</a>";
});
