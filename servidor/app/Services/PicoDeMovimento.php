<?php

namespace App\Services;

use App\Models\Contagem;
use Illuminate\Support\Collection;

/**
 * A que horas passa mais gente, e em qual região.
 *
 * O total do piso soma as 16 zonas, então fica sempre muito acima do limiar e pode ser
 * publicado. A região líder só sai com número quando ela própria passa do limiar.
 *
 * Fica em serviço porque dois endpoints precisam dela: `/v1/pico` e `/v1/resumo`.
 */
class PicoDeMovimento
{
    public function __construct(
        private readonly ConsultaDeContagens $consulta,
        private readonly Publicacao $publicacao,
    ) {}

    /**
     * @param  Collection<int, Contagem>  $contagens
     * @return array{pico: array<string, mixed>, janelas_mais_cheias: list<array<string, mixed>>, janelas_analisadas: int}
     */
    public function calcular(Collection $contagens): array
    {
        $porJanela = $contagens->groupBy(
            fn (Contagem $contagem): string => $contagem->inicio_janela->format('Y-m-d H:i:s')
        );

        $totais = $porJanela
            ->map(fn (Collection $daJanela): int => (int) $daJanela->sum('contagem'))
            ->sortDesc();

        $maisCheia = (string) $totais->keys()->first();
        $lider = $porJanela->get($maisCheia)->sortByDesc('contagem')->first();

        return [
            'pico' => [
                'janela' => $this->rotulo($maisCheia),
                'pessoas_no_piso' => $totais->first(),
                'regiao_lider' => $this->publicacao->janela($lider->zona, $lider->contagem),
            ],
            'janelas_mais_cheias' => $totais->take(5)
                ->map(fn (int $total, string $janela): array => [
                    'janela' => $this->rotulo($janela),
                    'pessoas_no_piso' => $total,
                ])
                ->values()
                ->all(),
            'janelas_analisadas' => $totais->count(),
        ];
    }

    private function rotulo(string $janela): string
    {
        return $this->consulta->rotuloDaJanela(now()->parse($janela));
    }
}
