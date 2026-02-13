<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Dipendente;
use App\Models\GmailLabel;
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
        $fileName = 'privacy_export_' . date('Y-m-d_H-i') . '.csv';

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
        $emails = $this->gmailService->getEmailsFromDbLabels();

        if ($emails === null) {
            return redirect()->to('/dev-login');
        }

        $countDipendenti = 0;
        $countMandatarie = 0;

        foreach ($emails as $emailData) {
            $rawFrom = $emailData['mittente'];
            $currentLabelId = $emailData['label_id'];

            // Recuperiamo il dominio atteso per questa etichetta (es. "innova-tech.cloud")
            $expectedDomain = $emailData['label_dominio'];

            // Estrazione dati mittente
            if (str_contains($rawFrom, '<')) {
                $name = trim(Str::before($rawFrom, '<'));
                $emailAddress = trim(Str::between($rawFrom, '<', '>'));
            } else {
                $name = '';
                $emailAddress = trim($rawFrom);
            }
            $name = str_replace('"', '', $name);

            // LOGICA DINAMICA
            // 1. Controlliamo se c'è un dominio configurato per questa label
            // 2. Controlliamo se l'email finisce con quel dominio (aggiungiamo la @ per sicurezza)
            $isDipendente = false;

            if (!empty($expectedDomain)) {
                // Se nel DB hai scritto "innova-tech.cloud", aggiungiamo la "@"
                // Se hai scritto "@innova-tech.cloud", la gestiamo per evitare doppi "@@"
                $domainCheck = str_starts_with($expectedDomain, '@') ? $expectedDomain : '@' . $expectedDomain;

                if (str_ends_with(strtolower($emailAddress), strtolower($domainCheck))) {
                    $isDipendente = true;
                }
            }

            if ($isDipendente) {
                Dipendente::updateOrCreate(
                    ['email' => $emailAddress],
                    [
                        'name' => $name,
                        'label_id' => $currentLabelId
                    ]
                );
                $countDipendenti++;
            } else {
                // Se il dominio non coincide (o è vuoto), va in Mandatarie
                Referente::updateOrCreate(
                    ['email' => $emailAddress],
                    [
                        'name' => $name,
                        'label_id' => $currentLabelId
                    ]
                );
                $countMandatarie++;
            }
        }

        return 'Importazione completata!<br>'
            . "Dipendenti (match dominio label): <strong>$countDipendenti</strong><br>"
            . "Mandatarie (altri): <strong>$countMandatarie</strong>";
    }

    /**
     * Visualizza tutti gli allegati scaricati
     */
    public function listAttachments()
    {
        $attachments = Attachment::with('emailInteractions')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('gmail.attachments', compact('attachments'));
    }

    /**
     * Scarica un allegato specifico
     */
    public function downloadAttachment($id)
    {
        $attachment = Attachment::findOrFail($id);

        if (!$attachment->fileExists()) {
            abort(404, 'File non trovato su disco');
        }

        return response()->download(
            $attachment->full_path,
            $attachment->filename
        );
    }

    /**
     * Scarica tutti gli allegati di un messaggio come ZIP
     */
    public function downloadMessageAttachments($messageId)
    {
        $attachments = Attachment::where('message_id', $messageId)
            ->where('downloaded', true)
            ->get();

        if ($attachments->isEmpty()) {
            abort(404, 'Nessun allegato trovato per questo messaggio');
        }

        // Crea un file ZIP temporaneo
        $zipFileName = "attachments_{$messageId}_" . date('YmdHis') . '.zip';
        $zipPath = storage_path("app/temp/{$zipFileName}");

        // Crea la directory temp se non esiste
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            abort(500, 'Impossibile creare il file ZIP');
        }

        foreach ($attachments as $attachment) {
            if ($attachment->fileExists()) {
                $zip->addFile($attachment->full_path, $attachment->filename);
            }
        }

        $zip->close();

        // Scarica e poi elimina il file temporaneo
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * API per ottenere statistiche sugli allegati
     */
    public function attachmentStats()
    {
        $totalAttachments = Attachment::count();
        $totalSize = Attachment::sum('size');
        $byMimeType = Attachment::selectRaw('mime_type, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('mime_type')
            ->get();

        return response()->json([
            'total_attachments' => $totalAttachments,
            'total_size_bytes' => $totalSize,
            'total_size_readable' => $this->formatBytes($totalSize),
            'by_mime_type' => $byMimeType
        ]);
    }

    /**
     * Formatta i bytes in formato leggibile
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
