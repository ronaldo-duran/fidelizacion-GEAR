<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demotion notices the nightly process registers and E7-01 sends. The
     * unique constraint makes a repeated notice collide in the database.
     */
    public function up(): void
    {
        Schema::create('avisos_descenso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->date('fecha_vencimiento');
            $table->unsignedSmallInteger('dias_antes');
            $table->integer('puntos_que_vencen');
            $table->string('nivel_actual', 10);
            $table->string('nivel_resultante', 10);
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['cliente_id', 'fecha_vencimiento', 'dias_antes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_descenso');
    }
};
