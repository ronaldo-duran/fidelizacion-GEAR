<?php

declare(strict_types=1);

namespace App\Api;

use App\Beneficios\EntregaRechazada;
use App\Compras\CompraEnConflicto;
use App\Enums\ResultadoBeneficio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Todo error de `/api` en un solo formato (E9-01, regla 14):
 * `{"error": {"codigo", "mensaje", "campos"}}`. El código es estable y es lo
 * que lee el POS; el mensaje va en español de Colombia. Un error inesperado
 * nunca muestra la traza ni el SQL: se reporta al registro y responde un 500
 * genérico.
 */
final class RespuestaError
{
    public static function para(Throwable $error): JsonResponse
    {
        [$estado, $codigo, $mensaje, $campos] = self::partes($error);
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];

        return new JsonResponse([
            'error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'campos' => (object) $campos],
        ], $estado, $headers);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, list<string>>}
     */
    private static function partes(Throwable $error): array
    {
        return match (true) {
            $error instanceof ErrorApi => [$error->estado, $error->codigo, $error->getMessage(), $error->campos],
            $error instanceof ValidationException => [
                422,
                'datos_invalidos',
                'Revisa los datos enviados.',
                self::campos($error),
            ],
            $error instanceof AuthenticationException => [
                401,
                'no_autenticado',
                'Falta el token de la credencial o ya no sirve.',
                [],
            ],
            // Laravel convierte la AuthorizationException en AccessDeniedHttpException antes de renderizar.
            $error instanceof AuthorizationException, $error instanceof AccessDeniedHttpException => [
                403,
                'sin_permiso',
                'Esta credencial no tiene permiso para esta operación.',
                [],
            ],
            $error instanceof CompraEnConflicto => [
                409,
                'compra_en_conflicto',
                'Ya existe otra compra con esa referencia.',
                [],
            ],
            $error instanceof EntregaRechazada => [
                422,
                self::codigoEntrega($error->chequeo->resultado),
                $error->getMessage(),
                [],
            ],
            default => self::http($error),
        };
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, list<string>>}
     */
    private static function http(Throwable $error): array
    {
        return match (true) {
            $error instanceof ThrottleRequestsException => [
                429,
                'demasiadas_peticiones',
                'Hiciste demasiadas peticiones. Espera un momento.',
                [],
            ],
            $error instanceof MethodNotAllowedHttpException => [
                405,
                'metodo_no_permitido',
                'Esta ruta no acepta ese método.',
                [],
            ],
            $error instanceof NotFoundHttpException, $error instanceof ModelNotFoundException => [
                404,
                'ruta_no_encontrada',
                'Esta ruta no existe.',
                [],
            ],
            $error instanceof HttpExceptionInterface => [
                $error->getStatusCode(),
                'error_http',
                'No pudimos atender la petición.',
                [],
            ],
            default => [500, 'error_interno', 'Tuvimos un error inesperado. Intenta de nuevo en un momento.', []],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function campos(ValidationException $error): array
    {
        $campos = [];

        foreach ($error->errors() as $campo => $mensajes) {
            $campos[(string) $campo] = array_values(array_filter(
                is_array($mensajes) ? $mensajes : [],
                is_string(...),
            ));
        }

        return $campos;
    }

    private static function codigoEntrega(ResultadoBeneficio $resultado): string
    {
        return $resultado === ResultadoBeneficio::Usado ? 'ya_usado' : $resultado->value;
    }
}
