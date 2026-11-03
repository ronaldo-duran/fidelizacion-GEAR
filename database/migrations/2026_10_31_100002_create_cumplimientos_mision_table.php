<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que envía un cliente para cumplir una misión (E3-06). Guarda la
     * versión de la misión vigente al enviarlo: con esa se abona aunque la
     * misión cambie antes de la aprobación (regla dura 3.3). El estado solo
     * pasa una vez de pendiente a aprobado o rechazado.
     */
    public function up(): void
    {
        Schema::create('cumplimientos_mision', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('regla_id')->constrained('reglas');
            $table->unsignedInteger('regla_version');
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->string('soporte_path')->nullable();
            $table->string('estado', 20);
            $table->string('motivo_rechazo')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users');
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();

            $table->index(['comercio_id', 'estado']);
            $table->index(['cliente_id', 'regla_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cumplimientos_mision');
    }
};
