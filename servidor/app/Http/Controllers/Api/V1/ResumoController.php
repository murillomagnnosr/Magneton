<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contagem;
use App\Services\ConsultaDeContagens;
use App\Services\PicoDeMovimento;
use App\Services\Publicacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * As duas perguntas do painel em uma resposta só: quanto passou em cada região, e
 * quando foi o pico.
 */
class ResumoController extends Controller
{
    public function __construct(
        private readonly ConsultaDeContagens $consulta,
        private readonly Publicacao $publicacao,
        private readonly PicoDeMovimento $pico,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $dia = $this->consulta->diaOuMaisRecente($request->string('dia')->toString());
        $periodo = $this->consulta->validarPeriodo($request->string('periodo', 'dia_inteiro')->toString());
        $contagens = $this->consulta->doPeriodo($dia, $periodo);
        $porZona = $this->consulta->porZona($contagens);

        $ranking = $this->consulta->zonas()
            ->map(fn ($zona) => $this->publicacao->periodo($zona, $porZona[$zona->id] ?? []))
            // zonas sem número vão para o fim, para que a posição não revele o valor (RNF02)
            ->sortBy([
                fn (array $a, array $b) => ($b['densidade_100m2'] ?? -1) <=> ($a['densidade_100m2'] ?? -1),
                fn (array $a, array $b) => $a['nome'] <=> $b['nome'],
            ])
            ->values();

        $comNumero = $ranking->filter(fn (array $zona): bool => isset($zona['densidade_100m2']))->values();
        $pico = $this->pico->calcular($contagens);

        return response()->json([
            'dia' => $dia,
            'periodo' => $periodo,
            'limiar' => $this->publicacao->limiar(),
            'fonte' => $this->fonteDoConjunto(),
            'regiao_mais_movimentada' => $comNumero->first(),
            'regiao_menos_movimentada' => $comNumero->last(),
            'pico' => $pico['pico'],
            'ranking' => $ranking,
            'janelas_analisadas' => $pico['janelas_analisadas'],
        ]);
    }

    /**
     * A fonte do conjunto, ou null se houver mais de uma gravada (RNF07).
     */
    private function fonteDoConjunto(): ?string
    {
        $fontes = Contagem::query()->distinct()->limit(2)->pluck('fonte');

        return $fontes->count() === 1 ? $fontes->first() : null;
    }
}
