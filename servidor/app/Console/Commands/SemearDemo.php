<?php

namespace App\Console\Commands;

use App\Models\Contagem;
use App\Models\Zona;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Enche o banco com contagens plausíveis, para demonstrar a API de transmissão.
 *
 * Os dados saem marcados como `simulado`, nunca como `sensor` (RNF07). A praça de
 * eventos é a zona quente e o Mall Stilo é a ala fria, as duas plantadas de propósito:
 * servem para conferir se o ranking e o pico as reencontram sozinhos.
 *
 * Gera várias semanas por padrão, e não um dia só, porque o painel afirma coisas sobre
 * dia da semana ("quinta é o dia mais cheio"). Com um dia no banco essas frases não
 * teriam o que comparar, e a tela mostraria um padrão que o dado não sustenta.
 */
class SemearDemo extends Command
{
    protected $signature = 'mapa:semear-demo
                            {--dia= : Último dia a gerar, AAAA-MM-DD (padrão: hoje)}
                            {--dias=28 : Quantos dias gerar, terminando em --dia}
                            {--semente=7 : Mesma semente, mesmos dados}
                            {--limpar : Apaga as contagens existentes antes de gerar}';

    protected $description = 'Gera semanas de contagens simuladas para demonstrar a API';

    /**
     * Ocupação típica por 100 m², por tipo de zona.
     */
    private const BASE = [
        'entrada' => 2.0,
        'corredor' => 3.0,
        'ancora' => 3.5,
        'lojas' => 2.2,
        'alimentacao' => 4.0,
        'convivencia' => 3.0,
    ];

    /**
     * Praça cheia, praça de alimentação, corredor movimentado e corredor frio.
     */
    private const ATRATIVIDADE = ['Z07' => 2.2, 'Z01' => 1.6, 'Z02' => 1.4, 'Z17' => 0.6, 'Z05' => 0.15];

    /**
     * Quanto cada dia da semana move, em relação a um dia médio. Domingo é 0.
     */
    private const DIA_DA_SEMANA = [0 => 1.15, 1 => 0.62, 2 => 0.68, 3 => 0.74, 4 => 0.86, 5 => 1.12, 6 => 1.35];

    /**
     * Hora do pico da tarde/noite em cada dia da semana: no fim de semana o movimento
     * sobe mais cedo, no meio da semana ele só acontece depois do trabalho.
     */
    private const PICO_DA_NOITE = [0 => 17.0, 1 => 19.0, 2 => 19.0, 3 => 19.5, 4 => 20.0, 5 => 20.5, 6 => 16.5];

    /**
     * Zona que puxa movimento em cada dia da semana, e a que horas. Segunda é o dia do
     * Mall Nordeste às 14h; quinta é da Galeria Leste no fim da tarde.
     *
     * @var array<int, array{string, float}>
     */
    private const PREFERIDA_DO_DIA = [
        0 => ['Z01', 13.0], 1 => ['Z16', 14.0], 2 => ['Z08', 15.0], 3 => ['Z02', 18.0],
        4 => ['Z17', 17.0], 5 => ['Z06', 20.0], 6 => ['Z07', 16.0],
    ];

    public function handle(): int
    {
        $zonas = Zona::query()->orderBy('id')->get();

        if ($zonas->isEmpty()) {
            $this->error('Não há zonas. Rode: php artisan db:seed --class=ZonaSeeder');

            return self::FAILURE;
        }

        if ($this->option('limpar')) {
            Contagem::query()->delete();
            $this->warn('Contagens anteriores apagadas.');
        }

        if (Contagem::query()->where('fonte', 'sensor')->exists()) {
            $this->error('Já há dados de sensor gravados. Use --limpar para trocar de fonte (RNF07).');

            return self::FAILURE;
        }

        $ultimo = Carbon::parse($this->option('dia') ?: now()->toDateString());
        $quantos = max(1, (int) $this->option('dias'));
        mt_srand((int) $this->option('semente'));

        $geradas = 0;
        $barra = $this->output->createProgressBar($quantos);

        for ($i = $quantos - 1; $i >= 0; $i--) {
            $dia = $ultimo->copy()->subDays($i);
            $linhas = $this->gerar($dia, $zonas);

            foreach (array_chunk($linhas, 500) as $pedaco) {
                Contagem::query()->upsert($pedaco, ['zona_id', 'inicio_janela'], ['contagem', 'fonte', 'updated_at']);
            }

            $geradas += count($linhas);
            $barra->advance();
        }

        $barra->finish();
        $this->newLine();

        $this->info("{$geradas} contagens geradas em {$quantos} dia(s), até {$ultimo->toDateString()}, "
            ."em {$zonas->count()} zonas.");
        $this->line('Veja: '.route('v1.panorama'));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Zona>  $zonas
     * @return list<array<string, mixed>>
     */
    private function gerar(Carbon $data, Collection $zonas): array
    {
        $duracao = (int) config('mapa.duracao_min');
        $dia = $data->toDateString();
        $abre = Carbon::parse("{$dia} ".config('mapa.horario_abre'));
        $fecha = Carbon::parse("{$dia} ".config('mapa.horario_fecha'));

        $semana = $data->dayOfWeek;
        $doDia = self::DIA_DA_SEMANA[$semana];
        [$preferida, $horaPreferida] = self::PREFERIDA_DO_DIA[$semana];

        $linhas = [];

        for ($quando = $abre->copy(); $quando < $fecha; $quando->addMinutes($duracao)) {
            $hora = $quando->hour + $quando->minute / 60;
            $fator = $doDia * $this->curvaDoDia($hora, self::PICO_DA_NOITE[$semana]);

            foreach ($zonas as $zona) {
                $gosto = $zona->id === $preferida
                    ? 1 + 1.1 * exp(-(($hora - $horaPreferida) ** 2) / 2.0)
                    : 1.0;

                $densidade = self::BASE[$zona->tipo] * (self::ATRATIVIDADE[$zona->id] ?? 1.0) * $fator * $gosto;
                $esperado = $densidade * $zona->area_m2 / 100;

                $linhas[] = [
                    'zona_id' => $zona->id,
                    'inicio_janela' => $quando->format('Y-m-d H:i:s'),
                    'duracao_min' => $duracao,
                    'contagem' => max(0, (int) round($this->comRuido($esperado))),
                    'fonte' => 'simulado',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        return $linhas;
    }

    /**
     * Dois picos no dia: almoço por volta das 13h e um segundo pico que muda de hora
     * conforme o dia da semana.
     */
    private function curvaDoDia(float $hora, float $picoDaNoite): float
    {
        $almoco = 1.7 * exp(-(($hora - 13.0) ** 2) / 3.2);
        $noite = 1.8 * exp(-(($hora - $picoDaNoite) ** 2) / 5.0);

        return 0.35 + $almoco + $noite;
    }

    /**
     * Ruído proporcional, para a curva não sair perfeita demais.
     */
    private function comRuido(float $esperado): float
    {
        $desvio = max(1.0, $esperado * 0.18);

        return $esperado + ($desvio * (mt_rand(-100, 100) + mt_rand(-100, 100)) / 200);
    }
}
