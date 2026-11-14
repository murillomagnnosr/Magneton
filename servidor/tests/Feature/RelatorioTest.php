<?php

namespace Tests\Feature;

use App\Models\Contagem;
use App\Services\RelatorioDoPeriodo;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O relatório de um intervalo: diário, mensal ou personalizado, tudo na mesma conta.
 *
 * O que mais importa aqui é a regra do limiar, que muda com o tamanho do intervalo:
 * em intervalo curto uma janela abaixo de k tira o número da zona; a partir de k*10
 * janelas o arredondamento da média já esconde mais que k e a zona volta a ter número.
 */
class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'Z07';

    private const OUTRA = 'Z08';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    /**
     * Gera `$dias` dias com `$porDia` janelas, todas com `$pessoas` pessoas em cada zona.
     *
     * @param  array<string, int>  $excecao  ['zona_id' => contagem] na primeira janela
     */
    private function semear(Carbon $ate, int $dias, int $porDia, int $pessoas, array $excecao = []): void
    {
        $linhas = [];

        for ($recuo = $dias - 1; $recuo >= 0; $recuo--) {
            $dia = $ate->copy()->subDays($recuo)->startOfDay();

            for ($i = 0; $i < $porDia; $i++) {
                $quando = $dia->copy()->setTime(10, 0)->addMinutes(15 * $i);

                foreach ([self::ZONA, self::OUTRA] as $zona) {
                    $primeira = $recuo === $dias - 1 && $i === 0;

                    $linhas[] = [
                        'zona_id' => $zona,
                        'inicio_janela' => $quando->format('Y-m-d H:i:s'),
                        'duracao_min' => 15,
                        'contagem' => $primeira ? ($excecao[$zona] ?? $pessoas) : $pessoas,
                        'fonte' => 'simulado',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($linhas, 500) as $pedaco) {
            Contagem::query()->insert($pedaco);
        }
    }

    private function pedir(string $de, string $ate)
    {
        return $this->getJson(route('v1.relatorio', ['de' => $de, 'ate' => $ate]));
    }

    public function test_exige_as_duas_datas_e_a_ordem_certa(): void
    {
        $this->getJson(route('v1.relatorio'))->assertStatus(422);
        $this->pedir('2026-11-14', '2026-11-01')->assertStatus(422);
        $this->pedir('14/11/2026', '14/11/2026')->assertStatus(422);
    }

    public function test_recusa_intervalo_maior_que_o_teto(): void
    {
        $ate = now();
        $de = $ate->copy()->subDays(RelatorioDoPeriodo::DIAS_MAXIMO);

        $this->pedir($de->toDateString(), $ate->toDateString())->assertStatus(422);
    }

    public function test_intervalo_sem_contagem_devolve_404(): void
    {
        $this->pedir('2020-01-01', '2020-01-05')->assertNotFound();
    }

    public function test_relatorio_de_um_dia_traz_a_curva_e_nao_a_serie(): void
    {
        $hoje = now()->startOfDay();
        $this->semear($hoje, dias: 1, porDia: 8, pessoas: 20);

        $corpo = $this->pedir($hoje->toDateString(), $hoje->toDateString())->assertOk()->json();

        $this->assertSame(1, $corpo['periodo']['dias_com_dado']);
        $this->assertCount(1, $corpo['por_dia']);
        $this->assertCount(8, $corpo['por_hora']);
        // duas zonas com 20 pessoas cada em toda janela; o JSON devolve 40, não 40.0
        $this->assertEqualsWithDelta(40, $corpo['totais']['pessoas_media_por_janela'], 0.01);
        // sem dias suficientes, o recorte por dia da semana não diria nada
        $this->assertSame([], $corpo['por_dia_da_semana']);
    }

    public function test_em_intervalo_curto_uma_janela_abaixo_do_limiar_tira_o_numero_da_zona(): void
    {
        $hoje = now()->startOfDay();
        // 8 janelas no dia: longe das k*10 que liberariam a média
        $this->semear($hoje, dias: 1, porDia: 8, pessoas: 20, excecao: [self::ZONA => 2]);

        $corpo = $this->pedir($hoje->toDateString(), $hoje->toDateString())->assertOk()->json();

        $comLimiar = collect($corpo['por_zona'])->firstWhere('zona_id', self::ZONA);
        $semLimiar = collect($corpo['por_zona'])->firstWhere('zona_id', self::OUTRA);

        $this->assertSame('abaixo_do_limiar', $comLimiar['classe']);
        $this->assertArrayNotHasKey('pessoas_media', $comLimiar);
        $this->assertArrayHasKey('pessoas_media', $semLimiar);
    }

    public function test_em_intervalo_longo_a_mesma_zona_volta_a_ter_numero(): void
    {
        $hoje = now()->startOfDay();
        // 10 dias x 12 janelas = 120 janelas, acima das k*10 = 50
        $this->semear($hoje, dias: 10, porDia: 12, pessoas: 20, excecao: [self::ZONA => 2]);

        $corpo = $this->pedir($hoje->copy()->subDays(9)->toDateString(), $hoje->toDateString())
            ->assertOk()
            ->json();

        $zona = collect($corpo['por_zona'])->firstWhere('zona_id', self::ZONA);

        // o arredondamento da média sobre 120 janelas esconde mais que k
        $this->assertArrayHasKey('pessoas_media', $zona);
        $this->assertNotSame('abaixo_do_limiar', $zona['classe']);
    }

    public function test_compara_com_o_intervalo_anterior_do_mesmo_tamanho(): void
    {
        $hoje = now()->startOfDay();

        // 4 dias a 10 pessoas por zona, depois 4 dias a 20: o dobro
        $this->semear($hoje->copy()->subDays(4), dias: 4, porDia: 8, pessoas: 10);
        $this->semear($hoje, dias: 4, porDia: 8, pessoas: 20);

        $corpo = $this->pedir($hoje->copy()->subDays(3)->toDateString(), $hoje->toDateString())
            ->assertOk()
            ->json();

        $this->assertNotNull($corpo['comparacao']);
        $this->assertEqualsWithDelta(1, $corpo['comparacao']['variacao'], 0.001);
        $this->assertSame(4, $corpo['comparacao']['dias_com_dado']);
    }

    public function test_sem_intervalo_anterior_nao_inventa_comparacao(): void
    {
        $hoje = now()->startOfDay();
        $this->semear($hoje, dias: 2, porDia: 8, pessoas: 20);

        $corpo = $this->pedir($hoje->copy()->subDay()->toDateString(), $hoje->toDateString())
            ->assertOk()
            ->json();

        $this->assertNull($corpo['comparacao']);
    }

    public function test_acha_o_pico_e_o_vale_do_intervalo(): void
    {
        $hoje = now()->startOfDay();
        $this->semear($hoje, dias: 3, porDia: 8, pessoas: 20);

        Contagem::query()->where('zona_id', self::ZONA)
            ->orderBy('inicio_janela')
            ->limit(1)
            ->update(['contagem' => 900]);

        $corpo = $this->pedir($hoje->copy()->subDays(2)->toDateString(), $hoje->toDateString())
            ->assertOk()
            ->json();

        $this->assertSame(920, $corpo['totais']['pico']['pessoas_no_piso']);
        $this->assertSame(40, $corpo['totais']['vale']['pessoas_no_piso']);
    }

    public function test_recorte_por_dia_da_semana_so_com_duas_semanas(): void
    {
        $hoje = now()->startOfDay();
        $this->semear($hoje, dias: 14, porDia: 8, pessoas: 20);

        $corpo = $this->pedir($hoje->copy()->subDays(13)->toDateString(), $hoje->toDateString())
            ->assertOk()
            ->json();

        $this->assertCount(7, $corpo['por_dia_da_semana']);
    }
}
