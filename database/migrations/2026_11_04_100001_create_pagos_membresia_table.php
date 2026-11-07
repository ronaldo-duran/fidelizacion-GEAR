<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagos de membresía (E6-01). La referencia la genera el servidor y es
     * única (regla dura 3.7). El pago guarda una copia del plan al iniciarse,
     * así un cambio de precio solo afecta a los pagos nuevos.
     */
    public function up(): void
    {
        Schema::create('pagos_membresia', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('plan_id')->constrained('planes_membresia');
            $table->string('referencia', 40)->unique();
            $table->string('pasarela', 20);
            $table->string('id_externo', 80)->nullable();
            $table->string('estado', 10)->default('pendiente');
            $table->string('nivel', 10);
            $table->unsignedSmallInteger('duracion_meses');
            $table->integer('monto_cop');
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();

            // Sin único: un pago pendiente no tiene id externo, y SQL Server solo admite un NULL por
            // índice único (regla 3.13). ApplyNotificacionPago revisa que el id no sea de otro pago, pero
            // es defensa en profundidad, no la garantía: esa revisión puede perder una carrera entre dos
            // pagos distintos. Lo que impide un doble periodo es el pago bloqueado (lockForUpdate) y el
            // único de membresias.pago_id.
            $table->index(['pasarela', 'id_externo']);
            $table->index(['cliente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_membresia');
    }
};
