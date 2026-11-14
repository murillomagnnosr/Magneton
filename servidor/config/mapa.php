<?php

/**
 * Parâmetros do mapa de calor (REQUISITOS.md, seção 8).
 *
 * Os valores de privacidade (k, duração da janela, horário) valem para a entrada e para a
 * saída: o que entra fora deles é recusado, e o que sai abaixo de k não vira número.
 */
$horarioAbre = env('MAPA_HORARIO_ABRE', '10:00');
$horarioFecha = env('MAPA_HORARIO_FECHA', '22:00');

// "Dia inteiro" é o dia de funcionamento, não um intervalo fixo: assim, mudar o horário
// no .env não deixa contagem nenhuma fora de todos os períodos.
$abreNaHora = (int) substr($horarioAbre, 0, 2);
$fechaNaHora = (int) substr($horarioFecha, 0, 2) + (((int) substr($horarioFecha, 3, 2)) > 0 ? 1 : 0);

return [

    // Janela única de agregação, em minutos (RNF03).
    'duracao_min' => (int) env('MAPA_DURACAO_MIN', 15),

    // Horário de funcionamento. Janela fora dele é recusada.
    'horario_abre' => $horarioAbre,
    'horario_fecha' => $horarioFecha,

    // Contagem mínima publicável (RNF02). Abaixo disso sai "abaixo do limiar".
    'k' => (int) env('MAPA_K', 5),

    // O mesmo limiar vale na tela de operação. Baixar para 1 mostra a contagem crua e
    // só faz sentido para testar a câmera: numa demonstração real, deixe igual a k.
    'limiar_ao_vivo' => (int) env('MAPA_LIMIAR_AO_VIVO', env('MAPA_K', 5)),

    // Depois disso sem receber nada, a zona conta como sem câmera medindo.
    'ao_vivo_valido_por_s' => (int) env('MAPA_AO_VIVO_VALIDO_POR_S', 15),

    // Teto de sanidade da contagem, em pessoas por m² de zona (RNF01).
    'teto_por_m2' => (int) env('MAPA_TETO_POR_M2', 4),

    // Token do Bearer exigido na API de ingestão. Vazio deixa a ingestão aberta (modo demo).
    'token_ingestao' => env('MAPA_TOKEN_INGESTAO', ''),

    // Períodos consultáveis, em horas. Lista fechada: não há consulta por faixa livre.
    'periodos' => [
        'manha' => [10, 12],
        'almoco' => [12, 14],
        'tarde' => [14, 18],
        'noite' => [18, 22],
        'dia_inteiro' => [$abreNaHora, $fechaNaHora],
    ],

    // Classes de densidade, em pessoas por 100 m² (mesma legenda da Entrega 1).
    // Lista de pares [limite, nome]: chave de array em PHP não guarda float.
    'faixas' => [
        [1.5, 'muito_baixa'],
        [3.0, 'baixa'],
        [5.0, 'media'],
        [8.0, 'alta'],
    ],
    'faixa_acima' => 'muito_alta',

    // Tipos de zona aceitos (RNF04).
    'tipos_de_zona' => ['entrada', 'corredor', 'ancora', 'lojas', 'alimentacao', 'convivencia'],

    // Origem do conjunto de dados (RNF07). Um conjunto tem uma fonte só.
    'fontes' => ['simulado', 'sensor'],

    // Área mínima de uma zona, em m² (RNF04).
    'area_min_m2' => (int) env('MAPA_AREA_MIN_M2', 50),

    // Raio da suavização do mapa de calor, em metros. A suavização é só visual: o dado
    // continua tendo resolução de zona, e a tela diz isso (RF15).
    'suavizacao_m' => (float) env('MAPA_SUAVIZACAO_M', 8),

    // A planta sobre a qual as zonas foram traçadas. O painel usa isto para montar o
    // viewBox do mapa e saber qual imagem desenhar ao fundo; os polígonos das zonas estão
    // neste mesmo sistema de coordenadas. Trocar de planta é trocar estes três valores e
    // rodar `php artisan mapa:gerar-zonas`.
    'planta' => [
        'imagem' => 'planta-piso1.jpg',
        'largura' => 2740,
        'altura' => 1820,
        // O que mostrar da imagem: só o prédio, sem o estacionamento e as avenidas em
        // volta. É o viewBox do mapa; as coordenadas das zonas seguem sendo as da imagem
        // inteira, então mudar o enquadramento não mexe em nenhum polígono.
        'recorte' => ['x' => 120, 'y' => 40, 'largura' => 2360, 'altura' => 1740],
        // Escala do desenho, calibrada pelas praças de eventos, que têm a área impressa
        // na planta. `mapa:gerar-zonas` recalcula e avisa se este valor sair do lugar.
        'pixels_por_metro' => 11.3,
        'descricao' => 'Capim Dourado Shopping, 1º pavimento (térreo). '
            .'Planta Mix de Operações, novembro de 2026, folha 1/2.',
    ],
];
