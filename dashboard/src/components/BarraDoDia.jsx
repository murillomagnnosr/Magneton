import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Pause, Play, TrendingUp } from 'lucide-react';
import { numero } from '../api';

// A forma do dia, inteira e de uma vez.
//
// Substitui o slider, que era cego: ali você arrastava sem saber para onde ia. Aqui os
// dois picos aparecem de relance e você clica no que quer ver.

export default function BarraDoDia() {
  const { state, dispatch, janelas } = useContext(DashboardContext);
  const dia = state.diaInteiro;

  if (!dia?.janelas?.length) {
    return null;
  }

  // No ao vivo a barra é histórico, não seletor: clicar nela é pedir para sair do agora
  // e olhar aquele horário.
  const escolherJanela = (hora) => {
    if (state.aoVivo) dispatch({ type: 'ALTERNAR_AO_VIVO' });
    dispatch({ type: 'ESCOLHER_JANELA', payload: hora });
  };

  const maior = Math.max(...dia.janelas.map((janela) => janela.pessoas_no_piso), 1);
  const altura = 72;
  const largura = 1000;
  const passo = largura / dia.janelas.length;
  const noPeriodo = new Set(janelas);

  return (
    <section className="cartao">
      <div className="cartao__cabeca" style={{ marginBottom: 'var(--e3)' }}>
        <h2 className="cartao__titulo">Pessoas no piso ao longo do dia</h2>

        <span className="cartao__selo">
          {state.aoVivo
            ? 'o mapa mostra o agora · clique em uma barra para ver aquele horário'
            : state.janela
              ? `mostrando ${state.janela}`
              : 'média do período · clique em uma barra para ver um instante'}
        </span>
      </div>

      <svg
        viewBox={`0 0 ${largura} ${altura}`}
        style={{ width: '100%', height: 84, display: 'block', cursor: 'pointer' }}
        role="img"
        aria-label="Total de pessoas no piso em cada janela do dia"
      >
        {dia.janelas.map((janela, i) => {
          const dentro = noPeriodo.has(janela.hora);
          const atual = state.janela === janela.hora;
          const h = Math.max(2, (janela.pessoas_no_piso / maior) * (altura - 14));

          return (
            <g key={janela.hora} onClick={() => escolherJanela(janela.hora)}>
              <rect x={i * passo} y={0} width={passo} height={altura} fill="transparent" />
              <rect
                x={i * passo + 1}
                y={altura - h}
                width={Math.max(1, passo - 2)}
                height={h}
                rx={2}
                fill={atual ? '#0b1220' : dentro ? '#2563eb' : '#dbe3ed'}
              />
              <title>{`${janela.rotulo}: ${janela.pessoas_no_piso} pessoas no piso`}</title>
            </g>
          );
        })}
      </svg>

      <div
        className="numero"
        style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.72rem', color: 'var(--tinta-4)', marginTop: 4 }}
      >
        {[0, Math.floor(dia.janelas.length / 2), dia.janelas.length - 1].map((i) => (
          <span key={i}>{dia.janelas[i].hora}</span>
        ))}
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--e2)', marginTop: 'var(--e4)', flexWrap: 'wrap' }}>
        <button
          type="button"
          className={state.tocando ? 'botao botao--parar' : 'botao botao--primario'}
          onClick={() => {
            if (!state.janela) dispatch({ type: 'ESCOLHER_JANELA', payload: janelas[0] });
            dispatch({ type: 'ALTERNAR_PLAY' });
          }}
        >
          {state.tocando ? <><Pause size={15} /> Pausar</> : <><Play size={15} /> Rodar o dia</>}
        </button>

        {state.janela && (
          <button
            type="button"
            className="botao botao--fantasma"
            onClick={() => dispatch({ type: 'ESCOLHER_JANELA', payload: null })}
          >
            Voltar à média do período
          </button>
        )}

        <span
          style={{
            marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', gap: 6,
            fontSize: '0.8rem', color: 'var(--tinta-3)',
          }}
        >
          <TrendingUp size={14} color="var(--vivo)" />
          Pico do dia:{' '}
          <strong className="numero" style={{ color: 'var(--tinta)' }}>{dia.pico.janela}</strong>, com{' '}
          <span className="numero">{numero(dia.pico.pessoas_no_piso, 0)}</span> pessoas
        </span>
      </div>
    </section>
  );
}
