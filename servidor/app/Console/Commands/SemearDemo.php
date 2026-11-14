<?php

namespace App\Console\Commands;

use App\Models\Contagem;
use App\Models\Zona;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Enche o banco com um dia de contagens plausíveis, para demonstrar a API de transmissão.
 *
 * Os dados saem marcados como `simulado`, nunca como `sensor` (RNF07). A praça de
 * alimentação é a zona quente e a Ala Norte é a ala fria, as duas plantadas de propósito:
 * servem para conferir se o ranking e o pico as reencontram sozinhos.
 */
class SemearDemo extends Command
{
    protected $signature = 'mapa:semear-demo
                            {--dia= : Dia a gerar, AAAA-MM-DD (padrão: hoje)}
                            {--semente=7 : Mesma semente, mesmos dados}
                            {--limpar : Apaga as contagens existentes antes de gerar}';

    protected $description = 'Gera um dia de contagens simuladas para demonstrar a API';

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
    private const ATRATIVIDADE = ['Z07' => 2.2, 'Z01' => 1.6, 'Z02' => 1.4, 'Z05' => 0.15];

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

        $dia = $this->option('dia') ?: now()->toDateString();
        mt_srand((int) $this->option('semente'));

        $linhas = $this->gerar($dia, $zonas);

        foreach (array_chunk($linhas, 500) as $pedaco) {
            Contagem::query()->upsert($pedaco, ['zona_id', 'inicio_janela'], ['contagem', 'fonte', 'updated_at']);
        }

        $this->info(count($linhas)." contagens geradas para {$dia}, em {$zonas->count()} zonas.");
        $this->line('Veja: '.route('v1.resumo.index', ['dia' => $dia, 'periodo' => 'almoco']));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Zona>  $zonas
     * @return list<array<string, mixed>>
     */
    private function gerar(string $dia, Collection $zonas): array
    {
        $duracao = (int) config('mapa.duracao_min');
        $abre = Carbon::parse("{$dia} ".config('mapa.horario_abre'));
        $fecha = Carbon::parse("{$dia} ".config('mapa.horario_fecha'));

        $linhas = [];

        for ($quando = $abre->copy(); $quando < $fecha; $quando->addMinutes($duracao)) {
            $fator = $this->curvaDoDia($quando->hour + $quando->minute / 60);

            foreach ($zonas as $zona) {
                $densidade = self::BASE[$zona->tipo] * (self::ATRATIVIDADE[$zona->id] ?? 1.0) * $fator;
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
     * Dois picos no dia: almoço por volta das 13h e noite por volta das 19h30.
     */
    private function curvaDoDia(float $hora): float
    {
        $almoco = 1.9 * exp(-(($hora - 13.0) ** 2) / 1.6);
        $noite = 1.6 * exp(-(($hora - 19.5) ** 2) / 2.4);

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
