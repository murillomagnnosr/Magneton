<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contagem;
use App\Models\Zona;
use App\Services\ConsultaDeContagens;
use App\Services\Publicacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Quanto passou em cada região: de uma janela específica ou da média de um período.
 */
class ContagemController extends Controller
{
    public function __construct(
        private readonly ConsultaDeContagens $consulta,
        private readonly Publicacao $publicacao,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $dia = $this->consulta->diaOuMaisRecente($request->string('dia')->toString());
        $zonas = $this->consulta->zonas();

        if ($janela = $request->string('janela')->toString()) {
            return response()->json($this->deUmaJanela($dia, $janela, $zonas));
        }

        $periodo = $this->consulta->validarPeriodo($request->string('periodo', 'dia_inteiro')->toString());
        $porZona = $this->consulta->porZona($this->consulta->doPeriodo($dia, $periodo));

        return response()->json([
            'dia' => $dia,
            'periodo' => $periodo,
            'limiar' => $this->publicacao->limiar(),
            'zonas' => $zonas->map(
                fn ($zona) => $this->publicacao->periodo($zona, $porZona[$zona->id] ?? [])
            )->values(),
        ]);
    }

    /**
     * @param  Collection<string, Zona>  $zonas
     * @return array<string, mixed>
     */
    private function deUmaJanela(string $dia, string $janela, $zonas): array
    {
        $inicio = "{$dia} {$janela}:00";

        $achadas = Contagem::query()
            ->where('inicio_janela', $inicio)
            ->pluck('contagem', 'zona_id');

        return [
            'dia' => $dia,
            'janela' => $this->consulta->rotuloDaJanela(now()->parse($inicio)),
            'limiar' => $this->publicacao->limiar(),
            'zonas' => $zonas->map(
                fn ($zona) => $this->publicacao->janela($zona, $achadas[$zona->id] ?? null)
            )->values(),
        ];
    }
}
