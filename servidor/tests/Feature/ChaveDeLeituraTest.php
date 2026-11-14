<?php

namespace Tests\Feature;

use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A chave que fecha a API de leitura quando ela está publicada na internet.
 *
 * Na rede local a chave fica vazia e tudo lê. Com o túnel aberto o endereço é público,
 * e aí só passa quem traz a chave — na prática, a ponte hospedada na Vercel.
 */
class ChaveDeLeituraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    public function test_sem_chave_configurada_a_leitura_fica_aberta(): void
    {
        $this->getJson('/api/v1/zonas')->assertOk();
    }

    public function test_com_chave_configurada_recusa_quem_nao_a_envia(): void
    {
        config(['mapa.chave_leitura' => 'chave-do-evento']);

        $this->getJson('/api/v1/zonas')->assertUnauthorized();
        $this->getJson('/api/v1/zonas', ['x-api-key' => 'outra'])->assertUnauthorized();
    }

    public function test_com_chave_configurada_aceita_quem_a_envia(): void
    {
        config(['mapa.chave_leitura' => 'chave-do-evento']);

        $this->getJson('/api/v1/zonas', ['x-api-key' => 'chave-do-evento'])->assertOk();
    }

    public function test_o_token_de_ingestao_tambem_abre_a_leitura(): void
    {
        config([
            'mapa.chave_leitura' => 'chave-do-evento',
            'mapa.token_ingestao' => 'token-do-worker',
        ]);

        // É assim que o worker continua lendo /api/v1/config com a credencial que já tem.
        $this->getJson('/api/v1/config', ['Authorization' => 'Bearer token-do-worker'])
            ->assertOk();
    }

    public function test_a_chave_nunca_aparece_na_configuracao_publicada(): void
    {
        config(['mapa.chave_leitura' => 'chave-do-evento']);

        $corpo = $this->getJson('/api/v1/config', ['x-api-key' => 'chave-do-evento'])
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('chave_leitura', $corpo);
        $this->assertArrayNotHasKey('token_ingestao', $corpo);
    }
}
