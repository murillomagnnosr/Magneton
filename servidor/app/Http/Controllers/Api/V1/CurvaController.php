<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contagem;
use App\Models\Zona;
use App\Services\ConsultaDeContagens;
use App\Services\Publicacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A curva do dia de uma região e o horário em que ela fica mais cheia.
 */
class CurvaController extends Controller
{
    public function __construct(
        private readonly ConsultaDeContagens $consulta,
        private readonly Publicacao $publicacao,
    ) {}

    public function show(Request $request, Zona $zona): JsonResponse
    {
        $dia = $this->consulta->diaOuMaisRecente($request->string('dia')->toString());

        $porJanela = Contagem::query()
            ->where('zona_id', $zona->id)
            ->doDia($dia)
            ->orderBy('inicio_janela')
            ->get()
            ->mapWithKeys(fn (Contagem $c): array => [$c->inicio_janela->format('H:i') => $c->contagem])
            ->all();

        if ($porJanela === []) {
            abort(404, "Sem contagem para {$zona->id} em {$dia}.");
        }

        return response()->json([
            'dia' => $dia,
            ...$this->publicacao->curva($zona, $porJanela),
        ]);
    }
}
