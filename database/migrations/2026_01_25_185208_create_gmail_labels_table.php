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
        Schema::create('gmail_labels', function (Blueprint $table) {
            $table->id();  // ID interno di Laravel
            $table->string('google_id')->unique();  // L'ID di Google (es. "Label_1", "INBOX")
            $table->string('name');  // Es. "PRIVACY/THUNDER"
            $table->string('dominio')->nullable();  // Es. "innova-tech.cloud"
            $table->string('type');  // Es. "user" o "system"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_labels');
    }
};
