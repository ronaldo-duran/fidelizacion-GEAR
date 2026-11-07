<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avisos de renovación de la membresía (E6-01, regla 10) que registra el
     * proceso nocturno y E7-01 envía. La restricción única hace que un aviso
     * repetido choque en la base de datos, como en `avisos_descenso`.
     */
    public function up(): void
    {
        Schema::create('avisos_renovacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->date('vigente_hasta');
            $table->unsignedSmallInteger('dias_antes');
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['cliente_id', 'vigente_hasta', 'dias_antes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_renovacion');
    }
};
