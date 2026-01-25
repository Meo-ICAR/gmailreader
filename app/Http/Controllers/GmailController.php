<?php

namespace App\Http\Controllers;

use App\Models\Dipendente;
use App\Models\Referente;
use App\Services\GoogleGmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;  // Importante per manipolare le stringhe

class GmailController extends Controller
{
    protected $gmailService;

    public function __construct(GoogleGmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    public function login()
    {
        return redirect()->away($this->gmailService->getAuthUrl());
    }

    public function callback(Request $request)
    {
        if ($request->has('code')) {
            $this->gmailService->authenticate($request->code);
            return redirect()->route('gmail.list');
        }
        return "Errore nell'autenticazione";
    }

    public function list()
    {
        // 1. Recupera le email usando l'ID Etichetta che hai trovato
        $emails = $this->gmailService->getPrivacyEmails();

        // Se l'utente non è loggato o il token è scaduto
        if ($emails === null) {
            return redirect()->to('/dev-login');
        }

        // 2. Genera il nome del file con la data corrente
        $fileName = 'privacy_innovative_export_' . date('Y-m-d_H-i') . '.csv';

        // 3. Crea e scarica il CSV
        return response()->streamDownload(function () use ($emails) {
            // Apre lo stream di output
            $file = fopen('php://output', 'w');

            // Aggiunge il BOM per far leggere correttamente i caratteri speciali (accenti) a Excel
            fputs($file, "\u{FEFF}");

            // Intestazioni delle colonne
            fputcsv($file, ['Data', 'Mittente', 'Destinatario', 'Oggetto'], ';');

            foreach ($emails as $email) {
                // Formattiamo la data in modo leggibile
                $dataFormatted = isset($email['data'])
                    ? Carbon::parse($email['data'])->format('d/m/Y H:i:s')
                    : '';

                fputcsv($file, [
                    $dataFormatted,
                    $email['mittente'],
                    $email['destinatario'],
                    $email['oggetto']
                ], ';');  // Uso il punto e virgola come separatore, Excel lo preferisce in Europa
            }

            fclose($file);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function listview()
    {
        $emails = $this->gmailService->getPrivacyEmails();

        if ($emails === null) {
            return redirect()->route('gmail.login');
        }

        return view('gmail.emails', compact('emails'));
    }

    public function debugLabels()
    {
        $labels = $this->gmailService->getAllLabels();

        if ($labels === null) {
            return redirect()->route('gmail.login');
        }

        // Restituisce una collezione pulita per vedere i nomi esatti
        return response()->json($labels);
    }

    public function importToDb()
    {
        // 1. Recupera le email da Google
        $emails = $this->gmailService->getPrivacyEmails();

        if ($emails === null) {
            return redirect()->to('/dev-login');
        }

        $countDipendenti = 0;
        $countMandatarie = 0;

        foreach ($emails as $emailData) {
            $rawFrom = $emailData['mittente'];  // Es: "Mario Rossi <mario@innova-tech.cloud>"

            // Logica per separare Nome da Email
            if (str_contains($rawFrom, '<')) {
                $name = trim(Str::before($rawFrom, '<'));
                $emailAddress = trim(Str::between($rawFrom, '<', '>'));
            } else {
                $name = '';  // Nessun nome visualizzato
                $emailAddress = trim($rawFrom);
            }

            // Pulizia finale (rimuovi doppi apici se presenti)
            $name = str_replace('"', '', $name);

            // LOGICA DI SMISTAMENTO
            // Usiamo updateOrCreate per non creare duplicati se riesegui lo script
            if (str_ends_with($emailAddress, '@innova-tech.cloud')) {
                Dipendente::updateOrCreate(
                    ['email' => $emailAddress],  // Cerca per email
                    ['name' => $name]  // Aggiorna il nome se cambiato
                );
                $countDipendenti++;
            } else {
                Referente::updateOrCreate(
                    ['email' => $emailAddress],
                    ['name' => $name]
                );
                $countMandatarie++;
            }
        }

        return 'Importazione completata!<br>'
            . "Dipendenti salvati/aggiornati: <strong>$countDipendenti</strong><br>"
            . "Mandatarie salvate/aggiornate: <strong>$countMandatarie</strong>";
    }
}
