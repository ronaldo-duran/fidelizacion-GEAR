<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchases. The unique (comercio_id, referencia_externa) is what stops a
     * retried registration from granting points twice (hard rule 3.7): a
     * check in code would race. A purchase is never edited; only its state
     * moves forward once. Amounts are whole pesos (hard rule 3.9) and can be
     * large (property purchases).
     */
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->string('tipo_documento_cliente', 5);
            $table->string('documento_cliente', 20);
            $table->foreignId('comercio_id')->constrained('comercios');
            $table->foreignId('sede_id')->nullable()->constrained('sedes');
            $table->bigInteger('monto');
            $table->string('referencia_externa', 100);
            $table->string('idempotency_key', 100)->nullable();
            $table->string('canal', 20);
            $table->string('estado', 20);
            $table->timestamp('acreditada_at')->nullable();
            $table->foreignId('registrada_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['comercio_id', 'referencia_externa']);
            $table->index(['tipo_documento_cliente', 'documento_cliente', 'estado']);
            $table->index(['cliente_id', 'created_at']);
        });

        Schema::table('movimientos_puntos', function (Blueprint $table): void {
            $table->foreign('transaccion_id')->references('id')->on('transacciones');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_puntos', function (Blueprint $table): void {
            $table->dropForeign(['transaccion_id']);
        });

        Schema::dropIfExists('transacciones');
    }
};
