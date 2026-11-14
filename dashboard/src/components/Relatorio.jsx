import React, { useContext, useEffect, useMemo, useRef, useState } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { FileBarChart, X } from 'lucide-react';
import Panorama from './Panorama';
import Lacuna from './Lacuna';
import RelatorioDoPeriodo from './RelatorioDoPeriodo';

// Os relatórios, atrás de um botão.
//
// Eles já ocuparam a tela inicial, e isso empurrava o mapa para baixo da dobra. Quem
// abre o painel quer ver o mapa: relatório é consulta, não primeira leitura.
//
// Diário, mensal e personalizado são o mesmo cálculo no servidor, mudando só o
// intervalo — as abas são atalhos para os intervalos mais pedidos, e "Personalizado"
// é o mesmo com as datas na mão. O padrão da semana continua à parte porque responde
// outra pergunta: não "como foi este mês", mas "o que se repete toda semana".
//
// Escolher um dia dentro do padrão da semana fecha a janela, porque a escolha só tem
// efeito no mapa, que está atrás dela.

const ABAS = [
  { id: 'dia', rotulo: 'Dia' },
  { id: 'mes', rotulo: 'Mês' },
  { id: 'personalizado', rotulo: 'Personalizado' },
  { id: 'semana', rotulo: 'Padrão da semana' },
];

/** Soma ou subtrai dias de uma data "AAAA-MM-DD", sem passar por fuso. */
function somarDias(dia, quantos) {
  const [ano, mes, data] = dia.split('-').map(Number);
  const quando = new Date(Date.UTC(ano, mes - 1, data + quantos));

  return quando.toISOString().slice(0, 10);
}

export default function Relatorio() {
  const { state } = useContext(DashboardContext);
  const [aberto, setAberto] = useState(false);
  const [aba, setAba] = useState('dia');
  const [personalizado, setPersonalizado] = useState(null);
  const botaoFechar = useRef(null);
  const diaAoAbrir = useRef(null);

  // O último dia com contagem é a âncora dos atalhos: "mês" é o mês até ele, não até hoje.
  const ultimoDia = state.saude?.dias?.[0] ?? state.dia;

  const intervalo = useMemo(() => {
    if (aba === 'dia') {
      const dia = state.dia ?? ultimoDia;
      return dia ? { de: dia, ate: dia } : null;
    }

    if (aba === 'mes') {
      return ultimoDia ? { de: somarDias(ultimoDia, -29), ate: ultimoDia } : null;
    }

    // enquanto ninguém mexeu nas datas, a aba personalizada começa nos últimos sete dias
    if (personalizado) return personalizado;

    return ultimoDia ? { de: somarDias(ultimoDia, -6), ate: ultimoDia } : null;
  }, [aba, state.dia, ultimoDia, personalizado]);

  // Esc fecha, e enquanto a janela está aberta a página atrás não rola.
  useEffect(() => {
    if (!aberto) return undefined;

    const naTecla = (evento) => evento.key === 'Escape' && setAberto(false);
    const rolagem = document.body.style.overflow;

    document.addEventListener('keydown', naTecla);
    document.body.style.overflow = 'hidden';
    botaoFechar.current?.focus();

    return () => {
      document.removeEventListener('keydown', naTecla);
      document.body.style.overflow = rolagem;
    };
  }, [aberto]);

  // Clicar num dia da semana troca o dia do painel: a janela sai da frente sozinha.
  useEffect(() => {
    if (!aberto) {
      diaAoAbrir.current = state.dia;
      return;
    }

    if (aba === 'semana' && diaAoAbrir.current !== null && state.dia !== diaAoAbrir.current) {
      setAberto(false);
    }
  }, [state.dia, aberto, aba]);

  if (!state.panorama) return null;

  return (
    <>
      <button
        type="button"
        className="botao botao--claro"
        aria-haspopup="dialog"
        aria-expanded={aberto}
        onClick={() => setAberto(true)}
      >
        <FileBarChart size={15} /> <span>Relatórios</span>
      </button>

      {aberto && (
        <div
          className="modal-fundo"
          role="presentation"
          onClick={(evento) => evento.target === evento.currentTarget && setAberto(false)}
        >
          <div className="modal" role="dialog" aria-modal="true" aria-label="Relatórios">
            <div className="modal__barra">
              <div className="relatorio__abas" role="tablist" aria-label="Período do relatório">
                {ABAS.map(({ id, rotulo }) => (
                  <button
                    key={id}
                    type="button"
                    role="tab"
                    className="relatorio__aba"
                    aria-selected={aba === id}
                    onClick={() => setAba(id)}
                  >
                    {rotulo}
                  </button>
                ))}
              </div>

              <button type="button" ref={botaoFechar} className="modal__fechar" onClick={() => setAberto(false)}>
                <X size={15} /> Fechar
              </button>
            </div>

            <Lacuna />

            {aba === 'personalizado' && intervalo && (
              <div className="relatorio__datas">
                <label className="campo">
                  <span className="campo__rotulo">De</span>
                  <input
                    type="date"
                    className="data-campo"
                    value={intervalo.de}
                    max={intervalo.ate}
                    onChange={(evento) =>
                      setPersonalizado({ ...intervalo, de: evento.target.value })
                    }
                  />
                </label>

                <label className="campo">
                  <span className="campo__rotulo">Até</span>
                  <input
                    type="date"
                    className="data-campo"
                    value={intervalo.ate}
                    min={intervalo.de}
                    onChange={(evento) =>
                      setPersonalizado({ ...intervalo, ate: evento.target.value })
                    }
                  />
                </label>

                <span style={{ fontSize: '0.76rem', color: 'var(--tinta-3)' }}>
                  O relatório se refaz a cada mudança de data.
                </span>
              </div>
            )}

            {aba === 'semana' ? (
              <Panorama />
            ) : intervalo ? (
              <RelatorioDoPeriodo de={intervalo.de} ate={intervalo.ate} />
            ) : (
              <div className="cartao aviso">Escolha um intervalo.</div>
            )}
          </div>
        </div>
      )}
    </>
  );
}
