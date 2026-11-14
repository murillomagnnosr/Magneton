<?php

namespace Tests\Feature;

use App\Models\Contagem;
use App\Models\Zona;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O padrão da semana, que o painel mostra no topo.
 *
 * O que se verifica aqui é se o padrão plantado é reencontrado: se as quintas forem o
 * dia cheio e a zona X subir às 14h nas segundas, a resposta tem de dizer isso sozinha.
 */
class PanoramaTest extends TestCase
{
    use RefreshDatabase;

    private const CHEIA = 'Z07';

    private const OUTRA = 'Z08';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    /**
     * Quatro semanas em que a quinta move o dobro, e a zona CHEIA dispara às 14h
     * nas segundas.
     */
    private function semearSemanas(): void
    {
        $linhas = [];

        for ($recuo = 27; $recuo >= 0; $recuo--) {
            $dia = now()->subDays($recuo)->startOfDay();
            $daSemana = $dia->dayOfWeek;

            for ($hora = 10; $hora < 22; $hora++) {
                $quando = $dia->copy()->setTime($hora, 0);
                $base = $daSemana === Carbon::THURSDAY ? 40 : 20;

                foreach ([self::CHEIA, self::OUTRA] as $zona) {
                    $pessoas = $base;

                    if ($zona === self::CHEIA && $daSemana === Carbon::MONDAY && $hora === 14) {
                        $pessoas = $base * 4;
                    }

                    $linhas[] = [
                        'zona_id' => $zona,
                        'inicio_janela' => $quando->format('Y-m-d H:i:s'),
                        'duracao_min' => 15,
                        'contagem' => $pessoas,
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

    public function test_sem_contagem_devolve_404(): void
    {
        $this->getJson(route('v1.panorama'))->assertNotFound();
    }

    public function test_acha_sozinho_o_dia_mais_cheio(): void
    {
        $this->semearSemanas();

        $corpo = $this->getJson(route('v1.panorama'))->assertOk()->json();

        $cheio = collect($corpo['destaques'])->firstWhere('tipo', 'dia_mais_cheio');

        $this->assertSame(Carbon::THURSDAY, $cheio['dia_semana']);
        $this->assertSame('quinta-feira', $cheio['nome']);
        $this->assertGreaterThan(1.0, $cheio['vezes_a_media']);
    }

    public function test_acha_sozinho_a_zona_que_sobe_na_segunda(): void
    {
        $this->semearSemanas();

        $corpo = $this->getJson(route('v1.panorama'))->assertOk()->json();

        $segunda = collect($corpo['semana'])->firstWhere('dia_semana', Carbon::MONDAY);

        $this->assertSame(self::CHEIA, $segunda['zona_destaque']['zona_id']);
        $this->assertSame('14:00', $segunda['zona_destaque']['hora']);
        $this->assertGreaterThan(1.5, $segunda['zona_destaque']['vezes_a_media']);
    }

    public function test_a_cobertura_diz_de_quantos_dias_e_de_que_fonte_fala(): void
    {
        $this->semearSemanas();

        $corpo = $this->getJson(route('v1.panorama'))->assertOk()->json();

        $this->assertSame(28, $corpo['cobertura']['dias']);
        $this->assertSame('simulado', $corpo['cobertura']['fonte']);
        $this->assertSame(now()->toDateString(), $corpo['cobertura']['ate']);
    }

    public function test_zona_abaixo_do_limiar_nao_vira_destaque(): void
    {
        $zona = Zona::query()->findOrFail(self::CHEIA);
        $linhas = [];

        for ($recuo = 27; $recuo >= 0; $recuo--) {
            $quando = now()->subDays($recuo)->startOfDay()->setTime(14, 0);
            $linhas[] = [
                'zona_id' => $zona->id,
                'inicio_janela' => $quando->format('Y-m-d H:i:s'),
                'duracao_min' => 15,
                // abaixo de k=5: existe no banco, mas não pode virar afirmação na tela
                'contagem' => 2,
                'fonte' => 'simulado',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Contagem::query()->insert($linhas);

        $corpo = $this->getJson(route('v1.panorama'))->assertOk()->json();

        foreach ($corpo['semana'] as $dia) {
            $this->assertNull($dia['zona_destaque']);
        }

        $this->assertNull(collect($corpo['destaques'])->firstWhere('tipo', 'zona_do_dia'));
    }

    public function test_a_faixa_cheia_cobre_o_pico(): void
    {
        $this->semearSemanas();

        $corpo = $this->getJson(route('v1.panorama'))->assertOk()->json();

        foreach ($corpo['semana'] as $dia) {
            $this->assertLessThanOrEqual($dia['pico']['hora'], $dia['faixa_cheia']['de']);
            $this->assertGreaterThanOrEqual($dia['pico']['hora'], $dia['faixa_cheia']['ate']);
        }
    }
}
