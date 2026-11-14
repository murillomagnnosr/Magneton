<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RelatorioDoPeriodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Relatório de um intervalo de datas.
 *
 * É a mesma rota para o relatório diário, o mensal e o personalizado: o que muda é o
 * intervalo. O painel só oferece atalhos para os intervalos mais usados.
 */
class RelatorioController extends Controller
{
    public function __construct(private readonly RelatorioDoPeriodo $relatorio) {}

    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'de' => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
        ], [
            'ate.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ]);

        $de = Carbon::parse($dados['de'])->startOfDay();
        $ate = Carbon::parse($dados['ate'])->startOfDay();

        if ($de->diffInDays($ate) + 1 > RelatorioDoPeriodo::DIAS_MAXIMO) {
            return response()->json([
                'message' => 'O intervalo passa de '.RelatorioDoPeriodo::DIAS_MAXIMO.' dias.',
            ], 422);
        }

        $resposta = $this->relatorio->calcular($de, $ate);

        if ($resposta === null) {
            return response()->json(['message' => 'Não há contagem neste intervalo.'], 404);
        }

        return response()->json($resposta);
    }
}
