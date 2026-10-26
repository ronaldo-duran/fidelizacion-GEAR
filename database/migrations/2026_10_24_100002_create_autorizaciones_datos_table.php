<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autorizaciones de tratamiento de datos (Ley 1581), append-only: cada
     * fila es la prueba de qué versión del texto aceptó el cliente, cuándo,
     * desde qué IP y por qué canal. Nunca se actualiza ni se borra.
     */
    public function up(): void
    {
        Schema::create('autorizaciones_datos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('version_texto', 40);
            $table->timestamp('aceptada_at');
            $table->string('ip', 45)->nullable();
            $table->string('canal', 10);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizaciones_datos');
    }
};
