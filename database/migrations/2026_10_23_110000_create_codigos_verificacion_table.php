<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Códigos de un solo uso por SMS (E8-06). El código nunca se guarda: solo
     * su HMAC. Un código vigente por celular y propósito; uno nuevo invalida el
     * anterior.
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
