<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correos que no deben recibir mensajes (E7-01). La llena E7-02 con los
     * rebotes, las quejas y las bajas de mercadeo. El correo se guarda en
     * minúsculas: SQL Server compara sin distinguir mayúsculas (regla 3.13).
     */
    public function up(): void
    {
        Schema::create('supresiones_correo', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('motivo', 20);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supresiones_correo');
    }
};
