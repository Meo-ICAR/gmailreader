<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\EmailInteraction;
use App\Models\User;
use Carbon\Carbon;
use Google\Service\Gmail;
use Google\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGmailMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $messageId;
    protected $labelName;
    protected $userId;

    // Passiamo l'ID utente per recuperare il token giusto nel worker
    public function __construct($messageId, $labelName, $userId)
    {
        $this->messageId = $messageId;
        $this->labelName = $labelName;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        // 1. Setup Client Google (simile al Service, ma isolato nel Job)
        $user = User::find($this->userId);
        if (!$user || !$user->google_token)
            return;

        $client = new Client();
        $client->setClientId(env('GMAIL_CLIENT_ID'));
        $client->setClientSecret(env('GMAIL_CLIENT_SECRET'));
        $client->setAccessToken($user->google_token);

        // Refresh token se necessario
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                $user->update(['google_token' => $newToken]);
            } else {
                return;  // Token scaduto irreparabilmente
            }
        }

        $gmail = new Gmail($client);

        try {
            // 2. Scarica il messaggio
            $msg = $gmail->users_messages->get('me', $this->messageId);
            $payload = $msg->getPayload();
            $headers = $payload->getHeaders();

            $subject = '(Nessun Oggetto)';
            $date = now();
            $from = '';
            $cc = [];

            // 3. Parsing Header
            foreach ($headers as $header) {
                $name = strtoupper($header->getName());
                if ($name === 'SUBJECT')
                    $subject = $header->getValue();
                if ($name === 'DATE')
                    $date = Carbon::parse($header->getValue());
                if ($name === 'FROM')
                    $from = $this->extractEmail($header->getValue());
                if ($name === 'CC')
                    $cc = explode(',', $header->getValue());
            }

            // 4. Salva il MITTENTE
            if ($from) {
                EmailInteraction::create([
                    'message_id' => $this->messageId,
                    'email_address' => $from,
                    'domain' => $this->extractDomain($from),
                    'role' => 'FROM',
                    'subject' => $subject,
                    'sent_at' => $date,
                    'label_name' => $this->labelName
                ]);
            }

            // 5. Salva i CC
            foreach ($cc as $ccRaw) {
                $ccEmail = $this->extractEmail($ccRaw);
                if ($ccEmail) {
                    EmailInteraction::create([
                        'message_id' => $this->messageId,
                        'email_address' => $ccEmail,
                        'domain' => $this->extractDomain($ccEmail),
                        'role' => 'CC',
                        'subject' => $subject,
                        'sent_at' => $date,
                        'label_name' => $this->labelName
                    ]);
                }
            }

            // 6. (Opzionale) Parsing del BODY per email extra (usa la logica vista prima)
            // ... qui puoi inserire la logica getRawBody e Regex ...
            // e salvare con role => 'BODY_MATCH'

            // 7. Gestione degli allegati
            $this->processAttachments($gmail, $msg);
        } catch (\Exception $e) {
            // Log errore silenzioso o riprova
            \Log::error("Errore processando messaggio {$this->messageId}: " . $e->getMessage());
        }
    }

    /**
     * Processa e salva gli allegati di un messaggio
     */
    private function processAttachments($gmail, $message)
    {
        $payload = $message->getPayload();
        $attachments = $this->extractAttachments($gmail, $this->messageId, $payload);

        foreach ($attachments as $attachmentData) {
            try {
                // Verifica se l'allegato è già stato salvato
                $existing = Attachment::where('message_id', $this->messageId)
                    ->where('attachment_id', $attachmentData['attachmentId'])
                    ->first();

                if (!$existing) {
                    // Decodifica e salva il file
                    $data = str_replace(['-', '_'], ['+', '/'], $attachmentData['data']);
                    $decodedData = base64_decode($data);

                    // Crea nome file sicuro
                    $safeFilename = $this->sanitizeFilename($attachmentData['filename']);
                    $storagePath = "attachments/{$this->labelName}/" . date('Y-m');
                    $fullPath = storage_path("app/{$storagePath}/{$safeFilename}");

                    // Crea directory se non esiste
                    $directory = dirname($fullPath);
                    if (!is_dir($directory)) {
                        mkdir($directory, 0755, true);
                    }

                    // Salva il file
                    file_put_contents($fullPath, $decodedData);

                    // Salva record nel database
                    Attachment::create([
                        'message_id' => $this->messageId,
                        'attachment_id' => $attachmentData['attachmentId'],
                        'filename' => $attachmentData['filename'],
                        'safe_filename' => $safeFilename,
                        'mime_type' => $attachmentData['mimeType'],
                        'size' => $attachmentData['size'],
                        'storage_path' => "{$storagePath}/{$safeFilename}",
                        'downloaded' => true
                    ]);

                    \Log::info("Allegato salvato: {$safeFilename} per messaggio {$this->messageId}");
                }
            } catch (\Exception $e) {
                \Log::error("Errore salvando allegato {$attachmentData['filename']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Estrae ricorsivamente gli allegati dal payload del messaggio
     */
    private function extractAttachments($gmail, $messageId, $part)
    {
        $attachments = [];

        // Se la parte ha sotto-parti, processa ricorsivamente
        if ($part->getParts()) {
            foreach ($part->getParts() as $subPart) {
                $attachments = array_merge(
                    $attachments,
                    $this->extractAttachments($gmail, $messageId, $subPart)
                );
            }
        }

        // Verifica se questa parte è un allegato
        $body = $part->getBody();
        $filename = $part->getFilename();

        if (!empty($filename) && $body->getAttachmentId()) {
            try {
                // Scarica l'allegato da Gmail
                $attachment = $gmail->users_messages_attachments->get(
                    'me',
                    $messageId,
                    $body->getAttachmentId()
                );

                $attachments[] = [
                    'filename' => $filename,
                    'mimeType' => $part->getMimeType(),
                    'size' => $body->getSize(),
                    'attachmentId' => $body->getAttachmentId(),
                    'data' => $attachment->getData()
                ];
            } catch (\Exception $e) {
                \Log::error("Errore scaricamento allegato {$filename}: " . $e->getMessage());
            }
        }

        return $attachments;
    }

    /**
     * Sanitizza il nome del file
     */
    private function sanitizeFilename($filename)
    {
        // Rimuovi caratteri pericolosi
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Aggiungi timestamp per evitare conflitti
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        
        // Limita la lunghezza del basename
        $basename = substr($basename, 0, 50);
        
        return $basename . '_' . time() . '_' . uniqid() . '.' . $extension;
        } catch (\Exception $e) {
            // Log errore silenzioso o riprova
            \Log::error("Errore processando messaggio {$this->messageId}: " . $e->getMessage());
        }
    }

    private function extractEmail($string)
    {
        preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $string, $matches);
        return $matches[0] ?? null;
    }

    private function extractDomain($email)
    {
        if (!$email)
            return null;
        $parts = explode('@', $email);
        return $parts[1] ?? null;
    }
}
