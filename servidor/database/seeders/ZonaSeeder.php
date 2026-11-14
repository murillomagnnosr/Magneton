<?php

namespace Database\Seeders;

use App\Models\Zona;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Carrega as regiões do piso a partir da planta.
 *
 * O arquivo `database/dados/zonas.json` é gerado da planta da Entrega 1. Trocar a planta
 * é trocar esse arquivo e rodar o seeder de novo.
 */
class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        $caminho = database_path('dados/zonas.json');

        if (! is_file($caminho)) {
            throw new RuntimeException("Arquivo de zonas não encontrado em {$caminho}.");
        }

        /** @var list<array<string, mixed>> $zonas */
        $zonas = json_decode((string) file_get_contents($caminho), associative: true, flags: JSON_THROW_ON_ERROR);

        $tiposAceitos = config('mapa.tipos_de_zona');
        $areaMinima = (int) config('mapa.area_min_m2');

        foreach ($zonas as $zona) {
            $this->conferir($zona, $tiposAceitos, $areaMinima);

            Zona::query()->updateOrCreate(['id' => $zona['id']], [
                'nome' => $zona['nome'],
                'tipo' => $zona['tipo'],
                'area_m2' => $zona['area_m2'],
                'centroide_x' => $zona['centroide'][0],
                'centroide_y' => $zona['centroide'][1],
                'vizinhos' => $zona['vizinhos'],
                'planta' => $zona['planta'],
            ]);
        }

        $this->command?->info(count($zonas).' zonas carregadas.');
    }

    /**
     * Só entram zonas de circulação pública, com área suficiente (RNF04).
     *
     * @param  array<string, mixed>  $zona
     * @param  list<string>  $tiposAceitos
     */
    private function conferir(array $zona, array $tiposAceitos, int $areaMinima): void
    {
        if (! in_array($zona['tipo'], $tiposAceitos, strict: true)) {
            throw new RuntimeException("Zona {$zona['id']}: tipo \"{$zona['tipo']}\" não é aceito.");
        }

        if ($zona['area_m2'] < $areaMinima) {
            throw new RuntimeException("Zona {$zona['id']}: área menor que {$areaMinima} m².");
        }
    }
}
