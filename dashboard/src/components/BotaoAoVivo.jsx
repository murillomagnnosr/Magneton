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
      type="button"
      className={ligado ? 'botao botao--vivo' : 'botao'}
      style={ligado ? undefined : { borderRadius: 'var(--raio-pilula)' }}
      aria-pressed={ligado}
      onClick={() => dispatch({ type: 'ALTERNAR_AO_VIVO' })}
    >
      <Radio size={15} className={ligado ? 'pulsando' : undefined} />
      {ligado
        ? `Ao vivo · ${medindo} ${medindo === 1 ? 'câmera' : 'câmeras'}`
        : 'Ver ao vivo'}
    </button>
  );
}
