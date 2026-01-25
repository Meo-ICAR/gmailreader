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
        $this->client->addScope(Gmail::GMAIL_READONLY);
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
            'maxResults' => 10  // Puoi alzarlo a 50 o 100
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

        // 1. Recuperiamo tutte le etichette dal database locale
        // Puoi filtrare qui se non vuoi scansionare le etichette di sistema (es. TRASH, SPAM)
        // Esempio: GmailLabel::where('type', 'user')->get();
        $dbLabels = GmailLabel::where('type', 'user')->get();

        $allEmails = [];

        foreach ($dbLabels as $label) {
            // Prepariamo la query per QUESTA specifica etichetta
            $params = [
                'labelIds' => [$label->google_id],
                'maxResults' => 100  // Teniamo basso per test, aumenta a 50 o 100 per produzione
            ];

            try {
                // Chiamata API per l'etichetta corrente
                $response = $gmail->users_messages->listUsersMessages('me', $params);
                $messages = $response->getMessages();

                if ($messages) {
                    foreach ($messages as $messageSummary) {
                        // Recupero dettagli messaggio
                        $msg = $gmail->users_messages->get('me', $messageSummary->getId());
                        $headers = $msg->getPayload()->getHeaders();

                        $emailData = [
                            'id' => $msg->getId(),
                            // DATI RICHIESTI: Memorizziamo l'ID e il Nome della Label corrente
                            'label_id' => $label->google_id,
                            'label_name' => $label->name,
                            'label_dominio' => $label->dominio,
                            // Dati standard
                            'mittente' => 'Sconosciuto',
                            'destinatario' => 'Sconosciuto',
                            'oggetto' => '(Nessun Oggetto)',
                            'data' => ''
                        ];

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

                        $allEmails[] = $emailData;
                    }
                }
            } catch (\Exception $e) {
                // Se un'etichetta dà errore (es. non esiste più su Google), continuiamo con la prossima
                continue;
            }
        }

        return $allEmails;
    }
}
