# Publicar o painel

O painel fica na internet, na Vercel. O servidor que conta pessoas continua neste
computador: ele é quem tem as câmeras na rede e o modelo rodando, e isso não cabe numa
hospedagem sem máquina própria. O túnel do ngrok é a ponte entre os dois.

```
navegador  →  painel na Vercel  →  /api/v1/...  →  função da Vercel
                                                        ↓  (chave)
                                              túnel ngrok  →  este PC :8000
                                                                   ↑
                                                       câmeras → worker → API
```

A chave nunca aparece no navegador: quem a envia é a função que roda na Vercel.

## O que já está pronto no repositório

| Peça | Onde |
| --- | --- |
| Ponte que atravessa o túnel | `dashboard/api/ponte.js` |
| Chave exigida para ler a API | `servidor/app/Http/Middleware/ChaveDeLeitura.php` |
| Script que abre o túnel | `publicar.ps1` |
| Chave já gerada e gravada | `servidor/.env`, `dashboard/.env.local` (ambos fora do git) |

A ponte só deixa passar leitura (`/api/v1/...`). A ingestão continua fechada à internet:
quem escreve contagem é o worker, pela rede local.

## Já está no ar

| | |
| --- | --- |
| Painel | https://hackarun-mapa-calor.vercel.app |
| Túnel (domínio fixo) | https://crayfish-unwoven-semantic.ngrok-free.dev |
| Projeto na Vercel | `murillomagnnosr-4858s-projects/hackarun-mapa-calor` |

As variáveis `CAMERA_API_URL` e `CAMERA_API_KEY` já estão criadas em Production. Como o
domínio do túnel é fixo, não é preciso mexer nelas de novo.

O que resta por apresentação é só abrir o túnel e ligar as câmeras — veja
"No dia da apresentação". As três seções abaixo ficam registradas para refazer o
caminho do zero, se um dia for preciso.

## Passo 1 — ligar o ngrok à sua conta (uma vez só)

Crie a conta em https://ngrok.com, copie o authtoken do painel deles e rode:

```bash
C:\Users\muril\tools\ngrok\ngrok.exe config add-authtoken SEU_TOKEN
```

Na conta gratuita você também pode reservar **um domínio fixo** (Domains → New Domain).
Vale a pena: sem ele o endereço muda toda vez que o túnel abre, e aí a variável na Vercel
precisa ser atualizada a cada apresentação.

## Passo 2 — abrir o túnel

```bash
powershell -File C:\Users\muril\hackarun-mapa-calor\publicar.ps1
```

Com domínio fixo:

```bash
powershell -File C:\Users\muril\hackarun-mapa-calor\publicar.ps1 -Dominio seu-dominio.ngrok-free.app
```

O script sobe a API se ela não estiver no ar, avisa se a chave estiver vazia e abre o
túnel. Se o ngrok reclamar que o endereço já está online (`ERR_NGROK_334`), é porque há
um túnel aberto: feche a janela dele antes, ou use a que já está no ar.

Na tela do ngrok, a linha `Forwarding` mostra o endereço `https://...` — é ele que vai
para a Vercel no passo seguinte.

## Passo 3 — publicar o painel na Vercel

```bash
cd C:\Users\muril\hackarun-mapa-calor\dashboard
npx vercel login
npx vercel --prod
```

Quando perguntar o diretório do projeto, aponte para esta pasta `dashboard` (é a raiz do
painel; o resto do repositório não vai para a Vercel).

Depois, em Settings → Environment Variables do projeto, crie duas variáveis:

| Nome | Valor |
| --- | --- |
| `CAMERA_API_URL` | o endereço `https://...` que o ngrok mostrou |
| `CAMERA_API_KEY` | o valor de `MAPA_CHAVE_LEITURA` em `servidor/.env` |

E rode `npx vercel --prod` de novo, para o deploy já subir com as variáveis.

## No dia da apresentação

1. Ligar as câmeras (Larix apontando para `rtmp://192.168.137.1:1935/live/cam1`).
2. `pipeline-ao-vivo\iniciar.ps1 -ComApi` — captura, contagem e envio.
3. `publicar.ps1` — API e túnel.
4. Abrir o endereço da Vercel. Se o endereço do túnel mudou, atualizar `CAMERA_API_URL`.

Fechar a janela do ngrok ao terminar: o endereço público deixa de existir na hora.

## Se der errado

| Sintoma | Causa provável |
| --- | --- |
| Painel diz que o servidor não respondeu | túnel fechado, ou `CAMERA_API_URL` desatualizada |
| Painel carrega vazio e a API responde 401 | `CAMERA_API_KEY` diferente de `MAPA_CHAVE_LEITURA` |
| Vem uma página de aviso do ngrok no lugar do dado | a chamada não passou pela ponte da Vercel |
| Painel local (5173) parou de ler | falta `VITE_API_KEY` em `dashboard/.env.local` |
| `ERR_NGROK_334` ao abrir o túnel | já existe um túnel aberto no mesmo domínio |

## Privacidade

O que atravessa o túnel são números por zona, já agregados em janelas de 15 minutos e
suprimidos abaixo de k=5 — nunca imagem, vídeo nem identificador de pessoa. O vídeo não
sai desta máquina: os quadros são descartados logo depois da contagem.
