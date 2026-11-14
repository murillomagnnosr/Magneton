import React, { createContext, useCallback, useEffect, useMemo, useReducer, useRef } from 'react';
import { api, janelasDoPeriodo } from '../api';

// Todo dado desta tela vem da API de transmissão. O que a API suprime por estar abaixo
// do limiar chega sem campo numérico e continua sem número na tela.

const estadoInicial = {
  // o que o usuário escolheu
  dia: null,
  // almoço é onde o movimento é alto o bastante para quase toda zona ter número;
  // em "dia inteiro" uma única janela vazia de manhã suprime a zona no período todo
  periodo: 'almoco',
  janela: null, // null mostra a média do período; "HH:MM" mostra uma janela
  zonaSelecionada: null,
  zonaSobrevoada: null,
  tocando: false,
  aoVivo: false, // operação: o estado atual das câmeras, buscado de poucos em poucos segundos

  // o que veio da API
  config: null,
  saude: null,
  zonas: [],
  porZona: {}, // zona_id => o que a camada de publicação liberou
  densidadeDoPiso: null,
  resumo: null,
  diaInteiro: null, // forma do dia: total do piso em cada janela
  vivo: null, // resposta de /v1/ao-vivo
  curva: null,

  carregando: true,
  erro: null,
};

function redutor(estado, acao) {
  switch (acao.type) {
    case 'ESCOLHER_DIA':
      return { ...estado, dia: acao.payload, janela: null, tocando: false, curva: null, zonaSelecionada: null };
    case 'ESCOLHER_PERIODO':
      return { ...estado, periodo: acao.payload, janela: null, tocando: false };
    case 'ESCOLHER_JANELA':
      return { ...estado, janela: acao.payload };
    case 'ESCOLHER_ZONA':
      return { ...estado, zonaSelecionada: acao.payload, curva: null };
    case 'SOBREVOAR_ZONA':
      return { ...estado, zonaSobrevoada: acao.payload };
    case 'ALTERNAR_PLAY':
      return { ...estado, tocando: !estado.tocando, aoVivo: false };
    case 'ALTERNAR_AO_VIVO':
      return { ...estado, aoVivo: !estado.aoVivo, tocando: false, janela: null };
    case 'VIVO_CARREGADO':
      return { ...estado, vivo: acao.payload };
    case 'BASE_CARREGADA':
      return { ...estado, ...acao.payload, carregando: false, erro: null };
    case 'DADOS_CARREGADOS':
      return { ...estado, ...acao.payload, erro: null };
    case 'DIA_CARREGADO':
      return { ...estado, diaInteiro: acao.payload };
    case 'CURVA_CARREGADA':
      return { ...estado, curva: acao.payload };
    case 'FALHOU':
      return { ...estado, erro: acao.payload, carregando: false };
    default:
      return estado;
  }
}

export const DashboardContext = createContext();

export function DashboardProvider({ children }) {
  const [state, dispatch] = useReducer(redutor, estadoInicial);
  const cache = useRef(new Map());

  // Zonas, parâmetros e dias disponíveis: carregam uma vez.
  useEffect(() => {
    let cancelado = false;

    Promise.all([api.zonas(), api.config(), api.saude()])
      .then(([zonas, config, saude]) => {
        if (cancelado) return;
        dispatch({
          type: 'BASE_CARREGADA',
          payload: { zonas, config, saude, dia: saude.dias?.[0] ?? null },
        });
      })
      .catch((erro) => !cancelado && dispatch({ type: 'FALHOU', payload: erro.message }));

    return () => {
      cancelado = true;
    };
  }, []);

  // A forma do dia inteiro: uma chamada só, que alimenta a barra do topo.
  useEffect(() => {
    if (!state.dia) return undefined;

    let cancelado = false;

    api
      .dia(state.dia)
      .then((diaInteiro) => !cancelado && dispatch({ type: 'DIA_CARREGADO', payload: diaInteiro }))
      .catch(() => !cancelado && dispatch({ type: 'DIA_CARREGADO', payload: null }));

    return () => {
      cancelado = true;
    };
  }, [state.dia]);

  // Contagens da janela ou do período, e o resumo do período.
  useEffect(() => {
    if (!state.dia) return undefined;

    let cancelado = false;
    const chave = `${state.dia}|${state.periodo}|${state.janela ?? 'periodo'}`;

    const emCache = cache.current.get(chave);
    const pedido = emCache
      ? Promise.resolve(emCache)
      : (state.janela
          ? api.daJanela(state.dia, state.janela)
          : api.doPeriodo(state.dia, state.periodo)
        ).then((corpo) => {
          cache.current.set(chave, corpo);
          return corpo;
        });

    Promise.all([pedido, api.resumo(state.dia, state.periodo)])
      .then(([contagens, resumo]) => {
        if (cancelado) return;
        dispatch({
          type: 'DADOS_CARREGADOS',
          payload: {
            porZona: Object.fromEntries(contagens.zonas.map((zona) => [zona.zona_id, zona])),
            densidadeDoPiso: contagens.densidade_do_piso ?? resumo.densidade_do_piso,
            resumo,
          },
        });
      })
      .catch((erro) => !cancelado && dispatch({ type: 'FALHOU', payload: erro.message }));

    return () => {
      cancelado = true;
    };
  }, [state.dia, state.periodo, state.janela]);

  // Modo ao vivo: busca o estado atual das câmeras de poucos em poucos segundos.
  // O intervalo vem do próprio servidor, para não ficar dois lugares combinando o mesmo número.
  useEffect(() => {
    if (!state.aoVivo) return undefined;

    let cancelado = false;
    const intervalo = Math.max(1, (state.config?.ao_vivo_valido_por_s ?? 15) / 5) * 1000;

    const buscar = () =>
      api
        .aoVivo()
        .then((vivo) => !cancelado && dispatch({ type: 'VIVO_CARREGADO', payload: vivo }))
        .catch((erro) => !cancelado && dispatch({ type: 'FALHOU', payload: erro.message }));

    buscar();
    const relogio = setInterval(buscar, intervalo);

    return () => {
      cancelado = true;
      clearInterval(relogio);
    };
  }, [state.aoVivo, state.config]);

  // Curva do dia da zona selecionada.
  useEffect(() => {
    if (!state.zonaSelecionada || !state.dia) return undefined;

    let cancelado = false;

    api
      .curva(state.zonaSelecionada, state.dia)
      .then((curva) => !cancelado && dispatch({ type: 'CURVA_CARREGADA', payload: curva }))
      .catch(() => !cancelado && dispatch({ type: 'CURVA_CARREGADA', payload: null }));

    return () => {
      cancelado = true;
    };
  }, [state.zonaSelecionada, state.dia]);

  const janelas = useMemo(
    () => janelasDoPeriodo(state.config?.periodos, state.periodo, state.config?.duracao_min),
    [state.config, state.periodo]
  );

  // Reprodução: anda de janela em janela e volta ao começo no fim.
  useEffect(() => {
    if (!state.tocando || janelas.length === 0) return undefined;

    const relogio = setInterval(() => {
      const atual = janelas.indexOf(state.janela);
      dispatch({ type: 'ESCOLHER_JANELA', payload: janelas[(atual + 1) % janelas.length] });
    }, 900);

    return () => clearInterval(relogio);
  }, [state.tocando, state.janela, janelas]);

  const zonaPorId = useMemo(
    () => Object.fromEntries(state.zonas.map((zona) => [zona.id, zona])),
    [state.zonas]
  );

  const escolherZona = useCallback((zonaId) => dispatch({ type: 'ESCOLHER_ZONA', payload: zonaId }), []);
  const sobrevoarZona = useCallback((zonaId) => dispatch({ type: 'SOBREVOAR_ZONA', payload: zonaId }), []);

  // No modo ao vivo, o que a tela desenha vem de /v1/ao-vivo em vez do histórico. Trocar
  // aqui mantém os componentes iguais: eles continuam lendo porZona e densidadeDoPiso.
  const estadoVisivel = useMemo(() => {
    if (!state.aoVivo || !state.vivo) return state;

    return {
      ...state,
      porZona: Object.fromEntries(state.vivo.zonas.map((zona) => [zona.zona_id, zona])),
      densidadeDoPiso: state.vivo.densidade_do_piso,
    };
  }, [state]);

  const valor = useMemo(
    () => ({ state: estadoVisivel, dispatch, janelas, zonaPorId, escolherZona, sobrevoarZona }),
    [estadoVisivel, janelas, zonaPorId, escolherZona, sobrevoarZona]
  );

  return <DashboardContext.Provider value={valor}>{children}</DashboardContext.Provider>;
}
