<?php

namespace Tests\Feature;

use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A API de transmissão nunca pode ser guardada em cache.
 *
 * O dado reflete o estado atual; uma resposta guardada mostra contagem velha sem avisar.
 * A tela ao vivo já ficou parada por isso, servindo do cache do navegador.
 */
class SemCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    public function test_transmissao_responde_no_store_e_sem_etag(): void
    {
        $resposta = $this->getJson('/api/v1/ao-vivo')->assertOk();

        $this->assertStringContainsString('no-store', $resposta->headers->get('Cache-Control'));
        $this->assertNull($resposta->headers->get('ETag'));
        $this->assertNull($resposta->headers->get('Last-Modified'));
    }

    public function test_vale_para_todas_as_rotas_de_transmissao(): void
    {
        foreach (['zonas', 'resumo', 'config', 'saude'] as $rota) {
            $cacheControl = $this->getJson("/api/v1/{$rota}")->headers->get('Cache-Control');

            $this->assertStringContainsString('no-store', (string) $cacheControl, "rota {$rota}");
        }
    }
}
