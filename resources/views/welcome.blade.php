<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail Connector Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .step-card { transition: transform 0.2s; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .step-card:hover { transform: translateY(-5px); }
        .hero-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 0; margin-bottom: 2rem; border-radius: 0 0 20px 20px; }
    </style>
</head>
<body class="bg-light">

    <div class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold"><i class="bi bi-google"></i> Gmail Connector</h1>
            <p class="lead">Strumento di importazione e analisi email</p>

            <div class="mt-4">
                @auth
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-person-check-fill"></i> Loggato come: {{ Auth::user()->email }}
                    </span>
                    @if(Auth::user()->google_token)
                         <span class="badge bg-info text-dark fs-6 px-3 py-2 ms-2">
                            <i class="bi bi-link-45deg"></i> Gmail Collegato
                            <a href="/gmail/debug-labels" class="btn btn-outline-light btn-sm ms-2 text-decoration-none fw-bold">
                                <i class="bi bi-box-arrow-right"></i> Labels
                            </a>
                            <hr>
                              <a href="/import-db" class="btn btn-outline-light btn-sm ms-2 text-decoration-none fw-bold">
                        <i class="bi bi-box-arrow-in-right"></i> Importa in DB
                    </a>
                        </span>
                         {{-- NUOVO PULSANTE AGGIUNTO QUI --}}

                    @else
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2 ms-2">
                            <i class="bi bi-exclamation-triangle"></i> Gmail NON Collegato
                        </span>
                        <a href="/gmail/login" class="btn btn-outline-light btn-sm ms-2 text-decoration-none fw-bold">
                            <i class="bi bi-box-arrow-in-right"></i> Collega Gmail
                        </a>
                    @endif
                @else
                    <span class="badge bg-danger fs-6 px-3 py-2">
                        <i class="bi bi-person-x-fill"></i> Non autenticato in Laravel
                    </span>
                    {{-- NUOVO PULSANTE AGGIUNTO QUI --}}
                    <a href="/dev-login" class="btn btn-outline-light btn-sm ms-2 text-decoration-none fw-bold">
                        <i class="bi bi-box-arrow-in-right"></i> Accedi ora
                    </a>
                @endauth
            </div>
        </div>
    </div>
