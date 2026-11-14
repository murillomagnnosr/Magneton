// Ponte entre o painel publicado e o servidor que está rodando no computador do evento.
//
// O painel é estático: ele não tem como falar com o PC da sala direto, e se falasse o
// endereço do túnel e a chave ficariam no código que qualquer pessoa lê no navegador.
// Então o navegador chama esta função, que roda na Vercel, e é ela quem atravessa o
// túnel levando a chave. Para o painel nada muda: continua chamando /api/v1/... na
// própria origem, igual ao proxy do Vite em desenvolvimento.
//
// Variáveis de ambiente (painel da Vercel, nunca no código):
//   CAMERA_API_URL  endereço público do túnel, ex.: https://seu-dominio.ngrok-free.app
//   CAMERA_API_KEY  a mesma chave configurada em MAPA_CHAVE_LEITURA no servidor
//
// Só passa leitura. As rotas de ingestão (/api/ingestao/...) ficam de fora de propósito:
// quem escreve contagem é o worker, pela rede local, nunca a internet.

export default async function handler(requisicao, resposta) {
  const destino = process.env.CAMERA_API_URL;

  if (!destino) {
    return resposta.status(503).json({
      message: 'O painel ainda não sabe onde fica o servidor: falta CAMERA_API_URL.',
    });
  }

  if (requisicao.method !== 'GET') {
    return resposta.status(405).json({ message: 'Esta ponte só lê dados.' });
  }

  // O caminho chega como ['v1', 'zonas']; o 'api' da frente fica implícito na pasta.
  const partes = [].concat(requisicao.query.caminho ?? []);

  if (partes[0] !== 'v1') {
    return resposta.status(404).json({ message: 'Rota fora da API de transmissão.' });
  }

  const query = new URLSearchParams();
  for (const [chave, valor] of Object.entries(requisicao.query)) {
    if (chave !== 'caminho') {
      query.append(chave, valor);
    }
  }

  const url = `${destino.replace(/\/$/, '')}/api/${partes.map(encodeURIComponent).join('/')}`
    + (query.size ? `?${query}` : '');

  let doServidor;
  try {
    doServidor = await fetch(url, {
      headers: {
        Accept: 'application/json',
        'x-api-key': process.env.CAMERA_API_KEY ?? '',
        // Sem isto o ngrok gratuito devolve uma página de aviso no lugar do JSON.
        'ngrok-skip-browser-warning': 'true',
      },
      signal: AbortSignal.timeout(10_000),
    });
  } catch {
    return resposta.status(502).json({
      message: 'O servidor do evento não respondeu. O túnel está aberto?',
    });
  }

  const corpo = await doServidor.text();

  // O dado muda a cada poucos segundos: cachear aqui mostraria contagem velha.
  resposta.setHeader('Cache-Control', 'no-store');
  resposta.setHeader(
    'Content-Type',
    doServidor.headers.get('content-type') ?? 'application/json'
  );

  return resposta.status(doServidor.status).send(corpo);
}
