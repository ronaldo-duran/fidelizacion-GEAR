<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permiso para aprobar o rechazar cumplimientos de misiones de su propio
     * comercio (E3-06). No es un rol: puede tenerlo un cajero.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('aprueba_misiones')->default(false)->after('edita_reglas_json');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('aprueba_misiones');
        });
    }
};
