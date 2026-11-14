<?php

namespace Tests\Feature;

use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * O contrato de entrada é fechado (REQUISITOS.md, RNF01, RNF03 e RNF07).
 */
class IngestaoContagemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    public function test_grava_um_lote_valido(): void
    {
        $this->enviar($this->registro())
            ->assertCreated()
            ->assertExactJson(['recebidos' => 1, 'novos' => 1, 'atualizados' => 0]);

        $this->assertDatabaseHas('contagens', [
            'zona_id' => 'Z07',
            'contagem' => 212,
            'fonte' => 'simulado',
        ]);
    }

    public function test_reenviar_a_mesma_janela_atualiza_em_vez_de_duplicar(): void
    {
        $this->enviar($this->registro(['contagem' => 212]))->assertCreated();

        $this->enviar($this->registro(['contagem' => 180]))
            ->assertCreated()
            ->assertExactJson(['recebidos' => 1, 'novos' => 0, 'atualizados' => 1]);

        $this->assertDatabaseCount('contagens', 1);
        $this->assertDatabaseHas('contagens', ['zona_id' => 'Z07', 'contagem' => 180]);
    }

    public function test_grava_um_lote_grande_de_uma_vez(): void
    {
        $registros = [];

        foreach (range(0, 47) as $i) {
            $hora = 10 + intdiv($i, 4);
            $minuto = ($i % 4) * 15;
            $registros[] = $this->registro([
                'inicio_janela' => sprintf('2026-11-07T%02d:%02d', $hora, $minuto),
                'contagem' => 10 + $i,
            ]);
        }

        $this->enviar(...$registros)->assertCreated();
        $this->assertDatabaseCount('contagens', 48);
    }

    // ------------------------------------------------ RNF01: campos, tipos e domínios

    public function test_recusa_campo_fora_do_contrato(): void
    {
        $this->enviar($this->registro(['device_id' => 'AA:BB:CC']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.device_id');
    }

    public function test_recusa_campo_de_imagem(): void
    {
        $this->enviar($this->registro(['imagem' => 'data:image/png;base64,iVBOR']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.imagem');
    }

    public function test_recusa_campo_extra_no_corpo(): void
    {
        $this->postJson(route('ingestao.contagens.store'), [
            'registros' => [$this->registro()],
            'video_url' => 'rtsp://camera',
        ])->assertStatus(422)->assertJsonValidationErrors('video_url');
    }

    public function test_recusa_fonte_fora_da_enumeracao(): void
    {
        $this->enviar($this->registro(['fonte' => 'cam-AA:BB:CC']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.fonte');
    }

    public function test_recusa_contagem_fracionada(): void
    {
        $this->enviar($this->registro(['contagem' => 0.07]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.contagem');
    }

    public function test_recusa_contagem_negativa(): void
    {
        $this->enviar($this->registro(['contagem' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.contagem');
    }

    public function test_recusa_contagem_acima_do_teto_por_area(): void
    {
        // Z07 tem 600 m² e o teto é de 4 pessoas por m²
        $this->enviar($this->registro(['contagem' => 2401]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.contagem');

        $this->enviar($this->registro(['contagem' => 2400]))->assertCreated();
    }

    public function test_recusa_zona_inexistente(): void
    {
        $this->enviar($this->registro(['zona_id' => 'Z99']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.zona_id');
    }

    public function test_recusa_zona_fora_do_padrao(): void
    {
        $this->enviar($this->registro(['zona_id' => 'praca']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.zona_id');
    }

    // ------------------------------------------------ RNF03: a janela

    public function test_recusa_duracao_diferente_da_configurada(): void
    {
        $this->enviar($this->registro(['duracao_min' => 10]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.duracao_min');
    }

    public function test_recusa_janela_desalinhada(): void
    {
        $this->enviar($this->registro(['inicio_janela' => '2026-11-07T12:07']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.inicio_janela');
    }

    public function test_recusa_janela_com_segundos(): void
    {
        $this->enviar($this->registro(['inicio_janela' => '2026-11-07T12:00:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.inicio_janela');
    }

    public function test_recusa_janela_fora_do_horario_de_funcionamento(): void
    {
        $this->enviar($this->registro(['inicio_janela' => '2026-11-07T23:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.inicio_janela');

        $this->enviar($this->registro(['inicio_janela' => '2026-11-07T09:45']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros.0.inicio_janela');
    }

    public function test_recusa_par_zona_janela_repetido_no_lote(): void
    {
        $this->enviar($this->registro(['contagem' => 10]), $this->registro(['contagem' => 20]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros');
    }

    public function test_recusa_lote_vazio(): void
    {
        $this->postJson(route('ingestao.contagens.store'), ['registros' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registros');
    }

    // ------------------------------------------------ RNF07: uma fonte por conjunto

    public function test_recusa_fontes_misturadas_no_mesmo_lote(): void
    {
        $this->enviar(
            $this->registro(['fonte' => 'simulado']),
            $this->registro(['zona_id' => 'Z01', 'contagem' => 30, 'fonte' => 'sensor']),
        )->assertStatus(422)->assertJsonValidationErrors('registros');
    }

    public function test_recusa_fonte_diferente_da_ja_gravada(): void
    {
        $this->enviar($this->registro(['fonte' => 'simulado']))->assertCreated();

        $this->enviar($this->registro(['zona_id' => 'Z01', 'contagem' => 30, 'fonte' => 'sensor']))
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'simulado'));
    }

    // ------------------------------------------------ token e limpeza

    public function test_exige_token_quando_configurado(): void
    {
        config(['mapa.token_ingestao' => 'segredo-da-demo']);

        $this->enviar($this->registro())->assertStatus(401);

        $this->withToken('segredo-da-demo')
            ->postJson(route('ingestao.contagens.store'), ['registros' => [$this->registro()]])
            ->assertCreated();
    }

    public function test_apagar_exige_confirmacao(): void
    {
        $this->enviar($this->registro())->assertCreated();

        $this->deleteJson(route('ingestao.contagens.destroy'))->assertStatus(422);
        $this->assertDatabaseCount('contagens', 1);

        $this->deleteJson(route('ingestao.contagens.destroy', ['confirmar' => true]))->assertOk();
        $this->assertDatabaseCount('contagens', 0);
    }

    /**
     * Um registro válido, com os campos que o teste quiser trocar.
     *
     * @param  array<string, mixed>  $mudancas
     * @return array<string, mixed>
     */
    private function registro(array $mudancas = []): array
    {
        return [
            'zona_id' => 'Z07',
            'inicio_janela' => '2026-11-07T12:00',
            'duracao_min' => 15,
            'contagem' => 212,
            'fonte' => 'simulado',
            ...$mudancas,
        ];
    }

    /**
     * @param  array<string, mixed>  ...$registros
     */
    private function enviar(array ...$registros): TestResponse
    {
        return $this->postJson(route('ingestao.contagens.store'), ['registros' => $registros]);
    }
}
