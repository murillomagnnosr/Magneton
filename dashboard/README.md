# Painel do mapa de calor

Interface em React + Vite que consome a API de transmissão em `../servidor`.

Partiu do dashboard inicial de <https://github.com/Samuel-slv/dashboard-fluxo>. O layout,
a identidade visual e os componentes vieram de lá; os dados, que antes eram sorteados no
navegador, passaram a vir da API.

## Como rodar

A API precisa estar no ar primeiro:

```powershell
cd ..\servidor
php artisan serve --host=0.0.0.0 --port=8000
```

Depois, em outro terminal:

```powershell
npm install
npm run dev
```

O painel abre em <http://localhost:5173>. O Vite repassa `/api` para a porta 8000, então
não há CORS no caminho. Para apontar para outra máquina, use `VITE_API_PROXY`.

Sem dado nenhum gravado, o painel avisa que não há contagem. Para encher com um dia:

```powershell
cd ..\servidor
php artisan mapa:semear-demo --dia=2026-11-07 --limpar
```

## O que cada parte mostra

## Dois modos

O botão **Ao vivo**, no topo, alterna entre as duas leituras do mesmo sistema:

| Modo | O que mostra | De onde vem |
|---|---|---|
| Histórico (padrão) | Média por período, em janelas de 15 min | `/v1/contagens`, `/v1/resumo`, `/v1/dia` |
| Ao vivo | O que as câmeras veem agora, atualizando sozinho | `/v1/ao-vivo` |

São coisas diferentes e a tela diz qual está mostrando. O ao vivo serve à operação: nada
dele vira histórico, porque `/v1/ao-vivo` guarda uma linha por zona e cada envio sobrescreve
a anterior. Não há série temporal fina para reconstruir o caminho de ninguém.

As duas visões têm as mesmas peças — frase, barra do dia, mapa de calor, ranking e detalhe
da zona. O que muda é o papel de cada uma:

| Peça | No histórico | No ao vivo |
|---|---|---|
| Barra do dia | escolhe a janela | é o histórico ao lado do agora; clicar sai do ao vivo para aquele horário |
| Mapa e ranking | média ou janela escolhida | o que as câmeras veem agora, com o número cru |
| Detalhe da zona | contagem da seleção e curva do dia | contagem da câmera, com a idade da medição, mais a curva do dia como contexto |
| Filtros de dia, período e horário | escolhem o recorte | somem: no ao vivo só existe o agora |

O limiar vale nos dois modos. Em `MAPA_LIMIAR_AO_VIVO=1` a tela ao vivo mostra a contagem
crua, o que serve para testar a câmera com uma ou duas pessoas; apague a variável para
a demonstração, e volta a valer o k=5.

A tela tem três faixas, cada uma respondendo uma pergunta:

| Faixa | Pergunta | De onde vem o dado |
|---|---|---|
| `Frase` | O que está acontecendo? | `/v1/resumo` e `/v1/contagens`; a regra de leitura fica em `src/frase.js` |
| `BarraDoDia` | Quando? | `/v1/dia` — total do piso em cada janela, o dia inteiro em uma chamada |
| `MallMap` | Onde? | `/v1/zonas` (geometria) e `/v1/contagens` |
| `Ranking` | Quanto? | o mesmo de `MallMap`, ordenado e em barras |
| `AreaDetails` | Como foi o dia desta zona? | `/v1/zonas/{id}/curva` |
| `PeriodoSelector` | — | `/v1/saude` (dias) e `/v1/config` (períodos) |
| `JanelaSelector` | Qual horário? | `/v1/dia` — a lista traz o movimento de cada janela |

Há dois jeitos de escolher o horário, de propósito: a **barra do dia** mostra a forma do
movimento e serve para escolher no olho; o **filtro Horário** é uma lista, para quando você
já sabe a hora que quer. Cada item da lista diz quantas pessoas havia no piso e marca o
pico, então o menu também é leitura.

O número principal é **quantas vezes a zona está mais cheia que a média do piso**, e não a
densidade bruta: "2,7× a média" se explica sozinho, "14,73 pessoas por 100 m²" não. A média
do piso vem de `densidade_do_piso`, que a API calcula só sobre as zonas com número publicado.

Passar o cursor por uma zona, no mapa ou na lista, reescreve a frase do topo para falar
dela. É assim que explorar vira leitura, em vez de garimpo.

## O mapa de calor

O mapa tem duas visões, no botão acima dele:

- **Mapa de calor** (padrão): a densidade de cada zona é espalhada a partir do centro dela
  com peso gaussiano, formando um campo contínuo sobre a planta. É o RF15 do
  `REQUISITOS.md`, com a fórmula de lá: `d(x) = Σ w_z(x)·d[z] / Σ w_z(x)`, com
  `w_z(x) = exp(-|x−c_z|²/2σ²)`. O raio σ é 8 metros, em `MAPA_SUAVIZACAO_M`.
- **Por zona**: cada zona pintada com a cor da sua classe, sem suavização.

Duas coisas importantes sobre o campo suavizado:

1. **Só entram zonas com número publicado.** Zona abaixo do limiar fica de fora da conta,
   então o campo não vaza por interpolação o valor que a camada de publicação suprimiu.
   Ela continua hachurada por cima do campo.
2. **A suavização é visual.** O dado tem resolução de zona, e a legenda diz isso. O campo
   desaparece longe de qualquer zona medida, para não pintar o que ninguém mediu.

O cálculo roda no navegador, em `src/campoDeCalor.js`, numa grade grossa que o navegador
interpola ao ampliar.

## A planta

O mapa é a planta real do Capim Dourado Shopping, 1º pavimento, da Planta Mix de Operações
de novembro de 2026. A imagem fica em `public/planta-piso1.jpg` e as zonas são desenhadas
por cima dela.

Não há coordenada escrita à mão nesta pasta. O painel pergunta à API qual imagem usar e em
que sistema de coordenadas (`/v1/config`, chave `planta`), e recebe o polígono de cada zona
em `/v1/zonas`. Trocar de planta é mexer em `mapa:gerar-zonas` no servidor e rodar o seeder.

As 13 zonas são áreas de **circulação pública** — corredores e praças. Loja nenhuma é zona,
porque o contrato não aceita (RNF04).

Os corredores não foram inventados: saíram de uma planta que o grupo marcou à mão, traçando
onde a circulação realmente passa. `ferramentas/converter_zonas.py` registra aquela imagem
contra a planta do projeto, converte as marcações para este sistema de coordenadas e desenha
o resultado por cima da marcação, para conferir. O que sai dali é colado em
`GerarZonas.php`.

Cada corredor é descrito como **eixo e largura**, que é como a marcação foi feita, e o
polígono sai do afastamento do eixo para os dois lados.

A **Praça de Alimentação fica no 2º pavimento**, sobre a mesma área. Ela tem `piso` 2, sai
no mapa com contorno tracejado azul e etiqueta do pavimento, e não faz fronteira de chão
com as vizinhas.

A área em m² da praça de eventos central vem impressa na planta. As demais são estimadas do
polígono, com a escala da imagem (`pixels_por_metro`), calibrada antes pelas três praças de
eventos que têm área impressa. O `mapa:gerar-zonas` informa, para cada zona com referência,
quanto o polígono traçado diverge do valor impresso — hoje a praça central está 52% maior,
o que dá a ordem de grandeza do erro de traçado.

## O que mudou em relação ao dashboard inicial

- **Os dados são reais.** Saíram os 35 pontos sorteados, a métrica `visitors.length * 12`
  e os textos fixos ("+14% vs ontem", "C&A, Riachuelo", "Insight do Algoritmo").
- **Saiu o modo de pontos por pessoa.** A API entrega contagem por zona, nunca a posição
  de alguém. O modo "Blocos" põe blocos na zona proporcionais à contagem, e a legenda diz
  quantas pessoas cada bloco representa.
- **Saiu o modo "Fluxo".** As setas de origem e destino precisariam de trajetória
  individual, que o sistema não coleta. Desenhar a seta mesmo assim seria inventar dado.
- **O seletor de pavimento virou seletor de dia e período.** A API cobre um piso com 16
  zonas; dia e período são escolhas que ela realmente aceita.
- **O slider de tempo virou a barra do dia.** No slider você arrastava sem saber para onde;
  na barra os dois picos aparecem de relance e você clica no que quer ver.
- **Saíram os cartões de métrica e o seletor de modo.** O conteúdo dos cartões virou a frase
  e o ranking; duas formas de desenhar a mesma contagem era uma escolha que ninguém queria fazer.
- **O limiar de privacidade aparece na tela.** Zona com menos de k pessoas sai hachurada e
  sem número, e a curva mostra lacuna no lugar de um ponto interpolado.
- **O selo da fonte saiu do cabeçalho**, a pedido. Ele dizia "Dados simulados" ou "Dados
  medidos por câmera" e atendia ao RF05 e ao RNF07 ("dado simulado nunca é apresentado como
  real"). A fonte continua no contrato e nas respostas de `/v1/resumo` e `/v1/saude`, então
  quem consome a API sabe a origem; o que sumiu foi o aviso na tela. O bloco para trazer de
  volta está comentado em `Header.jsx`.
