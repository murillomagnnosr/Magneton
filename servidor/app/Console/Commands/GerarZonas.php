<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Gera `database/dados/zonas.json` a partir da planta fictícia da Entrega 1.
 *
 * A planta tem 800 x 480 unidades de desenho. A escala foi fixada para que a praça de
 * alimentação fique com os 600 m² do exemplo do contrato (REQUISITOS.md, seção 6):
 * 1 m² = 60 unidades², ou seja, 1 m = 7,746 unidades.
 *
 * Rodar só quando a planta mudar. Depois, `php artisan db:seed --class=ZonaSeeder`.
 */
class GerarZonas extends Command
{
    protected $signature = 'mapa:gerar-zonas';

    protected $description = 'Gera o arquivo de zonas a partir da planta do piso';

    private const UNIDADES_POR_M2 = 60.0;

    /**
     * id, nome, tipo, x, y, largura, altura — em unidades da planta.
     *
     * @var list<array{string, string, string, int, int, int, int}>
     */
    private const PLANTA = [
        ['Z01', 'Âncora', 'ancora', 0, 0, 260, 100],
        ['Z02', 'Lojas Norte', 'lojas', 260, 0, 270, 100],
        ['Z03', 'Lojas Ala Norte', 'lojas', 530, 0, 270, 100],
        ['Z04', 'Entrada Oeste', 'entrada', 0, 100, 110, 50],
        ['Z05', 'Corredor Norte', 'corredor', 110, 100, 580, 50],
        ['Z06', 'Entrada Leste', 'entrada', 690, 100, 110, 50],
        ['Z07', 'Praça de alimentação', 'alimentacao', 240, 150, 200, 180],
        ['Z08', 'Lojas Oeste', 'lojas', 0, 150, 180, 180],
        ['Z09', 'Corredor Oeste', 'corredor', 180, 150, 60, 180],
        ['Z10', 'Convivência', 'convivencia', 440, 150, 120, 180],
        ['Z11', 'Corredor Leste', 'corredor', 560, 150, 60, 180],
        ['Z12', 'Lojas Leste', 'lojas', 620, 150, 180, 180],
        ['Z13', 'Corredor Principal', 'corredor', 0, 330, 800, 50],
        ['Z14', 'Lojas Sul A', 'lojas', 0, 380, 300, 100],
        ['Z15', 'Entrada Principal', 'entrada', 300, 380, 200, 100],
        ['Z16', 'Lojas Sul B', 'lojas', 500, 380, 300, 100],
    ];

    public function handle(): int
    {
        $metrosPorUnidade = sqrt(1 / self::UNIDADES_POR_M2);
        $zonas = [];

        foreach (self::PLANTA as [$id, $nome, $tipo, $x, $y, $largura, $altura]) {
            $zonas[] = [
                'id' => $id,
                'nome' => $nome,
                'tipo' => $tipo,
                'area_m2' => (int) round($largura * $altura / self::UNIDADES_POR_M2),
                'centroide' => [
                    round(($x + $largura / 2) * $metrosPorUnidade, 1),
                    round(($y + $altura / 2) * $metrosPorUnidade, 1),
                ],
                'vizinhos' => $this->vizinhosDe($id),
                'planta' => ['x' => $x, 'y' => $y, 'largura' => $largura, 'altura' => $altura],
            ];
        }

        usort($zonas, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        if (($erro = $this->conferir($zonas)) !== null) {
            $this->error($erro);

            return self::FAILURE;
        }

        $caminho = database_path('dados/zonas.json');
        file_put_contents($caminho, json_encode($zonas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info(count($zonas).' zonas gravadas em '.$caminho);
        $this->table(
            ['id', 'nome', 'tipo', 'área (m²)', 'vizinhos'],
            array_map(fn (array $z): array => [
                $z['id'], $z['nome'], $z['tipo'], $z['area_m2'], implode(', ', $z['vizinhos']),
            ], $zonas),
        );

        return self::SUCCESS;
    }

    /**
     * Vizinhas são as zonas que dividem um trecho de borda, não apenas um canto.
     *
     * @return list<string>
     */
    private function vizinhosDe(string $id): array
    {
        $esta = collect(self::PLANTA)->firstWhere(0, $id);
        $vizinhos = [];

        foreach (self::PLANTA as $outra) {
            if ($outra[0] !== $id && $this->encostam($esta, $outra)) {
                $vizinhos[] = $outra[0];
            }
        }

        sort($vizinhos);

        return $vizinhos;
    }

    /**
     * @param  array{string, string, string, int, int, int, int}  $a
     * @param  array{string, string, string, int, int, int, int}  $b
     */
    private function encostam(array $a, array $b): bool
    {
        [, , , $ax, $ay, $al, $aa] = $a;
        [, , , $bx, $by, $bl, $ba] = $b;

        $tocaNaVertical = $ax + $al === $bx || $bx + $bl === $ax;
        $tocaNaHorizontal = $ay + $aa === $by || $by + $ba === $ay;
        $sobrepoeEmY = min($ay + $aa, $by + $ba) - max($ay, $by) > 0;
        $sobrepoeEmX = min($ax + $al, $bx + $bl) - max($ax, $bx) > 0;

        return ($tocaNaVertical && $sobrepoeEmY) || ($tocaNaHorizontal && $sobrepoeEmX);
    }

    /**
     * Área mínima e vizinhança simétrica (RNF04).
     *
     * @param  list<array<string, mixed>>  $zonas
     */
    private function conferir(array $zonas): ?string
    {
        $porId = collect($zonas)->keyBy('id');
        $areaMinima = (int) config('mapa.area_min_m2');

        foreach ($zonas as $zona) {
            if ($zona['area_m2'] < $areaMinima) {
                return "{$zona['id']} tem menos de {$areaMinima} m².";
            }

            foreach ($zona['vizinhos'] as $vizinho) {
                if (! $porId->has($vizinho)) {
                    return "{$zona['id']} aponta para a zona inexistente {$vizinho}.";
                }

                if (! in_array($zona['id'], $porId[$vizinho]['vizinhos'], strict: true)) {
                    return "A vizinhança {$zona['id']}–{$vizinho} não é simétrica.";
                }
            }
        }

        return null;
    }
}
