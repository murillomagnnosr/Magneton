<?php

namespace Tests\Feature;

use App\Models\Zona;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A segunda planta: a praça de alimentação desenhada na folha do pavimento dela.
 *
 * É um desenho a mais da mesma zona, não uma zona nova. O risco aqui é o desenho ficar
 * apontando para uma zona que saiu da planta — já aconteceu com seis zonas neste projeto —
 * e o painel passar a mostrar um mapa de nada.
 */
class PlantaDaPracaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ZonaSeeder::class);
    }

    public function test_a_planta_da_praca_aponta_para_uma_zona_que_existe(): void
    {
        $planta = config('mapa.planta_praca');

        $this->assertNotNull($planta, 'A segunda planta sumiu da configuração.');
        $this->assertTrue(
            Zona::query()->whereKey($planta['zona_id'])->exists(),
            "A planta da praça aponta para a zona {$planta['zona_id']}, que não está mais na planta."
        );
    }

    public function test_a_zona_da_segunda_planta_e_a_que_fica_em_outro_pavimento(): void
    {
        $zona = Zona::query()->findOrFail(config('mapa.planta_praca.zona_id'));

        // O desenho separado só se justifica porque esta zona não está no pavimento do
        // mapa principal: no térreo ela aparece tracejada, por cima de outro piso.
        $this->assertGreaterThan(1, $zona->piso);
    }

    public function test_o_poligono_cabe_dentro_da_imagem(): void
    {
        $planta = config('mapa.planta_praca');

        $this->assertGreaterThanOrEqual(3, count($planta['poligono']));

        foreach ($planta['poligono'] as [$x, $y]) {
            $this->assertGreaterThanOrEqual(0, $x);
            $this->assertGreaterThanOrEqual(0, $y);
            $this->assertLessThanOrEqual($planta['largura'], $x);
            $this->assertLessThanOrEqual($planta['altura'], $y);
        }
    }

    public function test_a_api_publica_a_segunda_planta(): void
    {
        $this->getJson(route('v1.config'))
            ->assertOk()
            ->assertJsonPath('planta_praca.zona_id', 'Z01')
            ->assertJsonPath('planta_praca.imagem', 'planta-piso2.jpg');
    }
}
