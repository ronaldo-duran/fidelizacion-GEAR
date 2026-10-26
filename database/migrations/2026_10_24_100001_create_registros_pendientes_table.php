<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registros que esperan la verificación del celular (E8-02). No reservan
     * el documento: el cliente se crea solo al verificar. Un intento nuevo con
     * el mismo documento reemplaza al anterior (índice único), y el proceso
     * nocturno purga los vencidos.
     */
    public function up(): void
    {
        Schema::create('registros_pendientes', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_documento', 5);
            $table->string('documento', 20);
            $table->string('nombre_completo', 120);
            $table->date('fecha_nacimiento');
            $table->string('celular', 10);
            $table->string('email')->nullable();
            $table->string('pin_hash');
            $table->string('autorizacion_version', 40);
            $table->string('ip', 45)->nullable();
            $table->string('canal', 10);
            $table->timestamp('vence_at');
            $table->timestamps();

            $table->unique(['tipo_documento', 'documento']);
            $table->index('vence_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_pendientes');
    }
};
