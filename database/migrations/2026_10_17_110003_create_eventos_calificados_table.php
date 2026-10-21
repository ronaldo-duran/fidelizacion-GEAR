<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qualifying events (property purchase, school enrollment). Rows are never
     * deleted: closing an event only brings `vigente_hasta` forward. There is
     * no `nivel_otorgado` column: the level comes from `config/niveles.php`,
     * because the school level depends on how many enrollments are valid.
     */
    public function up(): void
    {
        Schema::create('eventos_calificados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->string('tipo', 30);
            $table->date('vigente_desde');
            $table->date('vigente_hasta');
            $table->string('soporte_path');
            $table->string('motivo_cierre')->nullable();
            $table->foreignId('registrado_por')->constrained('users');
            $table->foreignId('cerrado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['cliente_id', 'vigente_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_calificados');
    }
};
