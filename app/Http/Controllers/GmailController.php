<?php
namespace App\Http\Controllers;

use App\Services\GoogleGmailService;
use Illuminate\Http\Request;

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
}
