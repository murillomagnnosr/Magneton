// Cliente da API de transmissão (servidor Laravel).
//
// Em desenvolvimento o Vite repassa /api para o Laravel (ver vite.config.js), então o
// endereço padrão é vazio. Para apontar para outra máquina, defina VITE_API_URL.

const BASE = import.meta.env.VITE_API_URL ?? '';

class ErroDaApi extends Error {
  constructor(mensagem, status) {
    super(mensagem);
    this.name = 'ErroDaApi';
    this.status = status;
  }
}

async function buscar(rota, parametros = {}) {
  const query = new URLSearchParams(
    Object.entries(parametros).filter(([, valor]) => valor !== null && valor !== undefined && valor !== '')
  );
  const url = `${BASE}/api/v1/${rota}${query.size ? `?${query}` : ''}`;

  let resposta;
  try {
    resposta = await fetch(url, { headers: { Accept: 'application/json' } });
  } catch {
    throw new ErroDaApi('A API não respondeu. Ela está no ar em http://localhost:8000?', 0);
  }

  if (!resposta.ok) {
    const corpo = await resposta.json().catch(() => ({}));
    throw new ErroDaApi(corpo.message ?? `A API respondeu ${resposta.status}.`, resposta.status);
  }

  return resposta.json();
}

export const api = {
  saude: () => buscar('saude'),
  config: () => buscar('config'),
  zonas: () => buscar('zonas').then((corpo) => corpo.data),
  resumo: (dia, periodo) => buscar('resumo', { dia, periodo }),
  dia: (dia) => buscar('dia', { dia }),
  aoVivo: () => buscar('ao-vivo'),
  pico: (dia, periodo) => buscar('pico', { dia, periodo }),
  panorama: () => buscar('panorama'),
  doPeriodo: (dia, periodo) => buscar('contagens', { dia, periodo }),
  daJanela: (dia, janela) => buscar('contagens', { dia, janela }),
  curva: (zonaId, dia) => buscar(`zonas/${zonaId}/curva`, { dia }),
};

export { ErroDaApi };

// Classes de densidade, na mesma legenda da Entrega 1 (pessoas por 100 m²).
export const CLASSES = {
  muito_baixa: { cor: '#FFFFB2', rotulo: 'até 1,5' },
  baixa: { cor: '#FECC5C', rotulo: '1,5 a 3,0' },
  media: { cor: '#FD8D3C', rotulo: '3,0 a 5,0' },
  alta: { cor: '#F03B20', rotulo: '5,0 a 8,0' },
  muito_alta: { cor: '#BD0026', rotulo: 'acima de 8,0' },
  abaixo_do_limiar: { cor: 'url(#hachura)', rotulo: 'abaixo do limiar' },
  sem_dado: { cor: '#D5DAE0', rotulo: 'sem dado' },
};

// Texto claro acima destas classes, escuro abaixo.
export const CLASSES_ESCURAS = new Set(['alta', 'muito_alta']);

/** As janelas de 15 min de um período, como ["12:00", "12:15", ...]. */
export function janelasDoPeriodo(periodos, periodo, duracaoMin = 15) {
  const faixa = periodos?.[periodo];
  if (!faixa) return [];

  const [comeca, termina] = faixa;
  const janelas = [];

  for (let minuto = comeca * 60; minuto < termina * 60; minuto += duracaoMin) {
    const hora = String(Math.floor(minuto / 60)).padStart(2, '0');
    const min = String(minuto % 60).padStart(2, '0');
    janelas.push(`${hora}:${min}`);
  }

  return janelas;
}

/** "12:30" vira "12:30–12:45". */
export function rotuloDaJanela(janela, duracaoMin = 15) {
  const [hora, minuto] = janela.split(':').map(Number);
  const fim = hora * 60 + minuto + duracaoMin;

  return `${janela}–${String(Math.floor(fim / 60)).padStart(2, '0')}:${String(fim % 60).padStart(2, '0')}`;
}

/** "13:00" vira "13h"; "13:30" vira "13h30". */
export function hora(valor) {
  if (!valor) return '—';
  const [h, m] = valor.split(':');
  return m === '00' ? `${Number(h)}h` : `${Number(h)}h${m}`;
}

/** Número no padrão brasileiro: 14.73 vira "14,73". */
export function numero(valor, casas = 1) {
  if (valor === null || valor === undefined) return '—';
  return valor.toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
}

/** Quantas pessoas a zona tem, seja numa janela ou na média do período. */
export function pessoasDe(publicado) {
  return publicado?.pessoas ?? publicado?.pessoas_media ?? null;
}

/**
 * Quantas vezes a zona está mais cheia que a média do piso.
 *
 * É a régua que torna a contagem compreensível: "2,4× a média" diz mais a um gerente
 * do que "14,73 pessoas por 100 m²".
 */
export function vezesAMedia(publicado, densidadeDoPiso) {
  if (!publicado?.densidade_100m2 || !densidadeDoPiso) return null;
  return publicado.densidade_100m2 / densidadeDoPiso;
}

/** "2,4× mais cheia", "do tamanho da média", "a metade da média". */
export function comparacaoEmPalavras(vezes) {
  if (vezes === null) return null;
  if (vezes >= 1.15) return `${numero(vezes, 1)}× mais cheia que a média do piso`;
  if (vezes >= 0.85) return 'no mesmo nível da média do piso';
  return `${numero(1 / vezes, 1)}× mais vazia que a média do piso`;
}
