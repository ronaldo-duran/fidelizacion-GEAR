<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('comercios', function (Blueprint $table): void {
            $table->id();
            $table->string('razon_social');
            $table->string('nit')->unique();
            $table->foreignId('categoria_id')->constrained('categorias')->noActionOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('sedes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('direccion');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
        Schema::dropIfExists('comercios');
        Schema::dropIfExists('categorias');
    }
};
