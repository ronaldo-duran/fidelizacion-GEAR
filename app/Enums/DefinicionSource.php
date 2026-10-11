<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a rule definition comes from. The advanced JSON editor requires a
 * separate permission (E3-01, business rule 6).
 */
enum DefinicionSource: string
{
    case Form = 'form';
    case JsonEditor = 'json_editor';
}
