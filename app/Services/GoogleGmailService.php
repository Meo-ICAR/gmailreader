<?php

namespace App\Services;

use App\Models\GmailLabel;
use Google\Service\Gmail;
use Google\Client;
use Illuminate\Support\Facades\Auth;

class GoogleGmailService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(env('GMAIL_CLIENT_ID'));
        $this->client->setClientSecret(env('GMAIL_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GMAIL_REDIRECT_URI'));
        // Aggiunto scope per modifiche (necessario per scaricare allegati)
        $this->client->addScope(Gmail::GMAIL_READONLY);
        $this->client->addScope(Gmail::GMAIL_MODIFY);
        $this->client->setAccessType('offline');  // Fondamentale per ricevere il refresh_token
        $this->client->setPrompt('select_account consent');
    }

    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    public function authenticate($code)
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        $this->saveToken($token);
        return $token;
    }

    protected function saveToken($token)
    {
        $user = Auth::user();
        $user->google_token = $token;  // Laravel 12 casta automaticamente array in JSON se definito nel Model
        $user->save();
    }

    /**
     * Prepara il client con il token dell'utente
     */
    protected function setupClient()
    {
        $user = Auth::user();
        if (!$user || !$user->google_token)
            return false;

        $this->client->setAccessToken($user->google_token);

        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                $user->update(['google_token' => $newToken]);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * TEST: Elenca tutte le etichette disponibili
     */
    public function getAllLabels()
    {
        if (!$this->setupClient())
            return null;

        $gmail = new Gmail($this->client);
        $results = $gmail->users_labels->listUsersLabels('me');

        $labelList = [];
        foreach ($results->getLabels() as $label) {
            $labelList[] = [
                'id' => $label->getId(),
                'name' => $label->getName(),  // Questo è quello che ci serve per la query
                'type' => $label->getType()
            ];
        }
        return $labelList;
    }

    public function getPrivacyEmails()
    {
        if (!$this->setupClient())
            return null;

        $gmail = new Gmail($this->client);

        // MODIFICA QUI: Usiamo l'ID esatto che hai trovato nel debug
        // Questo è molto più preciso e veloce della ricerca per nome
        $params = [
            'labelIds' => ['Label_6119983706498995731'],
            'maxResults' => 9999  // Puoi alzarlo a 50 o 100
        ];

        $response = $gmail->users_messages->listUsersMessages('me', $params);

        $emails = [];
        $messages = $response->getMessages();

        if ($messages) {
            foreach ($messages as $messageSummary) {
                // Recuperiamo il messaggio completo
                $msg = $gmail->users_messages->get('me', $messageSummary->getId());
                $headers = $msg->getPayload()->getHeaders();

                $emailData = [
                    'id' => $msg->getId(),
                    'mittente' => 'Sconosciuto',
                    'destinatario' => 'Sconosciuto',
                    'oggetto' => '(Nessun Oggetto)',
                    'data' => ''
                ];

                // Ciclo ottimizzato per estrarre gli header
                foreach ($headers as $header) {
                    $name = $header->getName();
                    $value = $header->getValue();

                    if ($name === 'From')
                        $emailData['mittente'] = $value;
                    elseif ($name === 'To')
                        $emailData['destinatario'] = $value;
                    elseif ($name === 'Subject')
                        $emailData['oggetto'] = $value;
                    elseif ($name === 'Date')
                        $emailData['data'] = $value;
                }
                $emails[] = $emailData;
            }
        }

        return $emails;
    }

    public function getPrivacyEmailslabel()
    {
        $user = Auth::user();
        if (!$user->google_token)
            return null;

        $this->client->setAccessToken($user->google_token);

        // Se il token è scaduto, lo rinfreschiamo
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                $this->saveToken($newToken);
            } else {
                return null;  // Necessario nuovo login
            }
        }

        $gmail = new Gmail($this->client);
        // Cerchiamo messaggi con etichetta "privacy"
        $response = $gmail->users_messages->listUsersMessages('me', ['q' => 'label:PRIVACY/INNOVATIVE']);

        $emails = [];
        foreach ($response->getMessages() as $messageSummary) {
            $msg = $gmail->users_messages->get('me', $messageSummary->getId());
            $headers = $msg->getPayload()->getHeaders();

            $emailData = ['id' => $msg->getId()];
            foreach ($headers as $header) {
                if ($header->getName() == 'From')
                    $emailData['mittente'] = $header->getValue();
                if ($header->getName() == 'To')
                    $emailData['destinatario'] = $header->getValue();
                if ($header->getName() == 'Subject')
                    $emailData['oggetto'] = $header->getValue();
                if ($header->getName() == 'Date')
                    $emailData['data'] = $header->getValue();
            }
            $emails[] = $emailData;
        }

        return $emails;
    }

    public function getEmailsFromDbLabels()
    {
        if (!$this->setupClient())
            return null;
        $gmail = new Gmail($this->client);
        $dbLabels = GmailLabel::all();
        $allEmails = [];

        foreach ($dbLabels as $label) {
            $params = ['labelIds' => [$label->google_id], 'maxResults' => 10];  // Limitato per test

            try {
                $response = $gmail->users_messages->listUsersMessages('me', $params);
                $messages = $response->getMessages();

                if ($messages) {
                    foreach ($messages as $messageSummary) {
                        $msg = $gmail->users_messages->get('me', $messageSummary->getId());
                        $payload = $msg->getPayload();  // Payload completo
                        $headers = $payload->getHeaders();

                        $emailData = [
                            'id' => $msg->getId(),
                            'label_id' => $label->google_id,
                            'label_name' => $label->name,
                            'label_dominio' => $label->dominio,
                            'mittente' => 'Sconosciuto',
                            'destinatario' => 'Sconosciuto',
                            'oggetto' => '(Nessun Oggetto)',
                            'data' => '',
                            'body_emails' => []  // <--- NUOVO CAMPO
                        ];

                        // 1. Estrai Header (come prima)
                        foreach ($headers as $header) {
                            $name = $header->getName();
                            $value = $header->getValue();
                            if ($name === 'From')
                                $emailData['mittente'] = $value;
                            elseif ($name === 'To')
                                $emailData['destinatario'] = $value;
                            elseif ($name === 'Subject')
                                $emailData['oggetto'] = $value;
                            elseif ($name === 'Date')
                                $emailData['data'] = $value;
                        }

                        // 2. Estrai il corpo del messaggio
                        $rawBody = $this->getRawBody($payload);

                        // 3. Cerca tutte le email nel corpo usando REGEX
                        // Pattern: cerca stringhe nel formato xxxx@xxxx.xx
                        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rawBody, $matches);

                        // $matches[0] contiene tutte le email trovate
                        if (!empty($matches[0])) {
                            // Puliamo l'array rimuovendo duplicati e rendendo tutto minuscolo
                            $foundEmails = array_unique(array_map('strtolower', $matches[0]));

                            // (Opzionale) Rimuovi l'email del mittente originale se vuoi solo quelle "extra"
                            // $senderEmail = ... estrai email da $emailData['mittente']
                            // $foundEmails = array_diff($foundEmails, [$senderEmail]);

                            $emailData['body_emails'] = array_values($foundEmails);
                        }

                        $allEmails[] = $emailData;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return $allEmails;
    }

    /**
     * Funzione ricorsiva per estrarre il testo da un messaggio Gmail (Multipart)
     */
    private function getRawBody($payload)
    {
        $body = '';

        // Se il messaggio ha parti nidificate (es. testo + html)
        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                if ($part->getMimeType() === 'text/plain') {
                    $data = $part->getBody()->getData();
                } elseif ($part->getParts()) {
                    // Ricorsione se ci sono sotto-parti
                    $body .= $this->getRawBody($part);
                }
            }
        }
        // Se il messaggio è semplice
        elseif ($payload->getBody()->getData()) {
            $data = $payload->getBody()->getData();
        }

        if (isset($data)) {
            // Decodifica Base64URL usato da Gmail
            $body .= base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
        }

        return $body;
    }

    // In GoogleGmailService.php

    public function dispatchBatchImport()
    {
        if (!$this->setupClient())
            return false;

        $gmail = new Gmail($this->client);
        $dbLabels = \App\Models\GmailLabel::all();
        $userId = \Illuminate\Support\Facades\Auth::id();

        foreach ($dbLabels as $label) {
            $pageToken = null;

            do {
                $params = [
                    'labelIds' => [$label->google_id],
                    'maxResults' => 50,  // Scarichiamo 50 ID alla volta
                    'pageToken' => $pageToken
                ];

                try {
                    $response = $gmail->users_messages->listUsersMessages('me', $params);
                    $messages = $response->getMessages();

                    if ($messages) {
                        foreach ($messages as $message) {
                            // Verifica se l'abbiamo già importato per non duplicare i job
                            $exists = \App\Models\EmailInteraction::where('message_id', $message->getId())->exists();

                            if (!$exists) {
                                // Lancia il Job in coda
                                \App\Jobs\ProcessGmailMessage::dispatch(
                                    $message->getId(),
                                    $label->name,
                                    $userId
                                );
                            }
                        }
                    }
                    $pageToken = $response->getNextPageToken();
                } catch (\Exception $e) {
                    break;  // Interrompi loop etichetta in caso di errore
                }
            } while ($pageToken);
        }

        return true;
    }

    /**
     * Scarica gli allegati da un messaggio Gmail specifico
     * 
     * @param string $messageId ID del messaggio Gmail
     * @return array Array di allegati con informazioni e contenuto
     */
    public function downloadAttachments($messageId)
    {
        if (!$this->setupClient())
            return [];

        $gmail = new Gmail($this->client);
        $attachments = [];

        try {
            // Recupera il messaggio completo
            $message = $gmail->users_messages->get('me', $messageId);
            $payload = $message->getPayload();

            // Processa le parti del messaggio per trovare allegati
            $attachments = $this->processMessageParts($gmail, $messageId, $payload);
        } catch (\Exception $e) {
            \Log::error("Errore scaricamento allegati per messaggio {$messageId}: " . $e->getMessage());
        }

        return $attachments;
    }

    /**
     * Processa ricorsivamente le parti del messaggio per trovare allegati
     * 
     * @param Gmail $gmail Istanza del servizio Gmail
     * @param string $messageId ID del messaggio
     * @param object $part Parte del messaggio da processare
     * @return array Array di allegati trovati
     */
    private function processMessageParts($gmail, $messageId, $part)
    {
        $attachments = [];

        // Se la parte ha sotto-parti, processa ricorsivamente
        if ($part->getParts()) {
            foreach ($part->getParts() as $subPart) {
                $attachments = array_merge(
                    $attachments,
                    $this->processMessageParts($gmail, $messageId, $subPart)
                );
            }
        }

        // Verifica se questa parte è un allegato
        $body = $part->getBody();
        $filename = $part->getFilename();

        if (!empty($filename) && $body->getAttachmentId()) {
            try {
                // Scarica l'allegato
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
                    'data' => $attachment->getData() // Dati in formato base64url
                ];
            } catch (\Exception $e) {
                \Log::error("Errore scaricamento singolo allegato {$filename}: " . $e->getMessage());
            }
        }

        return $attachments;
    }

    /**
     * Salva un allegato su disco
     * 
     * @param array $attachmentData Dati dell'allegato da salvare
     * @param string $storagePath Percorso di storage relativo
     * @return string|null Percorso del file salvato o null in caso di errore
     */
    public function saveAttachment($attachmentData, $storagePath = 'attachments')
    {
        try {
            // Decodifica i dati da base64url a binario
            $data = str_replace(['-', '_'], ['+', '/'], $attachmentData['data']);
            $decodedData = base64_decode($data);

            // Crea un nome file sicuro
            $filename = $this->sanitizeFilename($attachmentData['filename']);
            $fullPath = storage_path("app/{$storagePath}/{$filename}");

            // Crea la directory se non esiste
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Salva il file
            file_put_contents($fullPath, $decodedData);

            return "{$storagePath}/{$filename}";
        } catch (\Exception $e) {
            \Log::error("Errore salvataggio allegato: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sanitizza il nome del file per renderlo sicuro
     * 
     * @param string $filename Nome file originale
     * @return string Nome file sanitizzato
     */
    private function sanitizeFilename($filename)
    {
        // Rimuovi caratteri non sicuri
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Aggiungi timestamp per evitare conflitti
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        
        return $basename . '_' . time() . '.' . $extension;
    }
}
