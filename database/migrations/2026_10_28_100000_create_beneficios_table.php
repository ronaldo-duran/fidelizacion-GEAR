<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Beneficios de los comercios (E5-01): un valor por nivel (el nivel mínimo
     * es el más bajo con valor) y condiciones de uso cerradas, validadas contra
     * resources/schemas/beneficio-condiciones.json (regla dura 3.8). No cuestan
     * puntos (regla dura 3.1). Un beneficio con redenciones no se borra: se
     * desactiva.
     */
    public function up(): void
    {
        Schema::create('beneficios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->string('nombre', 160);
            $table->string('condiciones_texto', 500)->nullable();
            $table->string('tipo', 20);
            $table->jsonb('valores_por_nivel');
            $table->jsonb('condiciones');
            $table->string('max_por_cliente_ventana', 10)->nullable();
            $table->unsignedInteger('max_por_cliente_cantidad')->nullable();
            $table->unsignedInteger('cupo_global')->nullable();
            $table->boolean('requiere_soporte')->default(false);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'vigente_desde']);
            $table->index('comercio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficios');
    }
};
