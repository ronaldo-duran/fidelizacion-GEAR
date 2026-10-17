<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only points ledger (hard rule 3.4). `transaccion_id` has no
     * foreign key yet: `transacciones` belongs to E4-01, which adds it.
     */
    public function up(): void
    {
        Schema::create('movimientos_puntos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('comercio_id')->nullable()->constrained('comercios');
            $table->unsignedBigInteger('transaccion_id')->nullable();
            $table->string('tipo', 20);
            $table->integer('puntos');
            $table->foreignId('regla_id')->nullable()->constrained('reglas');
            $table->unsignedInteger('regla_version')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->string('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['cliente_id', 'created_at']);
            $table->index('transaccion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_puntos');
    }
};
