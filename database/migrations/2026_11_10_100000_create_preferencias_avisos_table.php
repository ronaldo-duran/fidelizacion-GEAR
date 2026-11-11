<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencias de avisos por correo del cliente (E8-05, regla 9). Guarda
     * la preferencia actual, no un historial: sin fila, el aviso está
     * encendido. Una fila por cliente y evento.
     */
    public function up(): void
    {
        Schema::create('preferencias_avisos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('evento', 40);
            $table->boolean('correo');
            $table->timestamp('updated_at');

            $table->unique(['cliente_id', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_avisos');
    }
};
