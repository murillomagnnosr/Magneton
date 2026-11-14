<?php

namespace Tests\Feature;

use App\Models\AoVivo;
use App\Models\Zona;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * O estado ao vivo: o que as câmeras veem agora, para a tela de operação.
 *
 * Vale o mesmo contrato fechado da ingestão de contagens, e o mesmo limiar na saída.
 */
class AoVivoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    /** @param array<string, mixed> $mudancas */
    private function medida(array $mudancas = []): array
    {
        return [
            'zona_id' => 'Z07',
            'pessoas' => 12,
            'medido_em' => now()->format('Y-m-d\TH:i:s'),
            ...$mudancas,
        ];
    }

    private function enviar(array ...$zonas): TestResponse
    {
        return $this->postJson(route('ingestao.ao-vivo.store'), ['zonas' => $zonas]);
    }

    // ------------------------------------------------ ingestão

    public function test_grava_o_estado_atual(): void
    {
        $this->enviar($this->medida())->assertOk()->assertExactJson(['recebidas' => 1]);

        $this->assertDatabaseHas('ao_vivo', ['zona_id' => 'Z07', 'pessoas' => 12]);
    }

    public function test_novo_envio_sobrescreve_em_vez_de_acumular(): void
    {
        $this->enviar($this->medida(['pessoas' => 12]))->assertOk();
        $this->enviar($this->medida(['pessoas' => 3]))->assertOk();

        $this->assertDatabaseCount('ao_vivo', 1);
        $this->assertDatabaseHas('ao_vivo', ['zona_id' => 'Z07', 'pessoas' => 3]);
    }

    public function test_recusa_campo_fora_do_contrato(): void
    {
        $this->enviar($this->medida(['device_id' => 'AA:BB']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('zonas.0.device_id');
    }

    public function test_recusa_zona_inexistente_e_contagem_negativa(): void
    {
        $this->enviar($this->medida(['zona_id' => 'Z99']))->assertStatus(422);
        $this->enviar($this->medida(['pessoas' => -1]))->assertStatus(422);
    }

    public function test_recusa_contagem_acima_do_teto_da_zona(): void
    {
        $teto = Zona::findOrFail('Z07')->tetoDeContagem();

        $this->enviar($this->medida(['pessoas' => $teto + 1]))->assertStatus(422);
        $this->enviar($this->medida(['pessoas' => $teto]))->assertOk();
    }

    public function test_exige_token_quando_configurado(): void
    {
        config(['mapa.token_ingestao' => 'segredo-da-demo']);

        $this->enviar($this->medida())->assertStatus(401);

        $this->withToken('segredo-da-demo')
            ->postJson(route('ingestao.ao-vivo.store'), ['zonas' => [$this->medida()]])
            ->assertOk();
    }

    // ------------------------------------------------ leitura

    public function test_devolve_todas_as_zonas_marcando_quais_estao_medindo(): void
    {
        $this->enviar($this->medida())->assertOk();

        $resposta = $this->getJson(route('v1.ao-vivo.index'))->assertOk();

        $resposta->assertJsonPath('zonas_medindo', 1)
            ->assertJsonPath('pessoas_no_piso', 12)
            ->assertJsonCount(Zona::query()->count(), 'zonas');

        $zonas = collect($resposta->json('zonas'));
        $this->assertSame(12, $zonas->firstWhere('zona_id', 'Z07')['pessoas']);
        $this->assertFalse($zonas->firstWhere('zona_id', 'Z01')['medindo']);
        $this->assertSame('sem_dado', $zonas->firstWhere('zona_id', 'Z01')['classe']);
    }

    public function test_abaixo_do_limiar_mostra_que_mede_mas_nao_o_numero(): void
    {
        $this->enviar($this->medida(['pessoas' => 3]))->assertOk();

        $zona = collect($this->getJson(route('v1.ao-vivo.index'))->json('zonas'))
            ->firstWhere('zona_id', 'Z07');

        $this->assertTrue($zona['medindo'], 'A tela precisa mostrar que a câmera está viva.');
        $this->assertSame('abaixo_do_limiar', $zona['classe']);
        $this->assertArrayNotHasKey('pessoas', $zona);
        $this->assertArrayNotHasKey('densidade_100m2', $zona);
    }

    public function test_medicao_velha_deixa_de_contar_como_medindo(): void
    {
        $velha = now()->subSeconds((int) config('mapa.ao_vivo_valido_por_s') + 5);

        AoVivo::query()->create(['zona_id' => 'Z07', 'pessoas' => 12, 'medido_em' => $velha]);

        $resposta = $this->getJson(route('v1.ao-vivo.index'))->assertOk();

        $resposta->assertJsonPath('zonas_medindo', 0)->assertJsonPath('pessoas_no_piso', 0);
        $this->assertFalse(collect($resposta->json('zonas'))->firstWhere('zona_id', 'Z07')['medindo']);
    }

    public function test_limiar_ao_vivo_pode_ser_baixado_para_testar_a_camera(): void
    {
        config(['mapa.limiar_ao_vivo' => 1]);

        $this->enviar($this->medida(['pessoas' => 2]))->assertOk();

        $zona = collect($this->getJson(route('v1.ao-vivo.index'))->json('zonas'))
            ->firstWhere('zona_id', 'Z07');

        $this->assertSame(2, $zona['pessoas']);
    }

    public function test_sem_nenhuma_medicao_responde_vazio_sem_erro(): void
    {
        $this->getJson(route('v1.ao-vivo.index'))
            ->assertOk()
            ->assertJsonPath('zonas_medindo', 0)
            ->assertJsonPath('densidade_do_piso', null);
    }

    public function test_ao_vivo_nao_vira_historico(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 13:00:00'));

        foreach ([10, 20, 30] as $pessoas) {
            $this->enviar($this->medida(['pessoas' => $pessoas]))->assertOk();
        }

        // três envios, uma linha só: não há série temporal fina para reconstruir trajeto
        $this->assertDatabaseCount('ao_vivo', 1);
        $this->assertDatabaseCount('contagens', 0);
    }
}
