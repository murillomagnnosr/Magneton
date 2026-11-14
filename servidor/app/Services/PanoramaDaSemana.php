<?php

namespace App\Services;

use App\Models\Contagem;
use App\Models\Zona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * O que o histórico inteiro diz, em poucas frases.
 *
 * O resto da API responde sobre um dia: esta classe olha todos os dias juntos e procura
 * o padrão que se repete — que dia da semana move mais, a que horas, e qual zona manda
 * em cada dia. É o que o painel mostra no topo, antes de qualquer filtro.
 *
 * Nada aqui escapa do limiar. O total do piso soma todas as zonas e fica sempre muito
 * acima de k, então pode ser publicado; a afirmação sobre uma zona só sai quando aquela
 * zona passa de k em todas as ocorrências que entraram na conta, a mesma regra do
 * `Publicacao::periodo()` (REQUISITOS.md, RNF02).
 */
class PanoramaDaSemana
{
    /** Quantos dias para trás entram na conta. */
    private const DIAS = 120;

    private const NOMES = [
        0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira',
        4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado',
    ];

    public function __construct(private readonly Publicacao $publicacao) {}

    /**
     * @return array<string, mixed>|null null quando não há contagem nenhuma
     */
    public function calcular(): ?array
    {
        $desde = now()->subDays(self::DIAS)->toDateString();

        /** @var Collection<int, object{dia: string, hora: string, pessoas: int}> $porJanela */
        $porJanela = Contagem::query()
            ->selectRaw('substr(inicio_janela, 1, 10) AS dia')
            ->selectRaw('substr(inicio_janela, 12, 5) AS hora')
            ->selectRaw('SUM(contagem) AS pessoas')
            ->where('inicio_janela', '>=', $desde)
            ->groupBy('dia', 'hora')
            ->get();

        if ($porJanela->isEmpty()) {
            return null;
        }

        $dias = $porJanela->pluck('dia')->unique()->sort()->values();
        $semana = $this->porDiaDaSemana($porJanela);
        $zonas = $this->zonaDeCadaDia($desde);

        foreach ($semana as $indice => $dados) {
            $semana[$indice]['zona_destaque'] = $zonas[$dados['dia_semana']] ?? null;
        }

        return [
            'limiar' => $this->publicacao->limiar(),
            'cobertura' => [
                'dias' => $dias->count(),
                'de' => $dias->first(),
                'ate' => $dias->last(),
                'janelas' => $porJanela->count(),
                'fonte' => Contagem::query()->distinct()->limit(2)->pluck('fonte')->count() === 1
                    ? Contagem::query()->value('fonte')
                    : null,
            ],
            'semana' => array_values($semana),
            'destaques' => $this->destaques($semana, $porJanela),
        ];
    }

    /**
     * Média do piso por janela, em cada dia da semana, com a faixa de horário que
     * concentra o movimento.
     *
     * @param  Collection<int, object>  $porJanela
     * @return array<int, array<string, mixed>>
     */
    private function porDiaDaSemana(Collection $porJanela): array
    {
        $acumulado = [];

        foreach ($porJanela as $linha) {
            $semana = Carbon::parse($linha->dia)->dayOfWeek;
            $acumulado[$semana][$linha->hora][] = (int) $linha->pessoas;
        }

        ksort($acumulado);
        $saida = [];

        foreach ($acumulado as $semana => $porHora) {
            ksort($porHora);

            $medias = array_map(
                fn (array $valores): float => array_sum($valores) / count($valores),
                $porHora
            );

            $cheia = (string) array_search(max($medias), $medias, strict: true);

            $saida[] = [
                'dia_semana' => $semana,
                'nome' => self::NOMES[$semana],
                'dias_medidos' => count(reset($porHora)),
                'pessoas_media' => round(array_sum($medias) / count($medias), 1),
                'pico' => ['hora' => $cheia, 'pessoas_media' => round(max($medias), 1)],
                'faixa_cheia' => $this->faixaCheia($medias, $cheia),
            ];
        }

        return $saida;
    }

    /**
     * O trecho seguido de horas em que o movimento fica perto do pico do dia.
     *
     * Isso é o que uma pessoa quer ouvir: não "o pico é 16h15", mas "das 16h às 22h está
     * sempre cheio". Perto do pico quer dizer dois terços dele — com 80% a faixa fecha em
     * meia hora em volta do almoço e deixa de dizer alguma coisa.
     *
     * @param  array<string, float>  $medias  hora "HH:MM" => média de pessoas no piso
     * @return array<string, mixed>
     */
    private function faixaCheia(array $medias, string $horaDoPico): array
    {
        $horas = array_keys($medias);
        $corte = max($medias) * 0.65;
        $pico = array_search($horaDoPico, $horas, strict: true);

        $inicio = $pico;
        while ($inicio > 0 && $medias[$horas[$inicio - 1]] >= $corte) {
            $inicio--;
        }

        $fim = $pico;
        while ($fim < count($horas) - 1 && $medias[$horas[$fim + 1]] >= $corte) {
            $fim++;
        }

        $dentro = array_slice($medias, $inicio, $fim - $inicio + 1);

        return [
            'de' => $horas[$inicio],
            'ate' => $horas[$fim],
            'janelas' => count($dentro),
            'pessoas_media' => round(array_sum($dentro) / count($dentro), 1),
        ];
    }

    /**
     * A zona que mais se destaca em cada dia da semana, e a que horas.
     *
     * Destacar-se é estar acima da própria média: a zona é comparada com ela mesma, não
     * com as outras, senão a resposta seria sempre a maior zona do shopping.
     *
     * @return array<int, array<string, mixed>>
     */
    private function zonaDeCadaDia(string $desde): array
    {
        $zonas = Zona::query()->get()->keyBy('id');

        /** @var Collection<int, object> $linhas */
        $linhas = Contagem::query()
            ->selectRaw('zona_id')
            ->selectRaw('substr(inicio_janela, 1, 10) AS dia')
            ->selectRaw('substr(inicio_janela, 12, 5) AS hora')
            ->selectRaw('contagem')
            ->where('inicio_janela', '>=', $desde)
            ->get();

        $acumulado = [];

        foreach ($linhas as $linha) {
            $semana = Carbon::parse($linha->dia)->dayOfWeek;
            $acumulado[$linha->zona_id][$semana][$linha->hora][] = (int) $linha->contagem;
        }

        $melhorDoDia = [];

        foreach ($acumulado as $zonaId => $porSemana) {
            $zona = $zonas->get($zonaId);

            if ($zona === null) {
                continue;
            }

            $todas = [];
            foreach ($porSemana as $porHora) {
                foreach ($porHora as $valores) {
                    $todas = [...$todas, ...$valores];
                }
            }

            $mediaDaZona = array_sum($todas) / count($todas);

            if ($mediaDaZona <= 0) {
                continue;
            }

            foreach ($porSemana as $semana => $porHora) {
                foreach ($porHora as $hora => $valores) {
                    // a mesma regra do período: uma ocorrência abaixo de k tira a afirmação
                    if (min($valores) < $this->publicacao->limiar()) {
                        continue;
                    }

                    $media = array_sum($valores) / count($valores);
                    $vezes = $media / $mediaDaZona;

                    if (($melhorDoDia[$semana]['vezes_a_media'] ?? 0) >= $vezes) {
                        continue;
                    }

                    $melhorDoDia[$semana] = [
                        'zona_id' => $zona->id,
                        'nome' => $zona->nome,
                        'hora' => $hora,
                        'pessoas_media' => round($media, 1),
                        'densidade_100m2' => $zona->densidade($media),
                        'vezes_a_media' => round($vezes, 2),
                    ];
                }
            }
        }

        return $melhorDoDia;
    }

    /**
     * As frases do topo. Cada uma traz os números prontos; o texto é montado no painel.
     *
     * @param  array<int, array<string, mixed>>  $semana
     * @param  Collection<int, object>  $porJanela
     * @return list<array<string, mixed>>
     */
    private function destaques(array $semana, Collection $porJanela): array
    {
        $destaques = [];

        $cheio = collect($semana)->sortByDesc('pessoas_media')->first();
        $vazio = collect($semana)->sortBy('pessoas_media')->first();

        $destaques[] = [
            'tipo' => 'dia_mais_cheio',
            'dia_semana' => $cheio['dia_semana'],
            'nome' => $cheio['nome'],
            'faixa' => $cheio['faixa_cheia'],
            'pessoas_media' => $cheio['pessoas_media'],
            'vezes_a_media' => $this->vezes($cheio['pessoas_media'], $semana),
        ];

        if ($vazio['dia_semana'] !== $cheio['dia_semana']) {
            $destaques[] = [
                'tipo' => 'dia_mais_calmo',
                'dia_semana' => $vazio['dia_semana'],
                'nome' => $vazio['nome'],
                'pessoas_media' => $vazio['pessoas_media'],
                'vezes_a_media' => $this->vezes($vazio['pessoas_media'], $semana),
            ];
        }

        $recorde = $porJanela->sortByDesc('pessoas')->first();

        $destaques[] = [
            'tipo' => 'recorde',
            'dia' => $recorde->dia,
            'hora' => $recorde->hora,
            'nome' => self::NOMES[Carbon::parse($recorde->dia)->dayOfWeek],
            'pessoas' => (int) $recorde->pessoas,
        ];

        $comZona = collect($semana)->filter(fn (array $dia): bool => $dia['zona_destaque'] !== null);

        if ($comZona->isNotEmpty()) {
            $maisForte = $comZona->sortByDesc(fn (array $dia): float => $dia['zona_destaque']['vezes_a_media'])->first();

            $destaques[] = [
                'tipo' => 'zona_do_dia',
                'dia_semana' => $maisForte['dia_semana'],
                'nome' => $maisForte['nome'],
                'zona' => $maisForte['zona_destaque'],
            ];
        }

        return $destaques;
    }

    /**
     * @param  array<int, array<string, mixed>>  $semana
     */
    private function vezes(float $pessoas, array $semana): float
    {
        $media = collect($semana)->avg('pessoas_media');

        return $media > 0 ? round($pessoas / $media, 2) : 1.0;
    }
}
