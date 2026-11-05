<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro append-only de toda notificación de la pasarela (E6-01),
     * válida o no, con lo que se hizo con ella.
     */
    public function up(): void
    {
        Schema::create('notificaciones_pago', function (Blueprint $table): void {
            $table->id();
            $table->string('pasarela', 20);
            $table->string('referencia', 80)->nullable()->index();
            $table->string('id_externo', 80)->nullable();
            $table->string('estado_informado', 20)->nullable();
            $table->boolean('firma_valida');
            $table->string('resultado', 30);
            $table->jsonb('payload');
            $table->timestamp('created_at');

            $table->index('resultado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_pago');
    }
};
