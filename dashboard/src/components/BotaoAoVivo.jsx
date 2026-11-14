import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Radio } from 'lucide-react';

// Alterna entre as duas leituras do mesmo sistema:
//   histórico — janelas de 15 min, o que sustenta a análise;
//   ao vivo   — o que as câmeras veem agora, para operação.
//
// São coisas diferentes e a tela diz qual está mostrando, para ninguém confundir uma
// medição instantânea com a média de um período.

export default function BotaoAoVivo() {
  const { state, dispatch } = useContext(DashboardContext);
  const ligado = state.aoVivo;
  const medindo = state.vivo?.zonas_medindo ?? 0;

  return (
    <button
      onClick={() => dispatch({ type: 'ALTERNAR_AO_VIVO' })}
      style={{
        display: 'flex', alignItems: 'center', gap: 8,
        padding: '0.45rem 0.9rem', borderRadius: 999, cursor: 'pointer',
        fontWeight: 600, fontSize: '0.82rem',
        border: `1px solid ${ligado ? '#ef4444' : '#cbd5e1'}`,
        background: ligado ? '#ef4444' : '#ffffff',
        color: ligado ? '#ffffff' : '#475569',
      }}
    >
      <Radio size={15} className={ligado ? 'pulsando' : undefined} />
      {ligado
        ? `Ao vivo · ${medindo} ${medindo === 1 ? 'câmera' : 'câmeras'}`
        : 'Ver ao vivo'}
    </button>
  );
}
