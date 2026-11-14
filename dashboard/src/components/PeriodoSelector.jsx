import React, { useContext, useMemo } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CalendarDays } from 'lucide-react';
import { hora } from '../api';

// Dia e período em uma linha só.
//
// O filtro de dia segue o mesmo formato do de horário: rótulo com ícone à esquerda e
// lista à direita, e cada opção já diz quanto aquele dia moveu. Assim dá para escolher
// o dia cheio sem sair do menu, como na lista de horários.
//
// Os períodos são uma lista fechada no servidor: não há consulta por faixa livre de
// horário, e cada período tem janelas suficientes para que a diferença entre duas
// consultas não isole ninguém.

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

  // Quanto cada dia da semana move, do panorama: serve de etiqueta na lista de dias.
  const mediaPorDiaDaSemana = useMemo(() => {
    const mapa = new Map();

    for (const dia of state.panorama?.semana ?? []) {
      mapa.set(dia.dia_semana, dia);
    }

    return mapa;
  }, [state.panorama]);

  const maisCheio = useMemo(() => {
    const semana = state.panorama?.semana ?? [];
    if (semana.length === 0) return null;

    return semana.reduce((melhor, dia) => (dia.pessoas_media > melhor.pessoas_media ? dia : melhor)).dia_semana;
  }, [state.panorama]);

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
      {dias.length > 0 ? (
        <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#64748b', fontSize: '0.8rem' }}>
            <CalendarDays size={15} /> Dia
          </span>

          <select
            value={state.dia ?? ''}
            onChange={(evento) => dispatch({ type: 'ESCOLHER_DIA', payload: evento.target.value })}
            style={{
              padding: '0.5rem 0.75rem',
              borderRadius: 8,
              border: '1px solid #cbd5e1',
              background: '#ffffff',
              color: '#0f172a',
              fontSize: '0.85rem',
              fontWeight: 600,
              maxWidth: 320,
            }}
          >
            {dias.map((dia) => {
              const quando = new Date(`${dia}T12:00`);
              const daSemana = mediaPorDiaDaSemana.get(quando.getDay());

              return (
                <option key={dia} value={dia}>
                  {quando.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' })}
                  {daSemana ? ` · cheio ${hora(daSemana.faixa_cheia.de)}–${hora(daSemana.faixa_cheia.ate)}` : ''}
                  {daSemana && daSemana.dia_semana === maisCheio ? ' · o dia mais cheio' : ''}
                </option>
              );
            })}
          </select>
        </label>
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
