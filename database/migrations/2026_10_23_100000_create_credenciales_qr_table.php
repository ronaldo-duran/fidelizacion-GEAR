<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciales QR firmadas (regla dura 3.12). Cada una vive segundos; una
     * credencial de beneficio se usa una vez, marcada con un UPDATE
     * condicionado a `usado_at`. `beneficio_id` recibe su llave foránea con la
     * tabla de beneficios (E5-01). El proceso nocturno purga las vencidas.
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
