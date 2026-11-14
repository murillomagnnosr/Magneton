<?php

namespace Database\Seeders;

use App\Models\Zona;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Carrega as regiões do piso a partir da planta.
 *
 * `database/dados/zonas.json` é gerado por `php artisan mapa:gerar-zonas` a partir da
 * planta real do shopping. Trocar a planta é mexer naquele comando e rodar este seeder
 * de novo.
 */
class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        $caminho = database_path('dados/zonas.json');

        if (! is_file($caminho)) {
            throw new RuntimeException("Arquivo de zonas não encontrado em {$caminho}.");
        }

        /** @var array{planta: array<string, mixed>, zonas: list<array<string, mixed>>} $arquivo */
        $arquivo = json_decode((string) file_get_contents($caminho), associative: true, flags: JSON_THROW_ON_ERROR);

        $this->conferirPlanta($arquivo['planta'] ?? []);

        $tiposAceitos = config('mapa.tipos_de_zona');
        $areaMinima = (int) config('mapa.area_min_m2');

        foreach ($arquivo['zonas'] as $zona) {
            $this->conferir($zona, $tiposAceitos, $areaMinima);

            Zona::query()->updateOrCreate(['id' => $zona['id']], [
                'nome' => $zona['nome'],
                'tipo' => $zona['tipo'],
                'piso' => $zona['piso'] ?? 1,
                'area_m2' => $zona['area_m2'],
                'centroide_x' => $zona['centroide'][0],
                'centroide_y' => $zona['centroide'][1],
                'vizinhos' => $zona['vizinhos'],
                'planta' => $zona['planta'],
            ]);
        }

        // Zona que saiu da planta não pode continuar no banco com contagem pendurada.
        $sumidas = Zona::query()->whereNotIn('id', array_column($arquivo['zonas'], 'id'))->pluck('id');

        if ($sumidas->isNotEmpty()) {
            $this->command?->warn('Zonas que não estão mais na planta: '.$sumidas->implode(', ')
                .'. Apague as contagens delas antes de removê-las.');
        }

        $this->command?->info(count($arquivo['zonas']).' zonas carregadas.');
    }

    /**
     * O arquivo e a configuração têm de falar da mesma planta, senão os polígonos saem
     * em um sistema de coordenadas e o painel desenha em outro.
     *
     * @param  array<string, mixed>  $planta
     */
    private function conferirPlanta(array $planta): void
    {
        foreach (['largura', 'altura'] as $medida) {
            $doArquivo = $planta[$medida] ?? null;
            $daConfig = config("mapa.planta.{$medida}");

            if ($doArquivo !== $daConfig) {
                throw new RuntimeException(
                    "A planta do zonas.json tem {$medida} {$doArquivo}, mas config/mapa.php diz {$daConfig}. "
                    .'Rode `php artisan mapa:gerar-zonas` depois de acertar os dois.'
                );
            }
        }
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

        if (count($zona['planta']['pontos'] ?? []) < 3) {
            throw new RuntimeException("Zona {$zona['id']}: o polígono precisa de ao menos 3 pontos.");
        }
    }
}
