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
        Schema::create('email_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->index();  // ID Univoco di Google
            $table->string('email_address')->index();  // L'indirizzo estratto
            $table->string('domain')->index();  // Dominio dell'email
            $table->string('role');  // 'FROM', 'TO', 'CC', 'BODY_MATCH'
            $table->text('subject')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('label_name')->nullable();  // Da quale etichetta arriva
            $table->timestamps();

            // Indice per velocizzare le ricerche per email
            $table->index(['email_address', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_interactions');
    }
};
