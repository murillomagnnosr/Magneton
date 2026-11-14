import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Pause, Play } from 'lucide-react';
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
    <section style={{ background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: 12, padding: '1.25rem 1.5rem' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: '0.6rem', gap: '1rem', flexWrap: 'wrap' }}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', color: '#334155' }}>
          Pessoas no piso ao longo do dia
        </h2>
        <span style={{ fontSize: '0.8rem', color: '#64748b' }}>
          {state.aoVivo
            ? 'o mapa mostra o agora · clique em uma barra para ver o histórico daquele horário'
            : state.janela
              ? `mostrando ${state.janela}`
              : 'mostrando a média do período · clique em uma barra para ver um instante'}
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
                rx={1.5}
                fill={atual ? '#0f172a' : dentro ? '#3b82f6' : '#cbd5e1'}
              />
              <title>{`${janela.rotulo}: ${janela.pessoas_no_piso} pessoas no piso`}</title>
            </g>
          );
        })}
      </svg>

      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.72rem', color: '#94a3b8', marginTop: 4 }}>
        {[0, Math.floor(dia.janelas.length / 2), dia.janelas.length - 1].map((i) => (
          <span key={i}>{dia.janelas[i].hora}</span>
        ))}
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginTop: '0.9rem', flexWrap: 'wrap' }}>
        <button
          onClick={() => {
            if (!state.janela) dispatch({ type: 'ESCOLHER_JANELA', payload: janelas[0] });
            dispatch({ type: 'ALTERNAR_PLAY' });
          }}
          style={botao(state.tocando)}
        >
          {state.tocando ? <><Pause size={15} /> Pausar</> : <><Play size={15} /> Rodar o dia</>}
        </button>

        {state.janela && (
          <button onClick={() => dispatch({ type: 'ESCOLHER_JANELA', payload: null })} style={botao(false, true)}>
            Voltar à média do período
          </button>
        )}

        <span style={{ marginLeft: 'auto', fontSize: '0.8rem', color: '#64748b' }}>
          Pico do dia: <strong style={{ color: '#0f172a' }}>{dia.pico.janela}</strong>, com{' '}
          {numero(dia.pico.pessoas_no_piso, 0)} pessoas
        </span>
      </div>
    </section>
  );
}

function botao(ativo, discreto = false) {
  return {
    display: 'flex',
    alignItems: 'center',
    gap: 6,
    padding: '0.45rem 0.9rem',
    borderRadius: 8,
    cursor: 'pointer',
    fontWeight: 600,
    fontSize: '0.82rem',
    border: `1px solid ${ativo ? '#fca5a5' : discreto ? '#e2e8f0' : '#93c5fd'}`,
    background: ativo ? '#fef2f2' : discreto ? '#ffffff' : '#eff6ff',
    color: ativo ? '#ef4444' : discreto ? '#64748b' : '#3b82f6',
  };
}
