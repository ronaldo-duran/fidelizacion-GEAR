<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history of effective level changes: every change keeps its
     * date, the previous and the new level and the reason.
     */
    public function up(): void
    {
        Schema::create('cambios_nivel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('nivel_anterior', 10);
            $table->string('nivel_nuevo', 10);
            $table->string('motivo', 30);
            $table->timestamp('created_at');

            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_nivel');
    }
};
