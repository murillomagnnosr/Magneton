import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CalendarRange, Flame, Moon, Sparkles, Trophy } from 'lucide-react';
import { hora, numero } from '../api';

// A faixa de abertura: o que o histórico inteiro diz, antes de a pessoa filtrar nada.
//
// É a única peça escura do corpo da página, de propósito. Ela abre a leitura e não
// compete com os cartões de dado que vêm depois — quando tudo era cartão branco do
// mesmo tamanho, não havia ordem de leitura nenhuma.
//
// O resto da tela responde sobre um dia. Esta faixa responde sobre a semana: que dia
// move mais, a que horas, e qual zona manda em cada dia. É a leitura que um gerente faz
// de pé, sem clicar. Cada coluna é clicável e leva ao dia mais recente daquele dia da
// semana, então a afirmação do topo pode ser conferida no mapa logo abaixo.

const CURTOS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

/** "segunda-feira" vira "Segunda-feira", para começar frase. */
function maiuscula(texto) {
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function diaEMes(dia) {
  return new Date(`${dia}T12:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function Cartela({ icone: Icone, cor, titulo, detalhe }) {
  return (
    <div className="cartela">
      <Icone size={17} color={cor} style={{ flexShrink: 0, marginTop: 2 }} />
      <div style={{ minWidth: 0 }}>
        <div className="cartela__titulo">{titulo}</div>
        <div className="cartela__detalhe">{detalhe}</div>
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
    <section className="abertura">
      <div className="abertura__topo">
        <h2 className="abertura__etiqueta">
          <Sparkles size={14} /> O que a semana mostra
        </h2>

        <span className="abertura__cobertura">
          {cobertura.dias} {cobertura.dias === 1 ? 'dia medido' : 'dias medidos'}
          {cobertura.dias > 1 && ` · ${diaEMes(cobertura.de)} a ${diaEMes(cobertura.ate)}`}
          <br />
          zona abaixo de {panorama.limiar} pessoas não entra nestas frases
        </span>
      </div>

      {cheio && (
        <p className="abertura__manchete">
          O movimento da semana é{' '}
          <em>{cheio.nome}, das {hora(cheio.faixa.de)} às {hora(cheio.faixa.ate)}</em>.
          <span className="abertura__apoio">
            Nessa faixa o piso tem <span className="numero">{numero(cheio.faixa.pessoas_media, 0)}</span> pessoas
            em média, <span className="numero">{numero(cheio.vezes_a_media, 1)}×</span> o dia comum da semana.
          </span>
        </p>
      )}

      <div className="abertura__destaques">
        {porTipo.zona_do_dia && (
          <Cartela
            icone={Flame}
            cor="#fb923c"
            titulo={`${porTipo.zona_do_dia.zona.nome} brilha ${
              porTipo.zona_do_dia.dia_semana === 0 || porTipo.zona_do_dia.dia_semana === 6 ? 'no' : 'na'
            } ${porTipo.zona_do_dia.nome}`}
            detalhe={`Por volta das ${hora(porTipo.zona_do_dia.zona.hora)} ela fica ${numero(
              porTipo.zona_do_dia.zona.vezes_a_media,
              1
            )}× acima do próprio normal`}
          />
        )}

        {porTipo.recorde && (
          <Cartela
            icone={Trophy}
            cor="#fbbf24"
            titulo={`Recorde: ${numero(porTipo.recorde.pessoas, 0)} pessoas no piso`}
            detalhe={`${maiuscula(porTipo.recorde.nome)}, ${new Date(`${porTipo.recorde.dia}T12:00`)
              .toLocaleDateString('pt-BR', { day: '2-digit', month: 'long' })}, às ${hora(porTipo.recorde.hora)}`}
          />
        )}

        {porTipo.dia_mais_calmo && (
          <Cartela
            icone={Moon}
            cor="#93a7c6"
            titulo={`${maiuscula(porTipo.dia_mais_calmo.nome)} é o dia mais calmo`}
            detalhe={`${numero(porTipo.dia_mais_calmo.pessoas_media, 0)} pessoas por janela, ${numero(
              1 / porTipo.dia_mais_calmo.vezes_a_media,
              1
            )}× abaixo do dia comum`}
          />
        )}
      </div>

      <div className="semana">
        {semana.map((dia) => {
          const temDia = diasPorSemana.has(dia.dia_semana);
          const ehOCheio = cheio && dia.dia_semana === cheio.dia_semana;

          return (
            <button
              key={dia.dia_semana}
              type="button"
              className={ehOCheio ? 'dia-semana dia-semana--cheio' : 'dia-semana'}
              onClick={() => escolherDiaDaSemana(dia.dia_semana)}
              disabled={!temDia}
              title={temDia
                ? `Abrir ${diasPorSemana.get(dia.dia_semana)}`
                : 'Sem contagem gravada neste dia da semana'}
            >
              <span className="dia-semana__nome">{CURTOS[dia.dia_semana]}</span>

              <span className="trilho">
                <span
                  className="trilho__preenchido"
                  style={{ width: `${Math.max(4, (dia.pessoas_media / maior) * 100)}%` }}
                />
              </span>

              <span className="dia-semana__faixa numero">
                {hora(dia.faixa_cheia.de)}–{hora(dia.faixa_cheia.ate)}
              </span>

              <span className="dia-semana__zona">
                {dia.zona_destaque
                  ? `${dia.zona_destaque.nome} · ${hora(dia.zona_destaque.hora)}`
                  : 'sem zona acima do limiar'}
              </span>
            </button>
          );
        })}
      </div>

      <p className="dica" style={{ marginTop: 'var(--e2)' }}>
        <CalendarRange size={13} /> Clique em um dia para abri-lo no mapa
      </p>
    </section>
  );
}
