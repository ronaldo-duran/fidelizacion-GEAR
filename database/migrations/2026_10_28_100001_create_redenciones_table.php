<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redenciones de beneficios, append-only. Cada una guarda el valor en
     * pesos que se entregó (regla dura 3.11). E5-01 solo la crea y la lee
     * (usos por ventana y cupo global); la escribe la Action de E5-02.
     */
    public function up(): void
    {
        Schema::create('redenciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('beneficio_id')->constrained('beneficios');
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->foreignId('sede_id')->nullable()->constrained('sedes');
            $table->unsignedBigInteger('valor_otorgado_cop');
            $table->foreignId('validado_por')->constrained('users');
            $table->timestamp('created_at');

            $table->index(['beneficio_id', 'cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redenciones');
    }
};
