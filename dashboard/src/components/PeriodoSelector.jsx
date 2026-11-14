import React, { useContext, useMemo } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CalendarDays } from 'lucide-react';
import { hora } from '../api';

// Dia e período, os dois primeiros filtros da barra.
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
    <>
      {dias.length > 0 ? (
        <label className="campo">
          <span className="campo__rotulo">
            <CalendarDays size={14} /> Dia
          </span>

          <select
            className="selecao"
            value={state.dia ?? ''}
            onChange={(evento) => dispatch({ type: 'ESCOLHER_DIA', payload: evento.target.value })}
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
        <span style={{ fontSize: '0.84rem', color: 'var(--tinta-3)' }}>Nenhum dia com contagem ainda.</span>
      )}

      <div className="pilulas" role="group" aria-label="Período do dia">
        {periodos.map((periodo) => (
          <button
            key={periodo}
            type="button"
            className="pilula"
            aria-pressed={state.periodo === periodo}
            onClick={() => dispatch({ type: 'ESCOLHER_PERIODO', payload: periodo })}
          >
            {ROTULOS[periodo] ?? periodo}
          </button>
        ))}
      </div>
    </>
  );
}
