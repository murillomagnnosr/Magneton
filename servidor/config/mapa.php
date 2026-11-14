<?php

/**
 * Parâmetros do mapa de calor (REQUISITOS.md, seção 8).
 *
 * Os valores de privacidade (k, duração da janela, horário) valem para a entrada e para a
 * saída: o que entra fora deles é recusado, e o que sai abaixo de k não vira número.
 */
return [

    // Janela única de agregação, em minutos (RNF03).
    'duracao_min' => (int) env('MAPA_DURACAO_MIN', 15),

    // Horário de funcionamento. Janela fora dele é recusada.
    'horario_abre' => env('MAPA_HORARIO_ABRE', '10:00'),
    'horario_fecha' => env('MAPA_HORARIO_FECHA', '22:00'),

    // Contagem mínima publicável (RNF02). Abaixo disso sai "abaixo do limiar".
    'k' => (int) env('MAPA_K', 5),

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
        'dia_inteiro' => [10, 22],
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
];
