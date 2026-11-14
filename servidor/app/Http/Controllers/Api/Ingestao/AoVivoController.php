<?php

namespace App\Http\Controllers\Api\Ingestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\GravarAoVivoRequest;
use App\Models\AoVivo;
use Illuminate\Http\JsonResponse;

/**
 * Recebe o estado atual das zonas, para a tela de operação.
 *
 * O worker chama aqui de poucos em poucos segundos. Cada envio sobrescreve a linha da
 * zona: não acumula série temporal fina, e o histórico continua sendo só as janelas de
 * 15 minutos em `contagens`.
 */
class AoVivoController extends Controller
{
    public function store(GravarAoVivoRequest $request): JsonResponse
    {
        $zonas = $request->validated()['zonas'];

        AoVivo::query()->upsert(
            array_map(fn (array $zona): array => [
                'zona_id' => $zona['zona_id'],
                'pessoas' => $zona['pessoas'],
                'medido_em' => str_replace('T', ' ', $zona['medido_em']),
            ], $zonas),
            uniqueBy: ['zona_id'],
            update: ['pessoas', 'medido_em'],
        );

        return response()->json(['recebidas' => count($zonas)]);
    }
}
