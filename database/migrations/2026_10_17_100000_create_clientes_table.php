<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base customer table. `documento` is a unique index, never the primary
     * key (hard rule 3.10). Level columns come with E3-03 and registration
     * fields with E8-02.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('documento', 20)->unique();
            $table->string('celular', 20)->nullable();
            $table->string('email')->nullable();
            $table->integer('saldo_puntos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
