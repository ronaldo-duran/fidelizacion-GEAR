<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periodos de membresía (E6-01), append-only: uno por pago aprobado. El
     * `pago_id` único impide que un webhook repetido o en paralelo cree dos
     * periodos (regla dura 3.7).
     */
    public function up(): void
    {
        Schema::create('membresias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('plan_id')->constrained('planes_membresia');
            $table->foreignId('pago_id')->unique()->constrained('pagos_membresia');
            $table->string('nivel', 10);
            $table->date('vigente_desde');
            $table->date('vigente_hasta');
            $table->timestamp('created_at');

            $table->index(['cliente_id', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membresias');
    }
};
