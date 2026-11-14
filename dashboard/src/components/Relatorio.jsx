import React, { useContext, useEffect, useRef, useState } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { FileBarChart, X } from 'lucide-react';
import Panorama from './Panorama';
import Lacuna from './Lacuna';

// O relatório da semana, atrás de um botão.
//
// Ele já ocupou a tela inicial inteira, e isso empurrava o mapa para baixo da dobra.
// Quem abre o painel quer ver o mapa: o padrão da semana é consulta, não primeira
// leitura. O conteúdo é o mesmo de antes, só mudou quando aparece.
//
// Escolher um dia dentro do relatório fecha a janela, porque a escolha só tem efeito
// no mapa, que está atrás dela.

export default function Relatorio() {
  const { state } = useContext(DashboardContext);
  const [aberto, setAberto] = useState(false);
  const botaoFechar = useRef(null);
  const diaAoAbrir = useRef(null);

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

    if (diaAoAbrir.current !== null && state.dia !== diaAoAbrir.current) {
      setAberto(false);
    }
  }, [state.dia, aberto]);

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
        <FileBarChart size={15} /> <span>Relatório da semana</span>
      </button>

      {aberto && (
        <div
          className="modal-fundo"
          role="presentation"
          onClick={(evento) => evento.target === evento.currentTarget && setAberto(false)}
        >
          <div className="modal" role="dialog" aria-modal="true" aria-label="Relatório da semana">
            <div className="modal__barra">
              <span className="modal__titulo">Relatório da semana</span>

              <button
                type="button"
                ref={botaoFechar}
                className="modal__fechar"
                onClick={() => setAberto(false)}
              >
                <X size={15} /> Fechar
              </button>
            </div>

            <Lacuna />
            <Panorama />
          </div>
        </div>
      )}
    </>
  );
}
