<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cajeros\FindCliente;
use App\Api\ErrorApi;
use App\Beneficios\BeneficiosParaCliente;
use App\Http\Requests\Api\V1\ConsultarClienteRequest;
use App\Http\Resources\Api\V1\BeneficioClienteJson;
use App\Http\Resources\Api\V1\ClienteJson;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Consultas por documento (E9-01, operaciones 1 y 2). Cada una cuenta contra
 * el tope de consultas de la credencial, para que nadie recorra documentos.
 */
class ClientesController extends ApiController
{
    /**
     * @throws ValidationException
     */
    public function show(ConsultarClienteRequest $request, FindCliente $findCliente): JsonResponse
    {
        return self::datos(ClienteJson::datos($this->cliente($request, $findCliente)));
    }

    /**
     * @throws ValidationException
     */
    public function beneficios(
        ConsultarClienteRequest $request,
        FindCliente $findCliente,
        BeneficiosParaCliente $beneficios,
    ): JsonResponse {
        $cliente = $this->cliente($request, $findCliente);

        return self::datos(array_map(
            BeneficioClienteJson::datos(...),
            $beneficios->delComercio($cliente, (int) $request->credencial()->comercio_id),
        ));
    }

    /**
     * @throws ValidationException
     */
    private function cliente(ConsultarClienteRequest $request, FindCliente $findCliente): Cliente
    {
        $tipo = $request->tipoDocumento();
        $cliente = $tipo === null ? null : $findCliente($request->credencial(), $tipo, (string) $request->documento());

        return $cliente ?? throw ErrorApi::clienteNoRegistrado();
    }
}
