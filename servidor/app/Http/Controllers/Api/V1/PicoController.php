<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ConsultaDeContagens;
use App\Services\PicoDeMovimento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A que horas passa mais gente, e em qual região.
 */
class PicoController extends Controller
{
    public function __construct(
        private readonly ConsultaDeContagens $consulta,
        private readonly PicoDeMovimento $pico,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $dia = $this->consulta->diaOuMaisRecente($request->string('dia')->toString());
        $periodo = $this->consulta->validarPeriodo($request->string('periodo', 'dia_inteiro')->toString());

        return response()->json([
            'dia' => $dia,
            'periodo' => $periodo,
            ...$this->pico->calcular($this->consulta->doPeriodo($dia, $periodo)),
        ]);
    }
}
