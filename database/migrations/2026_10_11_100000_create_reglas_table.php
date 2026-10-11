<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each row is an immutable version of a rule (hard rule 3.2). The unique
     * constraint on (codigo, version) stops two concurrent saves from
     * creating the same version.
     */
    public function up(): void
    {
        Schema::create('reglas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 100);
            $table->string('tipo', 30);
            $table->unsignedInteger('version');
            $table->dateTime('vigente_desde');
            $table->dateTime('vigente_hasta')->nullable();
            $table->boolean('activa')->default(true);
            $table->jsonb('definicion');
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['codigo', 'version']);
            $table->index(['tipo', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas');
    }
};
