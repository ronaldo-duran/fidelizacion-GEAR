<?php

declare(strict_types=1);

namespace App\Notificaciones;

use App\Enums\CanalNotificacion;
use App\Enums\EstadoEnvio;
use App\Models\EnvioNotificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * La bandeja de avisos de la PWA (E7-01): los avisos en la app ya
 * enviados a un cliente, del más nuevo al más viejo, hasta el límite del
 * fixture. Una plantilla inactiva deja el aviso omitido y no aparece.
 */
final class AvisosDelCliente
{
    /**
     * @return Collection<int, EnvioNotificacion>
     */
    public function recientes(int $clienteId): Collection
    {
        $limite = config('notificaciones.limite_app');
        $query = $this->query($clienteId);
        $query->getQuery()->orderByDesc('id')->limit(is_int($limite) ? $limite : 30);

        return $query->get();
    }

    public function noLeidos(int $clienteId): int
    {
        $query = $this->query($clienteId);

        return $query->getQuery()->whereNull('leido_at')->count();
    }

    /**
     * @return Builder<EnvioNotificacion>
     */
    private function query(int $clienteId): Builder
    {
        $query = EnvioNotificacion::query();
        $query->getQuery()
            ->where('cliente_id', $clienteId)
            ->where('canal', CanalNotificacion::App->value)
            ->where('estado', EstadoEnvio::Enviado->value);

        return $query;
    }
}
