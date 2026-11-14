import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { montarFrase } from '../frase';

// A conclusão em uma frase, no topo da tela. A regra de leitura mora em ../frase.js.

export default function Frase() {
  const { state } = useContext(DashboardContext);
  const { titulo, detalhe, destaque } = montarFrase(state);
  const olhando = state.zonaSobrevoada ?? state.zonaSelecionada;

  return (
    <section style={{
      background: '#ffffff',
      border: '1px solid #e2e8f0',
      borderLeft: `6px solid ${corDoDestaque(destaque)}`,
      borderRadius: 12,
      padding: '1.5rem 1.75rem',
    }}>
      <p style={{
        margin: 0,
        fontSize: 'clamp(1.25rem, 2.2vw, 1.9rem)',
        fontWeight: 700,
        color: '#0f172a',
        lineHeight: 1.25,
      }}>
        {titulo}
      </p>

      {detalhe && (
        <p style={{ margin: '0.6rem 0 0 0', color: '#475569', fontSize: '0.95rem' }}>{detalhe}</p>
      )}

      <p style={{ margin: '0.75rem 0 0 0', color: '#94a3b8', fontSize: '0.78rem' }}>
        {olhando
          ? 'Tire o cursor da zona para voltar à leitura do período.'
          : 'Passe o cursor por uma zona do mapa ou da lista para ela se explicar.'}
      </p>
    </section>
  );
}

function corDoDestaque(vezes) {
  if (!vezes) return '#94a3b8';
  if (vezes >= 1.6) return '#BD0026';
  if (vezes >= 1.15) return '#FD8D3C';
  if (vezes >= 0.85) return '#FECC5C';
  return '#38bdf8';
}
