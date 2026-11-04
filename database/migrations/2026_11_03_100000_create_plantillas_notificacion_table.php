<?php

declare(strict_types=1);

use App\Enums\EventoNotificacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una plantilla por evento (E7-01), con los textos iniciales de
     * `config/notificaciones.php`. El administrador las edita en el panel;
     * el envío guarda el texto ya armado.
     */
    public function up(): void
    {
        Schema::create('plantillas_notificacion', function (Blueprint $table): void {
            $table->id();
            $table->string('evento', 40)->unique();
            $table->string('asunto');
            $table->text('cuerpo');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        $now = CarbonImmutable::now();

        DB::table('plantillas_notificacion')->insert(array_map(
            static fn (EventoNotificacion $evento): array => [
                'evento' => $evento->value,
                'asunto' => $evento->asuntoInicial(),
                'cuerpo' => $evento->cuerpoInicial(),
                'activa' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            EventoNotificacion::cases(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_notificacion');
    }
};
