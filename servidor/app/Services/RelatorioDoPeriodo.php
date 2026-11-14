<?php

namespace App\Services;

use App\Models\Contagem;
use App\Models\Zona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * O relatório de um intervalo de datas: um dia, um mês ou o que a pessoa escolher.
 *
 * As outras rotas respondem sobre um dia ou sobre a semana típica. Esta responde sobre
 * um intervalo qualquer e é a mesma conta para todos eles — relatório diário é o
 * intervalo de um dia só, mensal é o de trinta. Não há três cálculos, há três atalhos
 * para o mesmo.
 *
 * Quando há período anterior do mesmo tamanho com dado, ele entra como comparação: é o
 * que transforma "253 pessoas por janela" em "12% acima do mês passado".
 *
 * ## Sobre o limiar
 *
 * O total do piso soma todas as zonas e fica sempre muito acima de k, então pode ser
 * publicado. A média de uma zona depende de quantas janelas entraram na conta:
 *
 * - Em intervalo curto vale a regra do `Publicacao::periodo()`: uma janela abaixo de k
 *   tira o número do intervalo inteiro, senão a diferença entre duas respostas revela a
 *   janela escondida.
 * - A partir de `k * 10` janelas a média é publicada mesmo com janelas abaixo de k. A
 *   média sai arredondada em uma casa, então o total que se deduz dela tem incerteza de
 *   `janelas * 0,1` pessoas; quando essa incerteza passa de k, nenhuma janela suprimida
 *   pode ser isolada por diferença. Com k = 5 isso vale a partir de 50 janelas, pouco
 *   mais de um dia de funcionamento.
 */
class RelatorioDoPeriodo
{
    /** Teto do intervalo aceito, para uma consulta não varrer o banco inteiro. */
    public const DIAS_MAXIMO = 366;

    private const NOMES = [
        0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira',
        4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado',
    ];

    public function __construct(private readonly Publicacao $publicacao) {}

    /**
     * @return array<string, mixed>|null null quando não há contagem no intervalo
     */
    public function calcular(Carbon $de, Carbon $ate): ?array
    {
        $janelas = $this->janelasDoPiso($de, $ate);

        if ($janelas->isEmpty()) {
            return null;
        }

        $dias = $janelas->pluck('dia')->unique()->sort()->values();
        $mediaPorJanela = $janelas->avg('pessoas');

        return [
            'limiar' => $this->publicacao->limiar(),
            'periodo' => [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'dias_no_intervalo' => $de->diffInDays($ate) + 1,
                'dias_com_dado' => $dias->count(),
                'primeiro_dia' => $dias->first(),
                'ultimo_dia' => $dias->last(),
                'janelas' => $janelas->count(),
                'fonte' => $this->fonte($de, $ate),
            ],
            'totais' => [
                'pessoas_media_por_janela' => round($mediaPorJanela, 1),
                'pico' => $this->extremo($janelas, maior: true),
                'vale' => $this->extremo($janelas, maior: false),
            ],
            'comparacao' => $this->comparar($de, $ate, $mediaPorJanela),
            'por_zona' => $this->porZona($de, $ate),
            'por_dia' => $this->porDia($janelas),
            'por_hora' => $this->porHora($janelas),
            'por_dia_da_semana' => $dias->count() >= 14 ? $this->porDiaDaSemana($janelas) : [],
        ];
    }

    /**
     * Total do piso em cada janela do intervalo.
     *
     * @return Collection<int, object{dia: string, hora: string, pessoas: int}>
     */
    private function janelasDoPiso(Carbon $de, Carbon $ate): Collection
    {
        return Contagem::query()
            ->selectRaw('substr(inicio_janela, 1, 10) AS dia')
            ->selectRaw('substr(inicio_janela, 12, 5) AS hora')
            ->selectRaw('SUM(contagem) AS pessoas')
            ->whereBetween('inicio_janela', [$de->toDateString(), $ate->copy()->endOfDay()])
            ->groupBy('dia', 'hora')
            ->orderBy('dia')
            ->orderBy('hora')
            ->get();
    }

    private function fonte(Carbon $de, Carbon $ate): ?string
    {
        $fontes = Contagem::query()
            ->whereBetween('inicio_janela', [$de->toDateString(), $ate->copy()->endOfDay()])
            ->distinct()
            ->limit(2)
            ->pluck('fonte');

        return $fontes->count() === 1 ? $fontes->first() : null;
    }

    /**
     * @param  Collection<int, object>  $janelas
     * @return array<string, mixed>
     */
    private function extremo(Collection $janelas, bool $maior): array
    {
        $alvo = $maior
            ? $janelas->sortByDesc('pessoas')->first()
            : $janelas->sortBy('pessoas')->first();

        return [
            'dia' => $alvo->dia,
            'hora' => $alvo->hora,
            'dia_da_semana' => self::NOMES[Carbon::parse($alvo->dia)->dayOfWeek],
            'pessoas_no_piso' => (int) $alvo->pessoas,
        ];
    }

    /**
     * O intervalo anterior, do mesmo tamanho. Sem dado nele, não há comparação.
     *
     * @return array<string, mixed>|null
     */
    private function comparar(Carbon $de, Carbon $ate, float $media): ?array
    {
        $tamanho = $de->diffInDays($ate) + 1;
        $anteriorAte = $de->copy()->subDay();
        $anteriorDe = $anteriorAte->copy()->subDays($tamanho - 1);

        $antes = $this->janelasDoPiso($anteriorDe, $anteriorAte);

        // poucas janelas não comparam nada: o número balança mais que o fenômeno
        if ($antes->count() < 4) {
            return null;
        }

        $mediaAntes = $antes->avg('pessoas');

        return [
            'de' => $anteriorDe->toDateString(),
            'ate' => $anteriorAte->toDateString(),
            'dias_com_dado' => $antes->pluck('dia')->unique()->count(),
            'pessoas_media_por_janela' => round($mediaAntes, 1),
            'variacao' => $mediaAntes > 0 ? round(($media - $mediaAntes) / $mediaAntes, 3) : null,
        ];
    }

    /**
     * Cada zona no intervalo: média por janela, densidade e fatia do piso.
     *
     * @return list<array<string, mixed>>
     */
    private function porZona(Carbon $de, Carbon $ate): array
    {
        $zonas = Zona::query()->orderBy('id')->get();

        /** @var Collection<string, Collection<int, Contagem>> $porZona */
        $porZona = Contagem::query()
            ->whereBetween('inicio_janela', [$de->toDateString(), $ate->copy()->endOfDay()])
            ->get(['zona_id', 'contagem'])
            ->groupBy('zona_id');

        $limiar = $this->publicacao->limiar();
        $publicadas = [];

        foreach ($zonas as $zona) {
            $contagens = $porZona->get($zona->id);

            if ($contagens === null || $contagens->isEmpty()) {
                $publicadas[] = [
                    'zona_id' => $zona->id,
                    'nome' => $zona->nome,
                    'area_m2' => $zona->area_m2,
                    'classe' => 'sem_dado',
                    'janelas' => 0,
                ];

                continue;
            }

            $valores = $contagens->pluck('contagem')->all();
            $quantas = count($valores);

            if (! $this->podePublicar($quantas, $valores)) {
                $publicadas[] = [
                    'zona_id' => $zona->id,
                    'nome' => $zona->nome,
                    'area_m2' => $zona->area_m2,
                    'classe' => 'abaixo_do_limiar',
                    'limiar' => $limiar,
                    'janelas' => $quantas,
                ];

                continue;
            }

            $media = array_sum($valores) / $quantas;
            $densidade = $zona->densidade($media);

            $publicadas[] = [
                'zona_id' => $zona->id,
                'nome' => $zona->nome,
                'area_m2' => $zona->area_m2,
                'classe' => $this->publicacao->classe($densidade),
                'pessoas_media' => round($media, 1),
                'densidade_100m2' => $densidade,
                'pico' => ['pessoas' => max($valores)],
                'janelas' => $quantas,
            ];
        }

        return $this->comParticipacao($publicadas);
    }

    /**
     * A média da zona pode sair?
     *
     * @param  list<int>  $valores
     */
    private function podePublicar(int $janelas, array $valores): bool
    {
        $limiar = $this->publicacao->limiar();

        // com janelas suficientes, o arredondamento da média esconde mais que k
        if ($janelas * 0.1 >= $limiar) {
            return true;
        }

        return min($valores) >= $limiar;
    }

    /**
     * Fatia de cada zona no total publicado, para a tabela responder "quanto do piso".
     *
     * @param  list<array<string, mixed>>  $zonas
     * @return list<array<string, mixed>>
     */
    private function comParticipacao(array $zonas): array
    {
        $total = array_sum(array_column($zonas, 'pessoas_media'));

        foreach ($zonas as $i => $zona) {
            if (isset($zona['pessoas_media']) && $total > 0) {
                $zonas[$i]['participacao'] = round($zona['pessoas_media'] / $total, 4);
            }
        }

        usort($zonas, fn (array $a, array $b): int => ($b['densidade_100m2'] ?? -1) <=> ($a['densidade_100m2'] ?? -1));

        return array_values($zonas);
    }

    /**
     * @param  Collection<int, object>  $janelas
     * @return list<array<string, mixed>>
     */
    private function porDia(Collection $janelas): array
    {
        return $janelas
            ->groupBy('dia')
            ->map(fn (Collection $doDia, string $dia): array => [
                'dia' => $dia,
                'dia_da_semana' => self::NOMES[Carbon::parse($dia)->dayOfWeek],
                'pessoas_media' => round($doDia->avg('pessoas'), 1),
                'pessoas_pico' => (int) $doDia->max('pessoas'),
                'janelas' => $doDia->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $janelas
     * @return list<array<string, mixed>>
     */
    private function porHora(Collection $janelas): array
    {
        return $janelas
            ->groupBy('hora')
            ->sortKeys()
            ->map(fn (Collection $daHora, string $hora): array => [
                'hora' => $hora,
                'pessoas_media' => round($daHora->avg('pessoas'), 1),
                'dias' => $daHora->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $janelas
     * @return list<array<string, mixed>>
     */
    private function porDiaDaSemana(Collection $janelas): array
    {
        return $janelas
            ->groupBy(fn (object $linha): int => Carbon::parse($linha->dia)->dayOfWeek)
            ->sortKeys()
            ->map(fn (Collection $doDia, int $semana): array => [
                'dia_semana' => $semana,
                'nome' => self::NOMES[$semana],
                'pessoas_media' => round($doDia->avg('pessoas'), 1),
                'dias' => $doDia->pluck('dia')->unique()->count(),
            ])
            ->values()
            ->all();
    }
}
