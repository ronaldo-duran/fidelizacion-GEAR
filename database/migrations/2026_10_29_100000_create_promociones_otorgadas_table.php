<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las promociones abonadas a cada cliente (E3-06), append-only. La
     * restricción única sobre (cliente_id, codigo, ocurrencia) es la que
     * impide abonar dos veces la misma promoción (regla dura 3.7): un abono
     * repetido o simultáneo choca en la base de datos. El código es el de la
     * regla, así que vale a través de sus versiones.
     */
    public function up(): void
    {
        Schema::create('promociones_otorgadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('codigo', 100);
            $table->unsignedInteger('ocurrencia');
            $table->foreignId('movimiento_id')->constrained('movimientos_puntos');
            $table->timestamp('created_at');

            $table->unique(['cliente_id', 'codigo', 'ocurrencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones_otorgadas');
    }
};
