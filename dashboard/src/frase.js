import { comparacaoEmPalavras, numero, pessoasDe, vezesAMedia } from './api';

// A regra de leitura do painel, separada do desenho.
//
// É a diferença entre mostrar dado e responder pergunta: o mapa diz que uma zona está
// mais escura, esta frase diz o que isso significa.

const PERIODOS = {
  manha: 'de manhã',
  almoco: 'no almoço',
  tarde: 'à tarde',
  noite: 'à noite',
  dia_inteiro: 'no dia',
};

/**
 * Monta a frase do topo a partir do que está na tela.
 *
 * Com uma zona sob o cursor, a frase fala dela; sem nenhuma, fala da zona mais cheia do
 * período. Zona abaixo do limiar nunca ganha número aqui, como em nenhum outro lugar.
 */
export function montarFrase(state) {
  const { porZona, zonas, densidadeDoPiso, resumo, janela, periodo, zonaSobrevoada, zonaSelecionada } = state;

  if (zonas.length === 0) {
    return { titulo: 'Sem contagem para este período.', detalhe: null };
  }

  if (state.aoVivo) {
    return fraseAoVivo(state);
  }

  if (!resumo) {
    return { titulo: 'Sem contagem para este período.', detalhe: null };
  }

  const quando = state.aoVivo ? 'agora' : janela ? `às ${janela}` : (PERIODOS[periodo] ?? '');
  const emFoco = zonaSobrevoada ?? zonaSelecionada;
  const alvo = emFoco
    ? zonas.find((zona) => zona.id === emFoco)
    : zonas.find((zona) => zona.id === resumo.regiao_mais_movimentada?.zona_id);

  if (!alvo) {
    return {
      titulo: `Nenhuma zona passou do limiar ${quando}.`,
      detalhe: dizLimiar(resumo),
    };
  }

  const publicado = porZona[alvo.id];

  if (!publicado || publicado.classe === 'sem_dado') {
    return {
      titulo: `${alvo.nome}: nenhuma câmera mediu esta zona ${quando}.`,
      detalhe: 'Zona sem cobertura no período escolhido.',
    };
  }

  if (publicado.classe === 'abaixo_do_limiar') {
    return {
      titulo: `${alvo.nome} está praticamente vazia ${quando}.`,
      detalhe: dizLimiar(resumo),
    };
  }

  const pessoas = pessoasDe(publicado);
  const vezes = vezesAMedia(publicado, densidadeDoPiso);
  const comparacao = comparacaoEmPalavras(vezes);

  return {
    titulo: comparacao
      ? `${alvo.nome} está ${comparacao} ${quando}.`
      : `${alvo.nome} tem ${numero(pessoas, 0)} pessoas ${quando}.`,
    detalhe: [
      `${numero(pessoas, janela ? 0 : 1)} pessoas em ${numero(alvo.area_m2, 0)} m²`,
      resumo.pico ? `pico do período às ${resumo.pico.janela.split('–')[0]}` : null,
    ]
      .filter(Boolean)
      .join(' · '),
    destaque: vezes,
  };
}

/**
 * A frase do modo ao vivo. Aqui não há período nem média: é o que as câmeras veem agora.
 */
function fraseAoVivo(state) {
  const { vivo, zonas, porZona, densidadeDoPiso, zonaSobrevoada, zonaSelecionada } = state;

  if (!vivo) {
    return { titulo: 'Conectando às câmeras…', detalhe: null };
  }

  if (vivo.zonas_medindo === 0) {
    return {
      titulo: 'Nenhuma câmera está medindo agora.',
      detalhe: 'Ligue a transmissão no celular. A tela reage em poucos segundos.',
    };
  }

  const emFoco = zonaSobrevoada ?? zonaSelecionada;
  const alvo = emFoco ? zonas.find((zona) => zona.id === emFoco) : null;

  if (!alvo) {
    const zonasMedindo = vivo.zonas.filter((zona) => zona.medindo).map((zona) => zona.nome);

    return {
      titulo: `${vivo.pessoas_no_piso} ${vivo.pessoas_no_piso === 1 ? 'pessoa' : 'pessoas'} agora, em ${vivo.zonas_medindo} ${vivo.zonas_medindo === 1 ? 'zona medida' : 'zonas medidas'}.`,
      detalhe: `Medindo: ${zonasMedindo.join(', ')}. Atualiza sozinho.`,
    };
  }

  const publicado = porZona[alvo.id];

  if (!publicado?.medindo) {
    return {
      titulo: `${alvo.nome}: nenhuma câmera cobrindo esta zona agora.`,
      detalhe: 'Só as zonas com câmera aparecem no modo ao vivo.',
    };
  }

  if (publicado.classe === 'abaixo_do_limiar') {
    return {
      titulo: `${alvo.nome}: a câmera está medindo, e há menos de ${publicado.limiar} pessoas.`,
      detalhe: `O número exato não é publicado nem ao vivo, pela mesma regra que vale no histórico.`,
    };
  }

  const vezes = vezesAMedia(publicado, densidadeDoPiso);
  const comparacao = comparacaoEmPalavras(vezes);

  return {
    titulo: `${alvo.nome} tem ${publicado.pessoas} ${publicado.pessoas === 1 ? 'pessoa' : 'pessoas'} agora.`,
    detalhe: [
      `${numero(publicado.densidade_100m2, 2)} por 100 m²`,
      comparacao ? comparacao.replace('mais cheia que a média do piso', 'a média do piso') : null,
    ]
      .filter(Boolean)
      .join(' · '),
    destaque: vezes,
  };
}

function dizLimiar(resumo) {
  return `Menos de ${resumo.limiar} pessoas. O número não é publicado, para não permitir identificar quem estava ali.`;
}
