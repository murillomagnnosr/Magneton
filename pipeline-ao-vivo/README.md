# Pipeline ao vivo: contagem de pessoas por câmera

```
Celulares com Larix  →  MediaMTX  →  Python + YOLO + ByteTrack  →  Streamlit
   (RTMP por Wi-Fi)     (servidor)      (detecção e contagem)       (dashboard)
```

Os celulares enviam vídeo por RTMP. O MediaMTX recebe e repassa por RTSP. O worker lê
cada stream, detecta e rastreia pessoas e grava **só números** em um banco SQLite local.
O dashboard lê esse banco a cada 2 segundos.

Nenhuma imagem é gravada: o MediaMTX está com gravação desligada, o worker descarta cada
frame depois de contar, e o banco não tem coluna para imagem nem para identificador de pessoa.

## Arquivos

| Arquivo | Papel |
|---|---|
| `mediamtx.yml` | Configuração do servidor de streaming (RTMP na porta 1935, RTSP na 8554) |
| `cameras.json` | Lista de câmeras, zona de cada uma e parâmetros de detecção |
| `worker.py` | Lê os streams, roda YOLO + ByteTrack e grava as contagens |
| `dashboard.py` | Painel Streamlit em tempo real |
| `armazenamento.py` | Banco SQLite compartilhado entre worker e dashboard |
| `simular_camera.py` | Publica um vídeo ou foto no lugar de um celular, para teste |
| `enviar_para_api.py` | Envia as janelas de 15 min já fechadas para a API Laravel |
| `iniciar.ps1` | Sobe MediaMTX, worker e dashboard de uma vez |

## Instalação (uma vez)

1. Ambiente Python e dependências:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install torch torchvision --index-url https://download.pytorch.org/whl/cpu
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
```

2. MediaMTX: baixe `mediamtx_vX.Y.Z_windows_amd64.zip` em
   <https://github.com/bluenviron/mediamtx/releases> e extraia `mediamtx.exe` em `bin\`.

Na primeira execução o worker baixa os pesos `yolo11n.pt` (cerca de 5 MB).

## Como rodar

```powershell
.\iniciar.ps1
```

O script fecha sobras de uma execução anterior, abre três janelas e mostra o endereço para o Larix (com o ponto de acesso móvel do
Windows ligado, é `rtmp://192.168.137.1:1935/live/cam1`). O dashboard fica em
<http://localhost:8501>. Com `.\iniciar.ps1 -Mostrar`, o worker também abre uma janela por
câmera com as caixas de detecção, útil para posicionar o celular (só na tela, nada é salvo).

Para rodar cada parte à mão, em três terminais:

```powershell
.\bin\mediamtx.exe mediamtx.yml
.\.venv\Scripts\python.exe worker.py
.\.venv\Scripts\python.exe -m streamlit run dashboard.py
```

## Configurar o Larix no celular

1. Celular e computador na mesma rede. O caminho mais simples é ligar o **ponto de acesso
   móvel** do Windows (Configurações → Rede e Internet → Ponto de acesso móvel) e conectar os
   celulares nele: o PC fica sempre com o IP `192.168.137.1`, então a URL nunca muda.
2. No Larix Broadcaster: Settings → Connections → New connection.
3. URL: `rtmp://192.168.137.1:1935/live/cam1` (o segundo celular usa `live/cam2`, e assim por
   diante). Em outra rede Wi-Fi, troque o IP pelo que o `iniciar.ps1` mostrar.
4. Vídeo sugerido: 1280x720, 15 fps, 1500 kbps, H.264. Áudio pode ficar desligado.
5. Toque no botão de gravar para começar a transmitir.

Na primeira vez, o Windows pergunta se o `mediamtx.exe` pode receber conexões: permita em
redes privadas. Sem isso o celular não conecta.

## Testar sem celular

Com o MediaMTX e o worker rodando:

```powershell
.\.venv\Scripts\python.exe simular_camera.py
```

Publica em `live/cam1` uma foto de exemplo com 4 pessoas. Use
`--arquivo video.mp4 --caminho live/cam2` para outro vídeo ou outra câmera. Para usar a webcam
do próprio PC, troque a `url` da câmera em `cameras.json` por `"0"`.

## cameras.json

| Campo | Significado |
|---|---|
| `modelo` | Pesos do YOLO. `yolo11n.pt` é o mais leve; `yolo11s.pt` acerta mais e pesa mais |
| `confianca` | Confiança mínima da detecção (0 a 1) |
| `fps_maximo` | Teto de quadros analisados por segundo, por câmera |
| `min_frames_para_contar` | Quadros que uma trilha precisa durar para contar como pessoa distinta |
| `cameras[].url` | Endereço RTSP no MediaMTX (`rtsp://127.0.0.1:8554/live/camN`) |
| `cameras[].zona_id` | Zona da planta que a câmera cobre (usado na exportação) |
| `cameras[].linha` | Linha de contagem opcional, dois pontos em fração da imagem (`[[x, y], [x, y]]`), ou `null` |

Linha de contagem: com a linha traçada da esquerda para a direita, quem cruza de cima para
baixo na imagem conta como entrada, e o contrário como saída. Inverta os dois pontos para
trocar o sentido.

## O que o dashboard mostra

- Pessoas presentes agora, câmeras no ar, pessoas distintas e pico do período.
- Curva de pessoas presentes por câmera.
- Por câmera: contagem atual, distintas, média, entradas e saídas (se houver linha) e um
  mapa de calor abstrato de onde as pessoas ficam, medido pelo ponto dos pés.
- Botão **Reiniciar contagem**, na barra lateral: apaga o histórico, zera os mapas de calor
  e as pessoas já contadas, e manda o worker recomeçar do zero. Pede confirmação antes.
  Quem estiver em cena na hora volta a ser contado como pessoa nova. Serve para começar a
  demonstração limpa, depois dos testes de posicionamento das câmeras.
- Botão para baixar `contagens.json` no contrato do projeto
  (`zona_id`, `inicio_janela`, `duracao_min`, `contagem`, `fonte: "sensor"`), com as janelas
  de 15 minutos já fechadas das últimas 24 horas.

## Enviar para a API Laravel

A API fica em `../servidor`. Com ela no ar (`php artisan serve`), suba o pipeline com:

```powershell
.\iniciar.ps1 -ComApi
```

O `enviar_para_api.py` lê o mesmo banco do worker e, a cada minuto, manda para
`POST /api/ingestao/v1/contagens` as janelas de 15 minutos que já fecharam, com
`fonte: "sensor"`. Fica em processo separado de propósito: se a API cair, a contagem
continua e as janelas pendentes vão embora no ciclo seguinte.

O endereço da API está em `cameras.json`, no campo `api.url`. O `zona_id` de cada câmera
diz em qual região da planta ela está — é isso que liga a câmera ao mapa de calor.

Janela fora do horário de funcionamento da API (10h às 22h) não é enviada, e a API
recusaria de qualquer forma.

## Limites conhecidos

- "Pessoas distintas" é estimativa: quem sai do quadro e volta ganha novo número de trilha
  e é contado de novo. O mesmo vale para quem passa por duas câmeras.
- O mapa de calor por câmera zera quando o worker reinicia. As contagens ficam no banco.
- Roda em CPU. Com `yolo11n` e 640 px, espere de 5 a 10 quadros por segundo por câmera,
  menos com várias câmeras ao mesmo tempo.
- O MediaMTX avisa `reader is too slow, discarding N frames`. É esperado: o celular manda
  15 quadros por segundo e o worker analisa cerca de 8, de propósito. Ele sempre pega o
  quadro mais recente, então o painel não atrasa.
- O painel mostra contagens brutas, inclusive abaixo de 5. O limiar de privacidade k do
  projeto (RNF02) continua valendo na tela do mapa de calor, que consome a exportação.
