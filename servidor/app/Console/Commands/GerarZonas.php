<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Gera `database/dados/zonas.json` a partir da planta real do Capim Dourado Shopping.
 *
 * Os corredores não foram inventados: vieram de uma planta marcada à mão pelo grupo,
 * traçando onde a circulação realmente passa. `ferramentas/converter_zonas.py` leva
 * aquelas marcações para o sistema de coordenadas usado aqui, que é o do recorte
 * `dashboard/public/planta-piso1.jpg` (2740 x 1820 pixels).
 *
 * Só entram áreas de circulação pública (RNF04). Loja nenhuma é zona.
 *
 * A praça de alimentação fica no pavimento de cima, sobre a mesma área: por isso tem
 * `piso` 2 e não faz fronteira de chão com as outras.
 */
class GerarZonas extends Command
{
    protected $signature = 'mapa:gerar-zonas';

    protected $description = 'Gera o arquivo de zonas a partir da planta real do piso';

    /**
     * Áreas impressas na planta, em m². Valem mais que qualquer medição minha sobre o
     * desenho, e é por elas que a escala das outras zonas é calibrada.
     */
    private const AREA_IMPRESSA = ['Z07' => 265.90];

    /**
     * id, nome, tipo, piso, vizinhos e polígono no sistema da planta.
     *
     * @var list<array{string, string, string, int, list<string>, list<array{int, int}>}>
     */
    private const ZONAS = [
        ['Z01', 'Praça de Alimentação', 'alimentacao', 2, [],
            [[82, 1214], [216, 1151], [432, 1132], [591, 1151], [639, 1233], [630, 1377],
                [577, 1512], [466, 1598], [312, 1618], [178, 1579], [82, 1483], [43, 1348]]],

        ['Z02', 'Mall da C&A', 'corredor', 1, [],
            [[455, 995], [584, 1023], [705, 1029], [826, 1034], [900, 1011], [964, 978],
                [1025, 933], [1084, 889], [1140, 846], [1195, 805], [1234, 783], [1275, 793],
                [1325, 800], [1364, 822], [1419, 873], [1477, 917], [1535, 964], [1595, 1013],
                [1668, 1038], [1743, 1044], [1814, 1044], [1887, 1037], [1933, 1013],
                [1883, 925], [1863, 939], [1810, 942], [1747, 942], [1688, 938], [1645, 925],
                [1599, 886], [1541, 837], [1485, 795], [1424, 740], [1357, 704], [1291, 693],
                [1218, 683], [1141, 719], [1080, 764], [1022, 807], [965, 851], [910, 892],
                [860, 917], [818, 932], [709, 927], [598, 923], [477, 895]]],

        ['Z05', 'Mall Stilo', 'corredor', 1, ['Z06'],
            [[630, 1230], [681, 1227], [709, 1255], [742, 1320], [775, 1394], [789, 1436],
                [875, 1406], [859, 1360], [824, 1280], [781, 1201], [713, 1143], [630, 1140]]],

        ['Z06', 'Mall Oeste', 'corredor', 1, ['Z05', 'Z07'],
            [[832, 1472], [921, 1472], [1018, 1460], [1107, 1421], [1170, 1375], [1108, 1293],
                [1057, 1333], [992, 1362], [915, 1370], [832, 1370]]],

        ['Z07', 'Praça de Eventos Central', 'convivencia', 1, ['Z06', 'Z08'],
            [[1139, 1204], [1207, 1147], [1293, 1137], [1360, 1185], [1384, 1267],
                [1356, 1353], [1279, 1401], [1192, 1397], [1139, 1339]]],

        ['Z08', 'Mall Leste', 'corredor', 1, ['Z07'],
            [[1350, 1390], [1406, 1385], [1455, 1384], [1503, 1396], [1557, 1419],
                [1616, 1444], [1682, 1466], [1753, 1472], [1817, 1472], [1889, 1471],
                [1944, 1447], [1902, 1355], [1871, 1371], [1817, 1370], [1757, 1370],
                [1702, 1366], [1652, 1350], [1597, 1325], [1535, 1300], [1467, 1284],
                [1402, 1283], [1342, 1288]]],

    ];

    public function handle(): int
    {
        $escala = $this->calibrarEscala();
        $zonas = [];

        foreach (self::ZONAS as [$id, $nome, $tipo, $piso, $vizinhos, $pontos]) {
            $zonas[] = [
                'id' => $id,
                'nome' => $nome,
                'tipo' => $tipo,
                'piso' => $piso,
                'area_m2' => (int) round(self::AREA_IMPRESSA[$id] ?? $this->areaDoPoligono($pontos) * $escala),
                'area_da_planta' => isset(self::AREA_IMPRESSA[$id]),
                'centroide' => $this->centroide($pontos, $escala),
                'vizinhos' => $vizinhos,
                'planta' => ['pontos' => $pontos],
            ];
        }

        if (($erro = $this->conferir($zonas)) !== null) {
            $this->error($erro);

            return self::FAILURE;
        }

        file_put_contents(
            database_path('dados/zonas.json'),
            json_encode(['planta' => config('mapa.planta'), 'zonas' => $zonas],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->relatar($zonas, $escala);

        return self::SUCCESS;
    }

    /**
     * Quantos m² vale cada pixel² do desenho.
     *
     * A escala é da imagem, não dos meus polígonos: vem de config/mapa.php, onde foi
     * calibrada pelas três praças de eventos que têm área impressa na planta e que
     * concordaram entre si. Derivar a escala de um polígono traçado a olho daria o erro
     * desse traçado, e não a medida da planta.
     */
    private function calibrarEscala(): float
    {
        $pixelsPorMetro = (float) config('mapa.planta.pixels_por_metro');

        return 1 / ($pixelsPorMetro * $pixelsPorMetro);
    }

    /**
     * Compara a área impressa na planta com a que meu polígono mede, para mostrar o
     * tamanho do erro de traçado em cada zona que tem referência.
     *
     * @return array<string, float> zona => razão entre o medido e o impresso
     */
    private function conferirTracado(float $escala): array
    {
        $razoes = [];

        foreach (self::ZONAS as [$id, , , , , $pontos]) {
            if (isset(self::AREA_IMPRESSA[$id])) {
                $razoes[$id] = $this->areaDoPoligono($pontos) * $escala / self::AREA_IMPRESSA[$id];
            }
        }

        return $razoes;
    }

    /**
     * Área do polígono pela fórmula do cadarço, em pixels².
     *
     * @param  list<array{int, int}>  $pontos
     */
    private function areaDoPoligono(array $pontos): float
    {
        $soma = 0.0;
        $total = count($pontos);

        for ($i = 0; $i < $total; $i++) {
            [$x1, $y1] = $pontos[$i];
            [$x2, $y2] = $pontos[($i + 1) % $total];
            $soma += $x1 * $y2 - $x2 * $y1;
        }

        return abs($soma) / 2;
    }

    /**
     * Centro da zona em metros, medido do canto superior esquerdo da planta.
     *
     * @param  list<array{int, int}>  $pontos
     * @return list<float>
     */
    private function centroide(array $pontos, float $escala): array
    {
        $metrosPorPixel = sqrt($escala);

        return [
            round(array_sum(array_column($pontos, 0)) / count($pontos) * $metrosPorPixel, 1),
            round(array_sum(array_column($pontos, 1)) / count($pontos) * $metrosPorPixel, 1),
        ];
    }

    /**
     * Área mínima, tipo aceito e vizinhança simétrica (RNF04).
     *
     * @param  list<array<string, mixed>>  $zonas
     */
    private function conferir(array $zonas): ?string
    {
        $porId = collect($zonas)->keyBy('id');
        $areaMinima = (int) config('mapa.area_min_m2');
        $tipos = config('mapa.tipos_de_zona');

        foreach ($zonas as $zona) {
            if ($zona['area_m2'] < $areaMinima) {
                return "{$zona['id']} ficou com {$zona['area_m2']} m², menos que o mínimo de {$areaMinima}.";
            }

            if (! in_array($zona['tipo'], $tipos, strict: true)) {
                return "{$zona['id']}: tipo \"{$zona['tipo']}\" não é aceito.";
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

    /**
     * @param  list<array<string, mixed>>  $zonas
     */
    private function relatar(array $zonas, float $escala): void
    {
        $this->info(count($zonas).' zonas gravadas em '.database_path('dados/zonas.json'));

        $this->line(sprintf(
            'Escala da planta (config/mapa.php): 1 metro = %.2f pixels.',
            (float) config('mapa.planta.pixels_por_metro')
        ));

        foreach ($this->conferirTracado($escala) as $id => $razao) {
            $this->line(sprintf(
                '  %s: meu polígono mede %+.0f%% da área impressa na planta.',
                $id,
                ($razao - 1) * 100
            ));
        }

        $this->table(
            ['id', 'nome', 'tipo', 'piso', 'área (m²)', 'origem da área'],
            array_map(fn (array $zona): array => [
                $zona['id'],
                $zona['nome'],
                $zona['tipo'],
                $zona['piso'],
                $zona['area_m2'],
                $zona['area_da_planta'] ? 'impressa na planta' : 'estimada do polígono',
            ], $zonas),
        );

        $this->line('Circulação mapeada: '.array_sum(array_column($zonas, 'area_m2')).' m².');
    }
}
