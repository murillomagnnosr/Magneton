<?php

namespace App\Http\Controllers\Api\Ingestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\GravarContagensRequest;
use App\Models\Contagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API de recebimento: é por aqui que a contagem entra.
 *
 * Quem envia é o worker de visão computacional, que já descartou o frame e manda apenas
 * quantas pessoas havia em cada região, em cada janela de 15 minutos.
 */
class ContagemController extends Controller
{
    /**
     * Grava um lote de contagens.
     *
     * Reenviar a mesma janela atualiza o valor, então o worker pode repetir o envio
     * sem duplicar nada.
     */
    public function store(GravarContagensRequest $request): JsonResponse
    {
        $registros = $request->validated()['registros'];
        $fonteDoLote = $registros[0]['fonte'];
        $fonteGravada = $this->fonteDoConjunto();

        if ($fonteGravada !== null && $fonteGravada !== $fonteDoLote) {
            return response()->json([
                'message' => "O conjunto já é \"{$fonteGravada}\": dado simulado não se mistura com "
                    .'dado de sensor (RNF07). Apague o conjunto antes de trocar de fonte.',
            ], 409);
        }

        $antes = Contagem::query()->count();

        DB::transaction(function () use ($registros): void {
            foreach (array_chunk($registros, 500) as $pedaco) {
                Contagem::query()->upsert(
                    array_map($this->paraLinha(...), $pedaco),
                    uniqueBy: ['zona_id', 'inicio_janela'],
                    update: ['contagem', 'fonte', 'updated_at'],
                );
            }
        });

        $novos = Contagem::query()->count() - $antes;

        return response()->json([
            'recebidos' => count($registros),
            'novos' => $novos,
            'atualizados' => count($registros) - $novos,
        ], 201);
    }

    /**
     * Apaga o conjunto inteiro. Serve para trocar de fonte ou começar a demo do zero.
     */
    public function destroy(Request $request): JsonResponse
    {
        if (! $request->boolean('confirmar')) {
            return response()->json(['message' => 'Repita com ?confirmar=true.'], 422);
        }

        $apagados = Contagem::query()->delete();

        return response()->json(['apagado' => true, 'registros' => $apagados]);
    }

    /**
     * A fonte já gravada, ou null se o banco está vazio (RNF07).
     */
    private function fonteDoConjunto(): ?string
    {
        $fontes = Contagem::query()->distinct()->limit(2)->pluck('fonte');

        return $fontes->count() === 1 ? $fontes->first() : null;
    }

    /**
     * @param  array<string, mixed>  $registro
     * @return array<string, mixed>
     */
    private function paraLinha(array $registro): array
    {
        return [
            'zona_id' => $registro['zona_id'],
            'inicio_janela' => str_replace('T', ' ', $registro['inicio_janela']).':00',
            'duracao_min' => $registro['duracao_min'],
            'contagem' => $registro['contagem'],
            'fonte' => $registro['fonte'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
