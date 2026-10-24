<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keeps a short code unique among the credentials still valid, enforced by
     * the database instead of a check in code. Each credential reserves its
     * code in every time window its validity touches; two credentials whose
     * validity overlaps share a window, so the second insert breaks the unique
     * constraint and the issue retries with another code. A plain composite
     * unique index, portable to every engine (no partial index). The rows go
     * away with their credential in the nightly purge.
     */
    public function up(): void
    {
        Schema::create('credenciales_qr_codigos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credencial_qr_id')->constrained('credenciales_qr')->cascadeOnDelete();
            $table->string('codigo_corto', 10);
            $table->unsignedBigInteger('ventana');

            $table->unique(['codigo_corto', 'ventana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciales_qr_codigos');
    }
};
