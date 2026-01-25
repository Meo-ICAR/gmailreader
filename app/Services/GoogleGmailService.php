<?php

namespace App\Services;

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
            }
            $emails[] = $emailData;
        }

        return $emails;
    }
}
