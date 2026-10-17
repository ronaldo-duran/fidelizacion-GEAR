<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The three level sources stay in separate columns and the effective
     * level is materialized next to them (hard rule 3.5). Levels are stored
     * as text (`bronce`, `plata`, `oro`, `diamante`).
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('nivel_por_puntos', 10)->default('bronce');
            $table->string('nivel_por_membresia', 10)->nullable();
            $table->date('membresia_vigente_hasta')->nullable();
            $table->string('nivel_por_evento', 10)->nullable();
            $table->date('evento_vigente_hasta')->nullable();
            $table->string('nivel_efectivo', 10)->default('bronce');
            $table->timestamp('nivel_efectivo_desde')->nullable();
            $table->date('proteccion_hasta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn([
                'nivel_por_puntos',
                'nivel_por_membresia',
                'membresia_vigente_hasta',
                'nivel_por_evento',
                'evento_vigente_hasta',
                'nivel_efectivo',
                'nivel_efectivo_desde',
                'proteccion_hasta',
            ]);
        });
    }
};
