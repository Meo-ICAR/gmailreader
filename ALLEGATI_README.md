# Gestione Allegati Gmail - Laravel 12

## 📋 Funzionalità Aggiunte

Questo aggiornamento aggiunge la funzionalità completa di **scarico e gestione degli allegati** dalle email Gmail.

### ✨ Caratteristiche Principali

1. **Scaricamento Automatico**: Gli allegati vengono scaricati automaticamente durante l'elaborazione dei messaggi Gmail
2. **Storage Organizzato**: I file vengono salvati in modo organizzato per label e data (`storage/app/attachments/{label}/{anno-mese}/`)
3. **Database Tracking**: Tutti gli allegati sono tracciati nel database con metadati completi
4. **Prevenzione Duplicati**: Sistema intelligente per evitare di scaricare lo stesso allegato più volte
5. **API REST**: Endpoint per gestire e visualizzare gli allegati
6. **Interfaccia Web**: Pagina dedicata per visualizzare e scaricare gli allegati

## 🚀 Installazione e Setup

### 1. Esegui le Migrazioni

Devi eseguire la nuova migrazione per creare la tabella `attachments`:

```bash
php artisan migrate
```

### 2. Ri-autorizza Gmail (IMPORTANTE!)

Poiché abbiamo aggiunto lo scope `Gmail::GMAIL_MODIFY` per poter scaricare gli allegati, **devi ri-autorizzare l'applicazione** con Google:

1. Vai su `/dev-login` per loggarti
2. Vai su `/gmail/login` per ri-autorizzare Gmail
3. Accetta i nuovi permessi richiesti da Google

### 3. Crea le Directory di Storage

Le directory vengono create automaticamente, ma puoi verificare i permessi:

```bash
mkdir -p storage/app/attachments
chmod -R 755 storage/app/attachments
```

## 📂 Struttura File Aggiunti/Modificati

### File Nuovi
- `app/Models/Attachment.php` - Model per gli allegati
- `database/migrations/2026_02_13_085609_create_attachments_table.php` - Migrazione tabella
- `resources/views/gmail/attachments.blade.php` - Interfaccia visualizzazione allegati
- `ALLEGATI_README.md` - Questo file

### File Modificati
- `app/Services/GoogleGmailService.php` - Aggiunto scope GMAIL_MODIFY e metodi per scarico allegati
- `app/Jobs/ProcessGmailMessage.php` - Aggiunta logica per processare allegati
- `app/Http/Controllers/GmailController.php` - Aggiunti metodi per gestione allegati
- `routes/web.php` - Aggiunte rotte per allegati

## 🔧 Come Funziona

### Processo Automatico

Quando esegui l'import batch (`/gmail/start-batch`):

1. Il sistema scarica la lista dei messaggi Gmail
2. Per ogni messaggio, viene creato un Job `ProcessGmailMessage`
3. Il Job elabora il messaggio e **automaticamente**:
   - Estrae gli header (mittente, destinatario, CC)
   - **Cerca tutti gli allegati** nel messaggio
   - **Scarica gli allegati** da Gmail
   - **Salva i file** su disco in `storage/app/attachments/{label}/{anno-mese}/`
   - **Registra i metadati** nel database

### Struttura Storage

Gli allegati vengono salvati seguendo questa struttura:

```
storage/app/attachments/
├── PRIVACY/
│   └── 2026-02/
│       ├── documento_1234567890_abc123.pdf
│       ├── immagine_1234567891_def456.jpg
│       └── ...
└── INNOVATIVE/
    └── 2026-02/
        └── ...
```

## 🌐 Rotte API Disponibili

### Visualizzazione Web

- **`GET /gmail/attachments`** - Pagina con lista di tutti gli allegati (con paginazione)
  - Mostra: nome file, tipo MIME, dimensione, messaggio associato, data
  - Azioni: scarica singolo file

### Download

- **`GET /gmail/attachments/{id}/download`** - Scarica un singolo allegato
  - Parametri: `{id}` = ID dell'allegato nel database
  - Risposta: File download diretto

- **`GET /gmail/attachments/message/{messageId}/download-all`** - Scarica tutti gli allegati di un messaggio come ZIP
  - Parametri: `{messageId}` = ID del messaggio Gmail
  - Risposta: File ZIP con tutti gli allegati

### Statistiche

- **`GET /gmail/attachments/stats`** - Statistiche JSON sugli allegati
  - Risposta JSON:
    ```json
    {
      "total_attachments": 150,
      "total_size_bytes": 52428800,
      "total_size_readable": "50.00 MB",
      "by_mime_type": [
        {
          "mime_type": "application/pdf",
          "count": 80,
          "total_size": 30000000
        },
        ...
      ]
    }
    ```

## 📊 Model Attachment

### Campi Principali

```php
- id                 // ID univoco
- message_id         // ID messaggio Gmail
- attachment_id      // ID univoco allegato su Gmail
- filename           // Nome file originale
- safe_filename      // Nome file sanitizzato salvato su disco
- mime_type          // Tipo MIME (application/pdf, image/jpeg, etc.)
- size               // Dimensione in bytes
- storage_path       // Percorso relativo in storage
- downloaded         // Flag booleano (true se scaricato)
- created_at         // Data creazione
- updated_at         // Data aggiornamento
```

### Metodi Utili

```php
$attachment = Attachment::find(1);

// Ottieni percorso completo
$attachment->full_path; // "/var/www/storage/app/attachments/..."

// Verifica esistenza file
$attachment->fileExists(); // true/false

// Dimensione leggibile
$attachment->readable_size; // "2.5 MB"

// Filtra per tipo
Attachment::ofType('application/pdf')->get(); // Solo PDF
Attachment::ofType('image')->get();           // Tutte le immagini

// Filtra per stato download
Attachment::downloaded()->get();
Attachment::notDownloaded()->get();
```

## 🔐 Sicurezza

### Sanitizzazione Nome File

I nomi dei file vengono automaticamente sanitizzati per prevenire:
- Directory traversal attacks
- Caratteri speciali pericolosi
- Conflitti di nomi (aggiunto timestamp + uniqid)

Esempio:
```
"../../etc/passwd.txt" → "passwd_txt_1676289012_65e1a2b3c4d5e.txt"
"Fattura #123.pdf"     → "Fattura__123_pdf_1676289012_65e1a2b3c4d5e.pdf"
```

### Permessi File

- Directory: `0755` (rwxr-xr-x)
- File: Eredita permessi dal sistema (solitamente `0644`)

## 🐛 Troubleshooting

### Gli allegati non vengono scaricati

**Problema**: Il Job viene eseguito ma non vedo allegati

**Soluzione**:
1. Verifica di aver ri-autorizzato Gmail con i nuovi scope
2. Controlla i log: `tail -f storage/logs/laravel.log`
3. Verifica permessi directory storage: `ls -la storage/app/`

### Errore "Token scaduto"

**Problema**: Errori relativi al token Google

**Soluzione**:
1. Vai su `/gmail/login` per ri-autorizzare
2. Assicurati che il refresh_token sia salvato nel database
3. Verifica le credenziali in `.env`: `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`

### File non trovato durante il download

**Problema**: Errore 404 quando scarico un allegato

**Soluzione**:
1. Verifica che il file esista: `ls -la storage/app/attachments/`
2. Controlla il campo `storage_path` nel database
3. Verifica permessi: `chmod -R 755 storage/app/attachments`

### Dimensione massima allegato

**Problema**: Allegati molto grandi non vengono scaricati

**Soluzione**:
1. Aumenta `memory_limit` in `php.ini`
2. Aumenta timeout del Job in `ProcessGmailMessage.php`:
   ```php
   public $timeout = 600; // 10 minuti
   ```

## 📝 Utilizzo da Codice

### Scaricare allegati manualmente per un messaggio

```php
use App\Services\GoogleGmailService;

$service = new GoogleGmailService();
$attachments = $service->downloadAttachments('message_id_qui');

foreach ($attachments as $attachment) {
    echo $attachment['filename'] . " - " . $attachment['size'] . " bytes\n";
}
```

### Salvare un allegato specifico

```php
$service = new GoogleGmailService();
$attachmentData = [...]; // Dati da downloadAttachments()
$path = $service->saveAttachment($attachmentData, 'my-folder');
echo "Salvato in: $path";
```

### Query allegati

```php
// Tutti gli allegati PDF di oggi
$pdfs = Attachment::ofType('application/pdf')
    ->whereDate('created_at', today())
    ->get();

// Allegati per un messaggio specifico
$attachments = Attachment::where('message_id', 'xyz123')->get();

// Allegati più grandi di 5MB
$large = Attachment::where('size', '>', 5 * 1024 * 1024)->get();
```

## 🎯 Best Practices

1. **Monitora lo spazio disco**: Gli allegati possono occupare molto spazio
   ```bash
   du -sh storage/app/attachments/
   ```

2. **Backup regolari**: Includi `storage/app/attachments/` nei backup

3. **Pulizia periodica**: Crea un comando Artisan per eliminare allegati vecchi
   ```bash
   php artisan make:command CleanOldAttachments
   ```

4. **Limitazioni upload**: Se necessario, limita dimensione/tipo allegati nel Job

5. **Queue workers**: Assicurati che `queue:work` sia sempre attivo per processare i Job

## 📈 Performance

- **Batch import**: Scarica ~10-20 email/minuto (dipende dalla dimensione allegati)
- **Storage**: 1GB può contenere ~1000-5000 allegati (media 200KB-1MB ciascuno)
- **Database**: Ogni allegato = ~500 bytes di overhead DB

## 🔄 Aggiornamenti Futuri

Possibili miglioramenti da implementare:

- [ ] Antivirus scanning degli allegati
- [ ] Compressione automatica allegati vecchi
- [ ] Integrazione con cloud storage (S3, Google Drive)
- [ ] Preview allegati immagini nell'interfaccia web
- [ ] Ricerca full-text nei nomi file
- [ ] Export batch allegati filtrati

## 📞 Supporto

Per problemi o domande:
1. Controlla i log Laravel: `storage/logs/laravel.log`
2. Verifica stato queue: `php artisan queue:failed`
3. Testa manualmente: Visita `/gmail/attachments`

---

**Versione**: 1.0.0
**Data**: 13 Febbraio 2026
**Laravel**: 12.x
**Google API Client**: ^2.19
