<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AoVivo;
use App\Models\Zona;
use App\Services\Publicacao;
use Illuminate\Http\JsonResponse;

/**
 * Quantas pessoas há agora em cada zona, para a tela de operação.
 *
 * É a visão instantânea, não a analítica: nada daqui é guardado como série. O limiar
 * continua valendo — uma zona com menos de k pessoas aparece como medindo, mas sem
 * número, do mesmo jeito que em qualquer outra tela (RNF02).
 */
class AoVivoController extends Controller
{
    public function __construct(private readonly Publicacao $publicacao) {}

    public function index(): JsonResponse
    {
        $limiar = (int) config('mapa.limiar_ao_vivo');
        $validoPor = (int) config('mapa.ao_vivo_valido_por_s');
        $agora = now();

        $medidas = AoVivo::query()->get()->keyBy('zona_id');
        $zonas = Zona::query()->orderBy('id')->get();

        $saida = $zonas->map(function (Zona $zona) use ($medidas, $limiar, $validoPor, $agora): array {
            $medida = $medidas->get($zona->id);
            $idade = $medida ? (int) round($agora->diffInSeconds($medida->medido_em, absolute: true)) : null;
            $medindo = $medida !== null && $idade <= $validoPor;

            if (! $medindo) {
                return ['zona_id' => $zona->id, 'nome' => $zona->nome, 'classe' => 'sem_dado', 'medindo' => false];
            }

            if ($medida->pessoas < $limiar) {
                return [
                    'zona_id' => $zona->id,
                    'nome' => $zona->nome,
                    'classe' => 'abaixo_do_limiar',
                    'limiar' => $limiar,
                    'medindo' => true,
                    'ha_segundos' => $idade,
                ];
            }

            $densidade = $zona->densidade($medida->pessoas);

            return [
                'zona_id' => $zona->id,
                'nome' => $zona->nome,
                'classe' => $this->publicacao->classe($densidade),
                'pessoas' => $medida->pessoas,
                'densidade_100m2' => $densidade,
                'medindo' => true,
                'ha_segundos' => $idade,
            ];
        });

        $medindo = $saida->where('medindo', true);

        return response()->json([
            'agora' => $agora->format('Y-m-d\TH:i:s'),
            'limiar' => $limiar,
            'valido_por_s' => $validoPor,
            'zonas_medindo' => $medindo->count(),
            // o total soma as zonas medidas, inclusive as que estão abaixo do limiar
            'pessoas_no_piso' => (int) $medidas
                ->filter(fn (AoVivo $m): bool => $agora->diffInSeconds($m->medido_em, absolute: true) <= $validoPor)
                ->sum('pessoas'),
            'densidade_do_piso' => $this->publicacao->densidadeDoPiso($saida->all(), $zonas->keyBy('id')),
            'zonas' => $saida->values(),
        ]);
    }
}
