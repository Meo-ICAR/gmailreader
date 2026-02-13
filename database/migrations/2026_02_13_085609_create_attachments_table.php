<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->index(); // ID del messaggio Gmail
            $table->string('attachment_id')->index(); // ID univoco dell'allegato su Gmail
            $table->string('filename'); // Nome file originale
            $table->string('safe_filename'); // Nome file sanitizzato salvato su disco
            $table->string('mime_type')->nullable(); // Tipo MIME (image/jpeg, application/pdf, etc.)
            $table->integer('size')->nullable(); // Dimensione in bytes
            $table->string('storage_path')->nullable(); // Percorso relativo nel sistema storage
            $table->boolean('downloaded')->default(false); // Flag per sapere se è stato scaricato
            $table->timestamps();

            // Indici per ricerche veloci
            $table->index(['message_id', 'downloaded']);
            $table->unique(['message_id', 'attachment_id']); // Evita duplicati
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
