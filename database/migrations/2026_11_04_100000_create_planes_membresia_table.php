<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planes de membresía (E6-01). Un plan no se borra: se desactiva. Siembra
     * los planes iniciales de config/pagos.php (issue #1, decisión 8).
     */
    public function up(): void
    {
        Schema::create('planes_membresia', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 80);
            $table->string('nivel', 10);
            $table->unsignedSmallInteger('duracion_meses');
            $table->integer('precio_cop');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $planes = config('pagos.planes_iniciales');
        $ahora = now();

        foreach (is_array($planes) ? $planes : [] as $plan) {
            DB::table('planes_membresia')->insert([
                ...(is_array($plan) ? $plan : []),
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_membresia');
    }
};
