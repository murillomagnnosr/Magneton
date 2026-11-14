import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';

// Dia e período em uma linha só. Os períodos são uma lista fechada no servidor: não há
// consulta por faixa livre de horário, e cada período tem janelas suficientes para que a
// diferença entre duas consultas não isole ninguém.

const ROTULOS = {
  manha: 'Manhã',
  almoco: 'Almoço',
  tarde: 'Tarde',
  noite: 'Noite',
  dia_inteiro: 'Dia inteiro',
};

export default function PeriodoSelector() {
  const { state, dispatch } = useContext(DashboardContext);
  const periodos = Object.keys(state.config?.periodos ?? {});
  const dias = state.saude?.dias ?? [];

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
      {dias.length > 0 ? (
        <select
          value={state.dia ?? ''}
          onChange={(evento) => dispatch({ type: 'ESCOLHER_DIA', payload: evento.target.value })}
          style={{
            padding: '0.5rem 0.75rem', borderRadius: 8, border: '1px solid #cbd5e1',
            background: '#ffffff', color: '#0f172a', fontSize: '0.85rem', fontWeight: 600,
          }}
        >
          {dias.map((dia) => (
            <option key={dia} value={dia}>
              {new Date(`${dia}T12:00`).toLocaleDateString('pt-BR', {
                weekday: 'long', day: '2-digit', month: 'long',
              })}
            </option>
          ))}
        </select>
      ) : (
        <span style={{ color: '#64748b', fontSize: '0.85rem' }}>Nenhum dia com contagem ainda.</span>
      )}

      <div style={{ display: 'flex', background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: 8, padding: 3, gap: 2 }}>
        {periodos.map((periodo) => {
          const ativo = state.periodo === periodo;

          return (
            <button
              key={periodo}
              onClick={() => dispatch({ type: 'ESCOLHER_PERIODO', payload: periodo })}
              style={{
                padding: '0.4rem 0.8rem',
                border: 'none',
                background: ativo ? '#0f172a' : 'transparent',
                color: ativo ? '#ffffff' : '#64748b',
                borderRadius: 6,
                cursor: 'pointer',
                fontWeight: ativo ? 600 : 500,
                fontSize: '0.82rem',
              }}
            >
              {ROTULOS[periodo] ?? periodo}
            </button>
          );
        })}
      </div>
    </div>
  );
}
