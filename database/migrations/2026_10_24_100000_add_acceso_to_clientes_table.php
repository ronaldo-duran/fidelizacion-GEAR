<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos del registro y del acceso del cliente (E8-02). El PIN se guarda
     * solo como hash de Laravel, nunca en claro. Las columnas aceptan nulos
     * porque hay clientes creados antes del registro (pruebas, panel).
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->string('nombre_completo', 120)->nullable();
            $table->string('pin_hash')->nullable();
            $table->timestamp('celular_verificado_at')->nullable();
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn(['nombre_completo', 'pin_hash', 'celular_verificado_at', 'remember_token']);
        });
    }
};
