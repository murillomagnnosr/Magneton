<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contagem;
use App\Services\ConsultaDeContagens;
use App\Services\PicoDeMovimento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * O dia inteiro em uma chamada: quantas pessoas havia no piso em cada janela.
 *
 * É o que o painel precisa para desenhar a forma do dia de uma vez, em vez de pedir
 * janela por janela. O total soma as 16 zonas, então está muito acima do limiar e pode
 * ser publicado mesmo quando uma zona isolada não poderia.
 */
class DiaController extends Controller
{
    public function __construct(
        private readonly ConsultaDeContagens $consulta,
        private readonly PicoDeMovimento $pico,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $dia = $this->consulta->diaOuMaisRecente($request->string('dia')->toString());

        $contagens = Contagem::query()
            ->with('zona')
            ->doDia($dia)
            ->orderBy('inicio_janela')
            ->get();

        if ($contagens->isEmpty()) {
            abort(404, "Sem contagem em {$dia}.");
        }

        $janelas = $contagens
            ->groupBy(fn (Contagem $contagem): string => $contagem->inicio_janela->format('H:i'))
            ->map(fn (Collection $daJanela, string $hora): array => [
                'hora' => $hora,
                'rotulo' => $this->consulta->rotuloDaJanela($daJanela->first()->inicio_janela),
                'pessoas_no_piso' => (int) $daJanela->sum('contagem'),
                'zonas_medidas' => $daJanela->count(),
            ])
            ->sortKeys()
            ->values();

        return response()->json([
            'dia' => $dia,
            'duracao_min' => (int) config('mapa.duracao_min'),
            'janelas' => $janelas,
            'pico' => $this->pico->calcular($contagens)['pico'],
            'total_no_dia' => (int) $contagens->sum('contagem'),
        ]);
    }
}
