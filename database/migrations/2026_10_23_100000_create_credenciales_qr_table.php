<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signed QR credentials (hard rule 3.12). Each one lives for seconds; a
     * benefit credential is used once, marked with a conditional UPDATE on
     * `usado_at`. `beneficio_id` gets its foreign key with the benefits table
     * (E5-01). Expired rows are purged by the nightly process.
     */
    public function up(): void
    {
        Schema::create('credenciales_qr', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('proposito', 20);
            $table->unsignedBigInteger('beneficio_id')->nullable();
            $table->foreignId('comercio_id')->nullable()->constrained('comercios');
            $table->string('nonce', 64)->unique();
            $table->string('codigo_corto', 10);
            $table->timestamp('emitido_at');
            $table->timestamp('vence_at');
            $table->timestamp('usado_at')->nullable();
            $table->foreignId('usado_por')->nullable()->constrained('users');
            $table->timestamp('created_at');

            $table->index(['codigo_corto', 'vence_at']);
            $table->index('vence_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciales_qr');
    }
};
