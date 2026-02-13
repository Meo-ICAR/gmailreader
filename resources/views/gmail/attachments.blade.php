<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allegati Gmail</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .stat-card {
            flex: 1;
            padding: 15px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .filename {
            font-weight: 500;
            color: #333;
        }
        .mime-type {
            display: inline-block;
            padding: 4px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        .size {
            color: #666;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1976D2;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-success {
            background: #4CAF50;
        }
        .btn-success:hover {
            background: #45a049;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .message-id {
            font-family: monospace;
            font-size: 12px;
            color: #888;
        }
        .date {
            color: #666;
            font-size: 13px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .nav-links {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <div>
                <h1>📎 Allegati Gmail</h1>
                <p style="color: #666; margin-top: 5px;">Tutti gli allegati scaricati dalle email</p>
            </div>
            <div class="nav-links">
                <a href="/" class="btn btn-small">🏠 Home</a>
                <a href="/gmail/start-batch" class="btn btn-small btn-success">🔄 Avvia Import</a>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Totale Allegati</div>
                <div class="stat-value">{{ $attachments->total() }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In questa pagina</div>
                <div class="stat-value">{{ $attachments->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Dimensione Totale</div>
                <div class="stat-value">
                    {{ number_format(\App\Models\Attachment::sum('size') / 1024 / 1024, 2) }} MB
                </div>
            </div>
        </div>

        @if($attachments->isEmpty())
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <p style="font-size: 48px;">📭</p>
                <p style="font-size: 18px; margin-top: 10px;">Nessun allegato trovato</p>
                <p style="margin-top: 10px;">Avvia l'importazione per scaricare gli allegati dalle email</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nome File</th>
                        <th>Tipo</th>
                        <th>Dimensione</th>
                        <th>Messaggio</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attachments as $attachment)
                        <tr>
                            <td>
                                <div class="filename">{{ $attachment->filename }}</div>
                                <div class="message-id" style="margin-top: 3px;">
                                    {{ Str::limit($attachment->safe_filename, 50) }}
                                </div>
                            </td>
                            <td>
                                <span class="mime-type">{{ $attachment->mime_type ?? 'unknown' }}</span>
                            </td>
                            <td class="size">{{ $attachment->readable_size }}</td>
                            <td>
                                <div class="message-id">{{ Str::limit($attachment->message_id, 20) }}</div>
                            </td>
                            <td class="date">{{ $attachment->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions">
                                    <a href="{{ route('gmail.attachment.download', $attachment->id) }}" 
                                       class="btn btn-small">
                                        ⬇️ Scarica
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $attachments->links() }}
            </div>
        @endif
    </div>
</body>
</html>
