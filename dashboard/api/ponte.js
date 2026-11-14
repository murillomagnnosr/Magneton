// Ponte entre o painel publicado e o servidor que está rodando no computador do evento.
//
// O painel é estático: ele não tem como falar com o PC da sala direto, e se falasse o
// endereço do túnel e a chave ficariam no código que qualquer pessoa lê no navegador.
// Então o navegador chama esta função, que roda na Vercel, e é ela quem atravessa o
// túnel levando a chave. Para o painel nada muda: continua chamando /api/v1/... na
// própria origem, igual ao proxy do Vite em desenvolvimento.
//
// Quem traz /api/v1/... até aqui é o rewrite declarado em vercel.json, que entrega o
// resto do caminho no parâmetro `rota`. O arquivo tem nome simples de propósito: um
// arquivo de rota com colchetes no nome não chegou a ser empacotado no envio.
//
// Variáveis de ambiente (painel da Vercel, nunca no código):
//   CAMERA_API_URL  endereço público do túnel, ex.: https://seu-dominio.ngrok-free.dev
//   CAMERA_API_KEY  a mesma chave configurada em MAPA_CHAVE_LEITURA no servidor
//
// Só passa leitura, e só dentro de /api/v1. As rotas de ingestão ficam de fora de
// propósito: quem escreve contagem é o worker, pela rede local, nunca a internet.

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

  const rota = String(requisicao.query.rota ?? '');
  const partes = rota.split('/').filter(Boolean);

  // '..' subiria para fora de /api/v1; segmento vazio viraria barra dobrada.
  if (partes.length === 0 || partes.some((parte) => parte === '..')) {
    return resposta.status(404).json({ message: 'Rota fora da API de transmissão.' });
  }

  const query = new URLSearchParams();
  for (const [chave, valor] of Object.entries(requisicao.query)) {
    if (chave !== 'rota') {
      query.append(chave, valor);
    }
  }

  const url = `${destino.replace(/\/$/, '')}/api/v1/${partes.map(encodeURIComponent).join('/')}`
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

  // end() em vez de send(): o send() do runtime da Vercel gera um ETag, que dispara
  // requisição condicional e resposta 304 — indesejado numa tela que relê a cada segundos.
  resposta.statusCode = doServidor.status;
  return resposta.end(corpo);
}
