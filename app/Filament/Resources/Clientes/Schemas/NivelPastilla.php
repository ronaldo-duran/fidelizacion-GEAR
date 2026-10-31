<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\Schemas;

use App\Enums\Nivel;
use Carbon\CarbonImmutable;
use Illuminate\Support\HtmlString;

/**
 * El nivel como pastilla con su color (tokens de nivel, sin hex sueltos) y,
 * para las fuentes con vigencia, la fecha y si está vencida (regla dura 3.5:
 * una fuente vencida se muestra, no se oculta).
 */
final class NivelPastilla
{
    public static function html(?Nivel $nivel, string $vacio = 'Sin nivel'): HtmlString
    {
        if ($nivel === null) {
            return new HtmlString(e($vacio));
        }

        $colores = match ($nivel) {
            Nivel::Bronce => 'bg-tier-bronce-bg text-tier-bronce-ink',
            Nivel::Plata => 'bg-tier-plata-bg text-tier-plata-ink',
            Nivel::Oro => 'bg-tier-oro-bg text-tier-oro-ink',
            Nivel::Diamante => 'bg-tier-diamante-bg text-tier-diamante-ink',
        };

        return new HtmlString(sprintf(
            '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-display text-xs font-semibold %s">'
            . '%s</span>',
            $colores,
            e($nivel->getLabel()),
        ));
    }

    public static function conVigencia(?Nivel $nivel, ?CarbonImmutable $hasta, string $vacio): HtmlString
    {
        if ($nivel === null || $hasta === null) {
            return new HtmlString(e($vacio));
        }

        $estado = $hasta->lt(CarbonImmutable::today())
            ? '<span class="font-semibold text-danger">Vencida</span>'
            : '<span class="text-ink-2">Vigente</span>';

        return new HtmlString(sprintf(
            '<span class="inline-flex flex-wrap items-center gap-2">%s<span class="text-ink-2">hasta el %s</span>'
            . '%s</span>',
            self::html($nivel)->toHtml(),
            e($hasta->format('Y-m-d')),
            $estado,
        ));
    }
}
