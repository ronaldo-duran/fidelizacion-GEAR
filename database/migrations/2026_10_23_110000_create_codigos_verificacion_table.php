<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time SMS codes (E8-06). The code itself is never stored: only its
     * HMAC. One active code per phone and purpose; a new one invalidates the
     * previous.
     */
    public function up(): void
    {
        Schema::create('codigos_verificacion', function (Blueprint $table): void {
            $table->id();
            $table->string('proposito', 20);
            $table->string('celular', 10);
            $table->string('codigo_hash', 64);
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('vence_at');
            $table->timestamp('usado_at')->nullable();
            $table->timestamp('invalidado_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['celular', 'proposito']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_verificacion');
    }
};
