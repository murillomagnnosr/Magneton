<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PanoramaDaSemana;
use Illuminate\Http\JsonResponse;

/**
 * O padrão da semana: que dia move mais, a que horas, e qual zona manda em cada dia.
 *
 * Diferente das outras rotas, esta não aceita filtro: ela responde sobre o histórico
 * inteiro. É o que o painel mostra antes de a pessoa escolher qualquer coisa.
 */
class PanoramaController extends Controller
{
    public function __construct(private readonly PanoramaDaSemana $panorama) {}

    public function index(): JsonResponse
    {
        $resposta = $this->panorama->calcular();

        if ($resposta === null) {
            return response()->json(['message' => 'Ainda não há contagem gravada.'], 404);
        }

        return response()->json($resposta);
    }
}
