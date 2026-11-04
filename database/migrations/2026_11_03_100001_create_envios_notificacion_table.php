<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada aviso a un cliente por un canal (E7-01): el registro para soporte
     * y la bandeja de avisos de la PWA. La restricción única impide que un
     * reintento o un disparo repetido mande dos veces lo mismo; el evento
     * dice de qué tabla viene `origen_id`.
     */
    public function up(): void
    {
        Schema::create('envios_notificacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('evento', 40);
            $table->string('canal', 10);
            $table->unsignedBigInteger('origen_id');
            $table->string('destinatario')->nullable();
            $table->string('asunto');
            $table->text('cuerpo');
            $table->string('estado', 20);
            $table->string('motivo', 30)->nullable();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['evento', 'origen_id', 'canal']);
            $table->index(['cliente_id', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_notificacion');
    }
};
