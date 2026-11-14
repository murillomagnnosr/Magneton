<?php

namespace Tests\Feature;

use App\Models\Contagem;
use App\Models\Zona;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A API de transmissão só devolve o que a camada de publicação autoriza (RNF02) e
 * responde às duas perguntas do painel.
 */
class TransmissaoTest extends TestCase
{
    use RefreshDatabase;

    private const DIA = '2026-11-07';

    private const K = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    /** A zona que enche no pico do almoço. */
    private const CHEIA = 'Z07';

    /** A zona de movimento constante, usada para comparar. */
    private const CONSTANTE = 'Z08';

    /** A zona que nunca passa do limiar. */
    private const VAZIA = 'Z05';

    /**
     * Um almoço pequeno: a zona cheia enche às 12:30, a constante não muda, e a vazia
     * nunca passa de k.
     */
    private function semearAlmoco(): void
    {
        foreach (['12:00', '12:15', '12:30', '12:45'] as $hora) {
            $pico = $hora === '12:30';

            foreach ([[self::CHEIA, $pico ? 90 : 40], [self::CONSTANTE, 30], [self::VAZIA, 2]] as [$zona, $contagem]) {
                Contagem::query()->create([
                    'zona_id' => $zona,
                    'inicio_janela' => self::DIA." {$hora}:00",
                    'duracao_min' => 15,
                    'contagem' => $contagem,
                    'fonte' => 'sensor',
                ]);
            }
        }
    }

    // ------------------------------------------------ RNF02: o limiar k

    public function test_contagem_abaixo_do_limiar_nunca_vira_numero(): void
    {
        $this->semearAlmoco();

        $rotas = [
            route('v1.contagens.index', ['dia' => self::DIA, 'periodo' => 'almoco']),
            route('v1.contagens.index', ['dia' => self::DIA, 'janela' => '12:30']),
            route('v1.resumo.index', ['dia' => self::DIA, 'periodo' => 'almoco']),
            route('v1.zonas.curva', ['zona' => self::VAZIA, 'dia' => self::DIA]),
        ];

        foreach ($rotas as $rota) {
            $corpo = $this->getJson($rota)->assertOk()->json();

            $this->assertNotContains(2, $this->numerosDe($corpo), "A contagem suprimida vazou em {$rota}.");
        }
    }

    public function test_zona_suprimida_sai_sem_campo_numerico(): void
    {
        $this->semearAlmoco();

        $zona = collect($this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'periodo' => 'almoco']))
            ->json('zonas'))
            ->firstWhere('zona_id', self::VAZIA);

        $this->assertSame('abaixo_do_limiar', $zona['classe']);
        $this->assertArrayNotHasKey('pessoas', $zona);
        $this->assertArrayNotHasKey('pessoas_media', $zona);
        $this->assertArrayNotHasKey('densidade_100m2', $zona);
    }

    public function test_curva_tem_lacuna_onde_houve_supressao(): void
    {
        $this->semearAlmoco();

        $curva = $this->getJson(route('v1.zonas.curva', ['zona' => self::VAZIA, 'dia' => self::DIA]))->assertOk();

        $curva->assertJsonPath('janelas_suprimidas', 4)->assertJsonPath('pico', null);

        foreach ($curva->json('pontos') as $ponto) {
            $this->assertNull($ponto['pessoas']);
            $this->assertSame('abaixo_do_limiar', $ponto['motivo']);
        }
    }

    public function test_periodo_com_uma_janela_suprimida_perde_o_numero(): void
    {
        $this->semearAlmoco();

        Contagem::query()->create([
            'zona_id' => self::CONSTANTE,
            'inicio_janela' => self::DIA.' 13:00:00',
            'duracao_min' => 15,
            'contagem' => 1,
            'fonte' => 'sensor',
        ]);

        $zona = collect($this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'periodo' => 'almoco']))
            ->json('zonas'))
            ->firstWhere('zona_id', self::CONSTANTE);

        $this->assertSame('abaixo_do_limiar', $zona['classe']);
        $this->assertArrayNotHasKey('pessoas_media', $zona);
    }

    public function test_zona_sem_registro_sai_como_sem_dado(): void
    {
        $this->semearAlmoco();

        $zona = collect($this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'janela' => '12:00']))
            ->json('zonas'))
            ->firstWhere('zona_id', 'Z01');

        $this->assertSame('sem_dado', $zona['classe']);
        $this->assertArrayNotHasKey('pessoas', $zona);
    }

    // ------------------------------------------------ as duas perguntas do painel

    public function test_pico_aponta_a_janela_e_a_regiao_lider(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.pico.index', ['dia' => self::DIA, 'periodo' => 'almoco']))
            ->assertOk()
            ->assertJsonPath('pico.janela', '12:30–12:45')
            ->assertJsonPath('pico.pessoas_no_piso', 122)
            ->assertJsonPath('pico.regiao_lider.zona_id', self::CHEIA)
            ->assertJsonPath('pico.regiao_lider.pessoas', 90);
    }

    public function test_janelas_mais_cheias_vem_em_ordem(): void
    {
        $this->semearAlmoco();

        $janelas = $this->getJson(route('v1.pico.index', ['dia' => self::DIA, 'periodo' => 'almoco']))
            ->json('janelas_mais_cheias');

        $this->assertSame('12:30–12:45', $janelas[0]['janela']);
        $this->assertSame(122, $janelas[0]['pessoas_no_piso']);
        $this->assertSame(72, $janelas[1]['pessoas_no_piso']);
    }

    public function test_resumo_responde_as_duas_perguntas(): void
    {
        $this->semearAlmoco();

        $resumo = $this->getJson(route('v1.resumo.index', ['dia' => self::DIA, 'periodo' => 'almoco']))->assertOk();

        $resumo->assertJsonPath('regiao_mais_movimentada.zona_id', self::CHEIA)
            ->assertJsonPath('regiao_menos_movimentada.zona_id', self::CONSTANTE)
            ->assertJsonPath('pico.janela', '12:30–12:45')
            ->assertJsonPath('fonte', 'sensor')
            ->assertJsonPath('janelas_analisadas', 4);

        $comNumero = collect($resumo->json('ranking'))->filter(fn (array $z): bool => isset($z['densidade_100m2']));

        $this->assertSame([self::CHEIA, self::CONSTANTE], $comNumero->pluck('zona_id')->all());
        $this->assertSame([0, 1], $comNumero->keys()->all(), 'Zonas com número devem vir antes das suprimidas.');
    }

    public function test_curva_da_zona_quente_traz_o_horario_de_pico(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.zonas.curva', ['zona' => self::CHEIA, 'dia' => self::DIA]))
            ->assertOk()
            ->assertJsonPath('pico.hora', '12:30')
            ->assertJsonPath('pico.pessoas', 90)
            ->assertJsonPath('pico.densidade_100m2', $this->densidade(self::CHEIA, 90))
            ->assertJsonPath('janelas_suprimidas', 0);
    }

    public function test_densidade_compara_zonas_de_tamanhos_diferentes(): void
    {
        $this->semearAlmoco();

        $zonas = collect($this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'janela' => '12:00']))
            ->json('zonas'));

        // a mesma contagem em zonas de áreas diferentes dá densidades diferentes
        $this->assertSame($this->densidade(self::CHEIA, 40), $zonas->firstWhere('zona_id', self::CHEIA)['densidade_100m2']);
        $this->assertSame($this->densidade(self::CONSTANTE, 30), $zonas->firstWhere('zona_id', self::CONSTANTE)['densidade_100m2']);
        $this->assertGreaterThan(
            $zonas->firstWhere('zona_id', self::CONSTANTE)['densidade_100m2'],
            $zonas->firstWhere('zona_id', self::CHEIA)['densidade_100m2'],
            'A zona cheia precisa ficar mais densa que a constante.'
        );
    }

    // ------------------------------------------------ a forma do dia e a régua do piso

    public function test_dia_devolve_o_total_do_piso_em_cada_janela(): void
    {
        $this->semearAlmoco();

        $resposta = $this->getJson(route('v1.dia.show', ['dia' => self::DIA]))->assertOk();

        $resposta->assertJsonPath('duracao_min', 15)
            ->assertJsonPath('total_no_dia', 4 * (40 + 30 + 2) + 50) // 12:30 tem 90 em vez de 40
            ->assertJsonPath('pico.janela', '12:30–12:45')
            ->assertJsonCount(4, 'janelas');

        $primeira = $resposta->json('janelas.0');
        $this->assertSame('12:00', $primeira['hora']);
        $this->assertSame('12:00–12:15', $primeira['rotulo']);
        $this->assertSame(72, $primeira['pessoas_no_piso']);
        $this->assertSame(3, $primeira['zonas_medidas']);
    }

    public function test_dia_soma_o_piso_mesmo_com_zona_suprimida(): void
    {
        $this->semearAlmoco();

        // A zona vazia tem 2 pessoas por janela: some do ranking, mas entra no total do piso
        $this->getJson(route('v1.dia.show', ['dia' => self::DIA]))
            ->assertOk()
            ->assertJsonPath('janelas.0.pessoas_no_piso', 40 + 30 + 2);
    }

    public function test_dia_sem_contagem_devolve_404(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.dia.show', ['dia' => '2020-01-01']))->assertStatus(404);
    }

    public function test_resumo_traz_a_densidade_media_do_piso(): void
    {
        $this->semearAlmoco();

        // só as zonas publicadas entram; a vazia fica de fora por estar abaixo do limiar.
        // A cheia no período: (40 + 40 + 90 + 40) / 4 = 52,5
        $media = $this->densidadeDoPiso([self::CHEIA => 52.5, self::CONSTANTE => 30]);

        $this->getJson(route('v1.resumo.index', ['dia' => self::DIA, 'periodo' => 'almoco']))
            ->assertOk()
            ->assertJsonPath('densidade_do_piso', $media);
    }

    public function test_janela_traz_a_densidade_media_do_piso(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'janela' => '12:00']))
            ->assertOk()
            ->assertJsonPath('densidade_do_piso', $this->densidadeDoPiso([self::CHEIA => 40, self::CONSTANTE => 30]));
    }

    // ------------------------------------------------ erros, metadados e segredos

    public function test_periodo_fora_da_lista_fechada_devolve_422(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.resumo.index', ['periodo' => 'madrugada']))->assertStatus(422);
    }

    public function test_dia_sem_dado_devolve_404(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.resumo.index', ['dia' => '2020-01-01']))->assertStatus(404);
    }

    public function test_sem_nenhuma_contagem_devolve_404(): void
    {
        $this->getJson(route('v1.resumo.index'))->assertStatus(404);
    }

    public function test_zona_inexistente_na_curva_devolve_404(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.zonas.curva', ['zona' => 'Z99']))->assertStatus(404);
    }

    public function test_lista_todas_as_regioes_da_planta(): void
    {
        $zonas = $this->getJson(route('v1.zonas.index'))->assertOk()->json('data');

        // o número de zonas vem da planta; o que importa é que a API devolva todas elas
        $this->assertCount(Zona::query()->count(), $zonas);
        $this->assertNotEmpty($zonas);
        $this->assertSame('Z01', $zonas[0]['id']);

        foreach ($zonas as $zona) {
            $this->assertGreaterThanOrEqual(config('mapa.area_min_m2'), $zona['area_m2']);
        }
    }

    public function test_config_nao_expoe_o_token_de_ingestao(): void
    {
        config(['mapa.token_ingestao' => 'segredo-da-demo']);

        $resposta = $this->getJson(route('v1.config'))->assertOk();

        $resposta->assertJsonMissingPath('token_ingestao');
        $this->assertStringNotContainsString('segredo-da-demo', $resposta->getContent());
        $resposta->assertJsonPath('k', self::K);
    }

    public function test_saude_informa_o_estado_do_conjunto(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.saude'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('registros', 12)
            ->assertJsonPath('fonte', 'sensor')
            ->assertJsonPath('limiar_k', self::K);
    }

    /** Densidade de uma zona, em pessoas por 100 m², tirada da área real da planta. */
    private function densidade(string $zonaId, float $pessoas): float
    {
        return Zona::findOrFail($zonaId)->densidade($pessoas);
    }

    /**
     * Densidade média do piso sobre as zonas publicadas.
     *
     * @param  array<string, float>  $pessoasPorZona
     */
    private function densidadeDoPiso(array $pessoasPorZona): float
    {
        $pessoas = array_sum($pessoasPorZona);
        $area = Zona::query()->findMany(array_keys($pessoasPorZona))->sum('area_m2');

        return round(100 * $pessoas / $area, 2);
    }

    /**
     * Todo número inteiro que aparece em qualquer lugar da resposta.
     *
     * @param  mixed  $no
     * @return list<int>
     */
    private function numerosDe($no): array
    {
        if (is_int($no)) {
            return [$no];
        }

        if (! is_array($no)) {
            return [];
        }

        return collect($no)->flatMap(fn ($filho): array => $this->numerosDe($filho))->all();
    }
}
