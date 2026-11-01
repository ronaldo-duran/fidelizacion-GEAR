<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que el grupo le habilita a cada comercio en promociones (E3-06): si
     * puede tener misiones y cuántos puntos como máximo da una misión. Sin
     * fila, el comercio no tiene misiones.
     */
    public function up(): void
    {
        Schema::create('comercio_promocion_limites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comercio_id')->unique()->constrained('comercios');
            $table->boolean('permite_misiones')->default(false);
            $table->unsignedInteger('puntos_max_por_mision')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercio_promocion_limites');
    }
};
