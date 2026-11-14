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
            [[742, 1447], [701, 1348], [640, 1306], [601, 1224], [536, 1171], [262, 1236],
                [128, 1370], [114, 1498], [124, 1530], [279, 1610], [395, 1617], [519, 1581],
                [559, 1493]]],

        ['Z02', 'Mall da C&A', 'corredor', 1, ['Z05', 'Z16'],
            [[1299, 676], [1263, 676], [1168, 710], [1110, 747], [1005, 851], [984, 858],
                [974, 880], [918, 909], [621, 996], [594, 1020], [821, 1052], [953, 1021],
                [1206, 868]]],

        ['Z05', 'Mall Stilo', 'corredor', 1, ['Z02', 'Z06'],
            [[587, 1021], [567, 1059], [582, 1130], [641, 1186], [667, 1243], [713, 1271],
                [785, 1396], [812, 1417], [850, 1390], [880, 1338], [810, 1212], [715, 1123],
                [755, 1069], [817, 1052]]],

        ['Z06', 'Mall Oeste', 'corredor', 1, ['Z05', 'Z07', 'Z08'],
            [[1187, 1393], [1138, 1339], [1130, 1280], [1005, 1341], [879, 1350], [851, 1390],
                [810, 1419], [810, 1433], [834, 1462], [929, 1440], [1108, 1442]]],

        ['Z07', 'Praça de Eventos Central', 'convivencia', 1, ['Z06', 'Z08'],
            [[1139, 1204], [1207, 1147], [1293, 1137], [1360, 1185], [1384, 1267],
                [1356, 1353], [1279, 1401], [1192, 1397], [1139, 1339]]],

        ['Z08', 'Mall Leste', 'corredor', 1, ['Z06', 'Z07', 'Z17'],
            [[1301, 1388], [1332, 1384], [1350, 1406], [1442, 1421], [1745, 1438],
                [1954, 1433], [1910, 1324], [1814, 1343], [1675, 1309], [1424, 1275],
                [1394, 1264], [1392, 1219], [1370, 1181], [1362, 1190], [1385, 1268],
                [1355, 1355]]],

        ['Z16', 'Mall Nordeste', 'corredor', 1, ['Z02', 'Z17'],
            [[1213, 855], [1298, 832], [1369, 834], [1496, 948], [1631, 994], [1772, 1067],
                [1869, 1074], [1896, 1067], [1915, 1031], [1911, 980], [1868, 909], [1623, 873],
                [1595, 852], [1532, 759], [1366, 708], [1326, 681], [1302, 679]]],

        ['Z17', 'Galeria Leste', 'corredor', 1, ['Z08', 'Z16'],
            [[2039, 194], [1995, 196], [1922, 233], [1935, 414], [1925, 461], [1930, 668],
                [1901, 742], [1913, 911], [1873, 909], [1917, 996], [1901, 1061], [1927, 1308],
                [1914, 1331], [1959, 1435], [2010, 1433], [2032, 1413], [2010, 1347],
                [2017, 777], [2041, 379], [2056, 305], [2056, 211]]],

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
