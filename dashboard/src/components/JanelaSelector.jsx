import React, { useContext, useMemo } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Clock } from 'lucide-react';
import { numero, rotuloDaJanela } from '../api';

// Filtro de horário, em lista.
//
// A barra do dia é boa para ver a forma do movimento e escolher no olho; esta lista é
// para quando você já sabe a hora que quer. Cada opção mostra quantas pessoas havia no
// piso naquela janela, então a lista também serve de leitura: dá para achar o horário
// cheio sem sair do menu.

export default function JanelaSelector() {
  const { state, dispatch, janelas } = useContext(DashboardContext);

  // Total do piso por janela, para a lista não ser só horários soltos.
  const pessoasPorJanela = useMemo(() => {
    const mapa = new Map();

    for (const janela of state.diaInteiro?.janelas ?? []) {
      mapa.set(janela.hora, janela.pessoas_no_piso);
    }

    return mapa;
  }, [state.diaInteiro]);

  if (janelas.length === 0) return null;

  const maisCheia = janelas.reduce(
    (melhor, hora) => ((pessoasPorJanela.get(hora) ?? -1) > (pessoasPorJanela.get(melhor) ?? -1) ? hora : melhor),
    janelas[0]
  );

  return (
    <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <span style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#64748b', fontSize: '0.8rem' }}>
        <Clock size={15} /> Horário
      </span>

      <select
        value={state.janela ?? ''}
        onChange={(evento) =>
          dispatch({ type: 'ESCOLHER_JANELA', payload: evento.target.value || null })
        }
        style={{
          padding: '0.5rem 0.75rem',
          borderRadius: 8,
          border: '1px solid #cbd5e1',
          background: '#ffffff',
          color: '#0f172a',
          fontSize: '0.85rem',
          fontWeight: state.janela ? 600 : 500,
          maxWidth: 260,
        }}
      >
        <option value="">Média do período ({janelas.length} janelas)</option>

        {janelas.map((hora) => {
          const pessoas = pessoasPorJanela.get(hora);

          return (
            <option key={hora} value={hora}>
              {rotuloDaJanela(hora, state.config?.duracao_min)}
              {pessoas === undefined ? ' · sem contagem' : ` · ${numero(pessoas, 0)} pessoas`}
              {hora === maisCheia && pessoas !== undefined ? ' · pico' : ''}
            </option>
          );
        })}
      </select>
    </label>
  );
}
