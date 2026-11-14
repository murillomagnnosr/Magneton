import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CalendarRange, Flame, Moon, Sparkles, Trophy } from 'lucide-react';
import { hora, numero } from '../api';

// O bloco do topo: o que o histórico inteiro diz, antes de a pessoa filtrar qualquer coisa.
//
// O resto da tela responde sobre um dia. Este bloco responde sobre a semana — que dia
// move mais, a que horas, e qual zona manda em cada dia. É a leitura que um gerente faz
// de pé, sem clicar: "sábado das 12h às 18h", "segunda é do Mall Nordeste às 14h".
//
// Cada coluna da faixa é clicável e leva ao dia mais recente daquele dia da semana, então
// a afirmação do topo pode ser conferida no mapa logo abaixo.

const CURTOS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

/** "segunda-feira" vira "Segunda-feira", para começar frase. */
function maiuscula(texto) {
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function Destaque({ icone: Icone, cor, titulo, detalhe }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'flex-start', gap: 10, padding: '0.7rem 0.9rem',
      background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 10, flex: '1 1 220px',
    }}>
      <Icone size={17} color={cor} style={{ flexShrink: 0, marginTop: 2 }} />
      <div>
        <div style={{ fontSize: '0.86rem', fontWeight: 600, color: '#0f172a' }}>{titulo}</div>
        <div style={{ fontSize: '0.76rem', color: '#64748b', marginTop: 2 }}>{detalhe}</div>
      </div>
    </div>
  );
}

export default function Panorama() {
  const { state, dispatch } = useContext(DashboardContext);
  const panorama = state.panorama;

  if (!panorama) return null;

  const { cobertura, semana, destaques } = panorama;
  const porTipo = Object.fromEntries(destaques.map((d) => [d.tipo, d]));
  const cheio = porTipo.dia_mais_cheio;
  const maior = Math.max(...semana.map((d) => d.pessoas_media));

  // Clicar num dia da semana leva ao dia mais recente daquele dia que tenha contagem.
  const diasPorSemana = new Map();
  for (const dia of state.saude?.dias ?? []) {
    const qual = new Date(`${dia}T12:00`).getDay();
    if (!diasPorSemana.has(qual)) diasPorSemana.set(qual, dia);
  }

  const escolherDiaDaSemana = (qual) => {
    const dia = diasPorSemana.get(qual);
    if (dia) dispatch({ type: 'ESCOLHER_DIA', payload: dia });
  };

  return (
    <section style={{
      background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: 12,
      padding: '1.1rem 1.25rem', display: 'grid', gap: '0.9rem',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '1rem', flexWrap: 'wrap' }}>
        <h2 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: 7 }}>
          <Sparkles size={17} color="#0ea5e9" /> O que a semana mostra
        </h2>
        <span style={{ fontSize: '0.74rem', color: '#94a3b8' }}>
          {cobertura.dias} {cobertura.dias === 1 ? 'dia medido' : 'dias medidos'}
          {cobertura.dias > 1 && ` · ${new Date(`${cobertura.de}T12:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}
            a ${new Date(`${cobertura.ate}T12:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}`}
          {' · '}nenhuma zona abaixo de {panorama.limiar} pessoas entra nestas frases
        </span>
      </div>

      {cheio && (
        <p style={{ margin: 0, fontSize: '1.3rem', lineHeight: 1.35, fontWeight: 700, color: '#0f172a' }}>
          O movimento da semana é{' '}
          <span style={{ color: '#be123c' }}>
            {cheio.nome}, das {hora(cheio.faixa.de)} às {hora(cheio.faixa.ate)}
          </span>
          .{' '}
          <span style={{ fontWeight: 500, fontSize: '0.92rem', color: '#475569' }}>
            Nessa faixa o piso tem {numero(cheio.faixa.pessoas_media, 0)} pessoas em média,
            {' '}{numero(cheio.vezes_a_media, 1)}× o dia comum da semana.
          </span>
        </p>
      )}

      <div style={{ display: 'flex', gap: '0.6rem', flexWrap: 'wrap' }}>
        {porTipo.zona_do_dia && (
          <Destaque
            icone={Flame}
            cor="#ea580c"
            titulo={`${porTipo.zona_do_dia.zona.nome} brilha ${porTipo.zona_do_dia.dia_semana === 0 || porTipo.zona_do_dia.dia_semana === 6 ? 'no' : 'na'} ${porTipo.zona_do_dia.nome}`}
            detalhe={`Por volta das ${hora(porTipo.zona_do_dia.zona.hora)} ela fica
              ${numero(porTipo.zona_do_dia.zona.vezes_a_media, 1)}× acima do próprio normal`}
          />
        )}

        {porTipo.recorde && (
          <Destaque
            icone={Trophy}
            cor="#ca8a04"
            titulo={`Recorde: ${numero(porTipo.recorde.pessoas, 0)} pessoas no piso`}
            detalhe={`${maiuscula(porTipo.recorde.nome)}, ${new Date(`${porTipo.recorde.dia}T12:00`)
              .toLocaleDateString('pt-BR', { day: '2-digit', month: 'long' })}, às ${hora(porTipo.recorde.hora)}`}
          />
        )}

        {porTipo.dia_mais_calmo && (
          <Destaque
            icone={Moon}
            cor="#64748b"
            titulo={`${maiuscula(porTipo.dia_mais_calmo.nome)} é o dia mais calmo`}
            detalhe={`${numero(porTipo.dia_mais_calmo.pessoas_media, 0)} pessoas por janela,
              ${numero(1 / porTipo.dia_mais_calmo.vezes_a_media, 1)}× abaixo do dia comum`}
          />
        )}
      </div>

      <div>
        <div style={{ fontSize: '0.74rem', color: '#94a3b8', marginBottom: 6, display: 'flex', alignItems: 'center', gap: 5 }}>
          <CalendarRange size={13} /> Clique em um dia para abri-lo no mapa
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: `repeat(${semana.length}, minmax(0, 1fr))`, gap: 6 }}>
          {semana.map((dia) => {
            const temDia = diasPorSemana.has(dia.dia_semana);
            const ehOCheio = cheio && dia.dia_semana === cheio.dia_semana;

            return (
              <button
                key={dia.dia_semana}
                onClick={() => escolherDiaDaSemana(dia.dia_semana)}
                disabled={!temDia}
                title={temDia ? `Abrir ${diasPorSemana.get(dia.dia_semana)}` : 'Sem contagem gravada neste dia da semana'}
                style={{
                  display: 'grid', gap: 4, padding: '0.5rem 0.4rem', textAlign: 'left',
                  border: `1px solid ${ehOCheio ? '#fda4af' : '#e2e8f0'}`,
                  background: ehOCheio ? '#fff1f2' : '#ffffff',
                  borderRadius: 9, cursor: temDia ? 'pointer' : 'default',
                  opacity: temDia ? 1 : 0.55, font: 'inherit', color: 'inherit',
                }}
              >
                <span style={{ fontSize: '0.74rem', fontWeight: 700, color: ehOCheio ? '#be123c' : '#334155' }}>
                  {CURTOS[dia.dia_semana]}
                </span>

                <span style={{ display: 'block', height: 6, background: '#e2e8f0', borderRadius: 3, overflow: 'hidden' }}>
                  <span style={{
                    display: 'block', height: '100%', borderRadius: 3,
                    width: `${Math.max(4, (dia.pessoas_media / maior) * 100)}%`,
                    background: ehOCheio ? '#e11d48' : '#94a3b8',
                  }} />
                </span>

                <span style={{ fontSize: '0.7rem', color: '#64748b' }}>
                  {hora(dia.faixa_cheia.de)}–{hora(dia.faixa_cheia.ate)}
                </span>

                <span style={{ fontSize: '0.68rem', color: '#94a3b8', lineHeight: 1.25 }}>
                  {dia.zona_destaque
                    ? `${dia.zona_destaque.nome} · ${hora(dia.zona_destaque.hora)}`
                    : 'sem zona acima do limiar'}
                </span>
              </button>
            );
          })}
        </div>
      </div>
    </section>
  );
}
