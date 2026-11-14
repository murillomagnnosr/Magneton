<?php

namespace Tests\Feature;

use App\Models\Contagem;
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

    /**
     * Um almoço pequeno: Z07 enche às 12:30, Z13 fica constante e Z03 nunca passa de k.
     */
    private function semearAlmoco(): void
    {
        foreach (['12:00', '12:15', '12:30', '12:45'] as $hora) {
            $pico = $hora === '12:30';

            foreach ([['Z07', $pico ? 90 : 40], ['Z13', 30], ['Z03', 2]] as [$zona, $contagem]) {
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
            route('v1.zonas.curva', ['zona' => 'Z03', 'dia' => self::DIA]),
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
            ->firstWhere('zona_id', 'Z03');

        $this->assertSame('abaixo_do_limiar', $zona['classe']);
        $this->assertArrayNotHasKey('pessoas', $zona);
        $this->assertArrayNotHasKey('pessoas_media', $zona);
        $this->assertArrayNotHasKey('densidade_100m2', $zona);
    }

    public function test_curva_tem_lacuna_onde_houve_supressao(): void
    {
        $this->semearAlmoco();

        $curva = $this->getJson(route('v1.zonas.curva', ['zona' => 'Z03', 'dia' => self::DIA]))->assertOk();

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
            'zona_id' => 'Z13',
            'inicio_janela' => self::DIA.' 13:00:00',
            'duracao_min' => 15,
            'contagem' => 1,
            'fonte' => 'sensor',
        ]);

        $zona = collect($this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'periodo' => 'almoco']))
            ->json('zonas'))
            ->firstWhere('zona_id', 'Z13');

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
            ->assertJsonPath('pico.regiao_lider.nome', 'Praça de alimentação')
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

        $resumo->assertJsonPath('regiao_mais_movimentada.nome', 'Praça de alimentação')
            ->assertJsonPath('regiao_menos_movimentada.nome', 'Corredor Principal')
            ->assertJsonPath('pico.janela', '12:30–12:45')
            ->assertJsonPath('fonte', 'sensor')
            ->assertJsonPath('janelas_analisadas', 4);

        $comNumero = collect($resumo->json('ranking'))->filter(fn (array $z): bool => isset($z['densidade_100m2']));

        $this->assertSame(['Praça de alimentação', 'Corredor Principal'], $comNumero->pluck('nome')->all());
        $this->assertSame([0, 1], $comNumero->keys()->all(), 'Zonas com número devem vir antes das suprimidas.');
    }

    public function test_curva_da_zona_quente_traz_o_horario_de_pico(): void
    {
        $this->semearAlmoco();

        $this->getJson(route('v1.zonas.curva', ['zona' => 'Z07', 'dia' => self::DIA]))
            ->assertOk()
            ->assertJsonPath('pico.hora', '12:30')
            ->assertJsonPath('pico.pessoas', 90)
            // 90 pessoas em 600 m². O JSON não guarda a fração zero, então 15.0 chega como 15.
            ->assertJsonPath('pico.densidade_100m2', 15)
            ->assertJsonPath('janelas_suprimidas', 0);
    }

    public function test_densidade_compara_zonas_de_tamanhos_diferentes(): void
    {
        $this->semearAlmoco();

        $zonas = collect($this->getJson(route('v1.contagens.index', ['dia' => self::DIA, 'janela' => '12:00']))
            ->json('zonas'));

        // 40 pessoas em 600 m² é mais denso que 30 em 667 m²
        $this->assertSame(6.67, $zonas->firstWhere('zona_id', 'Z07')['densidade_100m2']);
        $this->assertSame(4.5, $zonas->firstWhere('zona_id', 'Z13')['densidade_100m2']);
        $this->assertSame('alta', $zonas->firstWhere('zona_id', 'Z07')['classe']);
        $this->assertSame('media', $zonas->firstWhere('zona_id', 'Z13')['classe']);
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

    public function test_lista_as_dezesseis_regioes_da_planta(): void
    {
        $zonas = $this->getJson(route('v1.zonas.index'))->assertOk()->json('data');

        $this->assertCount(16, $zonas);
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
