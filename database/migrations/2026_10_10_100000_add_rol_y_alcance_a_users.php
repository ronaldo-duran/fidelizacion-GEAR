<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('rol')->default(RolUsuario::Cajero->value)->after('email');
            $table->unsignedBigInteger('comercio_id')->nullable()->after('rol');
            $table->unsignedBigInteger('sede_id')->nullable()->after('comercio_id');
            $table->boolean('activo')->default(true)->after('sede_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['rol', 'comercio_id', 'sede_id', 'activo']);
        });
    }
};
