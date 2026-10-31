<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que guarda la entrega de un beneficio en caja (E5-02):
     *
     * - `referencia`: el intento de entrega. Su índice único hace que un
     *   doble toque devuelva la misma redención (regla dura 3.7, por
     *   analogía). La tabla está vacía en todos los ambientes: nadie la
     *   escribía antes de E5-02.
     * - `credencial_qr_id`: con qué código se redimió; nulo si fue por
     *   documento. No es único: SQL Server admite un solo NULL en un índice
     *   único, y el uso único ya lo da el código.
     * - `monto_cuenta_cop`: la base de un porcentaje, para explicar el valor
     *   otorgado; nulo en un monto fijo.
     * - `soporte`: la referencia del soporte, si el beneficio la exige.
     */
    public function up(): void
    {
        Schema::table('redenciones', function (Blueprint $table): void {
            $table->uuid('referencia')->unique();
            $table->foreignId('credencial_qr_id')->nullable()->index()->constrained('credenciales_qr');
            $table->unsignedBigInteger('monto_cuenta_cop')->nullable();
            $table->string('soporte', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('redenciones', function (Blueprint $table): void {
            $table->dropUnique(['referencia']);
            $table->dropConstrainedForeignId('credencial_qr_id');
            $table->dropColumn(['referencia', 'monto_cuenta_cop', 'soporte']);
        });
    }
};
