<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mantiene el código corto único entre las credenciales vigentes, impuesto
     * por la base de datos y no por una verificación en código. Cada credencial
     * reserva su código en cada ventana de tiempo que toca su vigencia; dos
     * credenciales cuyas vigencias se cruzan comparten una ventana, así el
     * segundo insert rompe la restricción única y la emisión reintenta con otro
     * código. Un índice único compuesto normal, portable a todos los motores
     * (sin índice parcial). Las filas se van con su credencial en la purga
     * nocturna.
     */
    public function up(): void
    {
        Schema::create('credenciales_qr_codigos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credencial_qr_id')->constrained('credenciales_qr')->cascadeOnDelete();
            $table->string('codigo_corto', 10);
            $table->unsignedBigInteger('ventana');

            $table->unique(['codigo_corto', 'ventana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciales_qr_codigos');
    }
};
