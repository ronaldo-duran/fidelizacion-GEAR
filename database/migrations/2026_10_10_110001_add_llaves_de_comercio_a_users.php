<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conecta las columnas de alcance que E2-01 dejó sin restricción, ahora
     * que existen las tablas comercios y sedes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('comercio_id')->references('id')->on('comercios')->noActionOnDelete();
            $table->foreign('sede_id')->references('id')->on('sedes')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['comercio_id']);
            $table->dropForeign(['sede_id']);
        });
    }
};
