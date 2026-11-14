# Mapa de calor de fluxo de pessoas — Capim Dourado Shopping

**Painel no ar:** https://hackarun-mapa-calor.vercel.app

**Vídeo 90s (1):** https://youtube.com/shorts/bBIsDQgiO_Y — enviado em 14/11/2026 às 12:51

**Vídeo 90s (2):** https://youtu.be/hGV4shPiwrw?is=-L39BheeuShUC2IH

Projeto do HackaRun (desafio 02 — fluxo e movimentação de pessoas), equipe Magneton.
Um mapa de calor sobre a planta do shopping, que mostra **onde as pessoas se concentram,
onde fica vazio e como isso muda ao longo do dia** — a partir de contagens agregadas por
zona e por janela de 15 minutos, sem imagem e sem identificação de ninguém.

O shopping já conta quem entra e sai. O que ninguém sabia é **quantas pessoas ficam e
onde ficam** — é essa a lacuna que o projeto preenche.

---

## Como o serviço funciona (a cadeia)

```
Celular (Larix)  →  MediaMTX  →  worker (YOLO conta)  →  enviador  →  API Laravel  →  painel
   vídeo RTMP        servidor      descarta a imagem,     empurra       SQLite,        React
                     de vídeo      guarda só o número      o número      duas APIs      (Vercel)
```

- **A imagem nunca é gravada nem transmitida.** O worker conta as pessoas no quadro e
  descarta o quadro na hora. Só o número (quantas pessoas, em qual zona, em qual horário)
  segue adiante.
- **A API Laravel tem duas pontas:** uma que *recebe* os números (ingestão, só escreve) e
  outra que *entrega* os dados já tratados para o painel (transmissão, só lê).
- **O painel fica na internet (Vercel)**, mas quem conta é este computador. Um túnel
  (ngrok) liga os dois. Veja [PUBLICAR.md](PUBLICAR.md).

---

## Pré-requisitos (já instalados nesta máquina)

| Ferramenta | Onde |
| --- | --- |
| PHP 8.4 | `C:\Users\muril\tools\php\php.exe` |
| Node + npm | no PATH |
| Python (real, não o da Store) | `C:\Users\muril\AppData\Local\Python\bin\python.exe` |
| ngrok | `C:\Users\muril\tools\ngrok\ngrok.exe` |

Primeira vez em cada pasta:

```bash
cd servidor; C:\Users\muril\tools\php\php.exe composer.phar install
```

```bash
cd dashboard; npm install
```

O pipeline de câmeras tem seu próprio ambiente Python em `pipeline-ao-vivo\.venv` e os
pesos do modelo em `pipeline-ao-vivo\*.pt` (ver `pipeline-ao-vivo\README` se precisar
recriar).

---

## Subir o serviço

Há **três blocos** que sobem separados, cada um com seu script PowerShell. Para a
apresentação, sobe os três na ordem abaixo.

### 1. A API e o painel local

```bash
powershell -File C:\Users\muril\hackarun-mapa-calor\iniciar.ps1
```

Sobe a **API Laravel** em `http://localhost:8000` e o **painel** em `http://localhost:5173`.
Acrescente `-Semear` para encher o banco com um dia de contagens simuladas (demonstração
sem câmera):

```bash
powershell -File C:\Users\muril\hackarun-mapa-calor\iniciar.ps1 -Semear
```

### 2. O pipeline das câmeras

```bash
powershell -File C:\Users\muril\hackarun-mapa-calor\pipeline-ao-vivo\iniciar.ps1 -ComApi
```

Sobe o **MediaMTX** (servidor de vídeo), o **worker** (conta pessoas) e o **enviador**
(`-ComApi` — empurra as contagens para a API). Sem `-ComApi`, o worker conta mas nada
chega à API.

Depois, no **Larix** (app no celular), transmita para:

```
rtmp://192.168.137.1:1935/live/cam1
```

Use `live/cam2` para um segundo celular. Ligue o **ponto de acesso móvel do Windows** e
conecte os celulares nele — o IP `192.168.137.1` é o do hotspot.

> O enviador é o elo que leva o ao vivo até a tela. Se a tela ao vivo não atualizar,
> confira primeiro se ele está rodando (`-ComApi`).

### 3. Publicar na internet (túnel + Vercel)

O painel da Vercel **já está no ar** em https://hackarun-mapa-calor.vercel.app. Para ele
enxergar este computador, abra o túnel:

```bash
powershell -File C:\Users\muril\hackarun-mapa-calor\publicar.ps1 -Dominio crayfish-unwoven-semantic.ngrok-free.dev
```

O domínio é fixo, então as variáveis na Vercel não precisam mudar. O passo a passo
completo (conta do ngrok, variáveis da Vercel, chave de leitura) está em
[PUBLICAR.md](PUBLICAR.md).

---

## Usar o painel

Abra https://hackarun-mapa-calor.vercel.app (ou `http://localhost:5173` na rede local).

- **Mapa** (tela inicial): a planta com o calor por zona. Clique numa zona para ver a
  curva do dia dela. Abas **Térreo / 2º piso** trocam o pavimento; **Mapa de calor / Por
  zona** trocam o desenho.
- **Filtros** (topo): dia, período (manhã, almoço, tarde, noite, dia inteiro) e horário.
- **Ver ao vivo**: troca o histórico pelo que as câmeras veem agora, atualizando sozinho.
- **Relatórios** (botão no cabeçalho): padrão da semana, dia, mês e período personalizado.
- **Lateral**: total nas zonas medidas, densidade, zona mais cheia, pico do dia, e o
  ranking da mais cheia para a mais vazia.

**Privacidade na tela:** zona com menos de 5 pessoas numa janela não mostra número
(aparece "menos de 5"); zona sem câmera aparece em cinza. Isso é regra, não erro.

---

## As duas APIs

Base local: `http://localhost:8000`. Em produção, o painel chama a própria origem da
Vercel, que repassa para o túnel (ver `dashboard/api/ponte.js`).

### Ingestão — recebe os dados (só escreve)

Protegida por token (`MAPA_TOKEN_INGESTAO`) quando definido. Quem escreve é o worker.

| Rota | O quê |
| --- | --- |
| `POST /api/ingestao/v1/contagens` | janelas de 15 min já fechadas |
| `DELETE /api/ingestao/v1/contagens` | apaga janelas (uso interno) |
| `POST /api/ingestao/v1/ao-vivo` | o estado atual de cada zona |

### Transmissão — entrega os dados (só lê)

Fechada por `MAPA_CHAVE_LEITURA` quando o túnel está no ar (cabeçalho `x-api-key`).
Nenhuma resposta é cacheada (`no-store`), e nenhuma zona abaixo do limiar vira número.

| Rota | O quê |
| --- | --- |
| `GET /api/v1/zonas` | as zonas e seus polígonos |
| `GET /api/v1/zonas/{zona}/curva` | a curva do dia de uma zona |
| `GET /api/v1/resumo` | onde concentra + pico, num período |
| `GET /api/v1/dia` | o total do piso em cada janela do dia |
| `GET /api/v1/ao-vivo` | o estado atual das câmeras |
| `GET /api/v1/pico` | o horário de maior movimento |
| `GET /api/v1/panorama` | o padrão da semana |
| `GET /api/v1/relatorio?de=&ate=` | relatório de um intervalo (dia, mês, personalizado) |
| `GET /api/v1/config` | parâmetros do mapa (janela, horário, planta) |
| `GET /api/v1/saude` | diagnóstico: quantos registros, quais dias, fonte |

---

## Dados reais x simulados

O banco guarda **uma fonte de cada vez** (RNF07): ou tudo simulado, ou tudo de câmera.
Enquanto houver dados simulados (o `-Semear`), o enviador recusa gravar o histórico real
(erro 409). Para rodar com câmera de verdade, apague os simulados antes:

```bash
cd servidor; C:\Users\muril\tools\php\php.exe artisan tinker --execute "App\Models\Contagem::query()->delete(); echo 'apagado';"
```

O **ao vivo funciona nos dois casos** — ele usa outra tabela, que não se mistura com o
histórico.

---

## Se algo não funcionar

| Sintoma | Causa provável |
| --- | --- |
| Painel diz "o servidor não respondeu" | a API local caiu, ou o túnel fechou |
| Tela ao vivo não atualiza | o enviador não está rodando (suba com `-ComApi`) |
| Zona sempre "menos de 5" no teste | menos de 5 pessoas na câmera — é a regra de privacidade |
| Enviador dá erro 409 | há dados simulados no banco; apague antes do dado real |
| Câmera não aparece | Larix sem transmitir, ou hotspot do Windows desligado |

Mais detalhe de publicação em [PUBLICAR.md](PUBLICAR.md); requisitos e decisões de
projeto em [REQUISITOS.md](REQUISITOS.md).
