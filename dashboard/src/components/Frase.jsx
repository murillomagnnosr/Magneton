import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { montarFrase } from '../frase';

// A conclusão em uma frase, logo abaixo dos filtros. A regra de leitura mora em ../frase.js.
//
// A faixa colorida da esquerda não é enfeite: ela usa a mesma escala do mapa, então a
// cor da frase e a cor da zona no mapa querem dizer a mesma coisa.

export default function Frase() {
  const { state } = useContext(DashboardContext);
  const { titulo, detalhe, destaque } = montarFrase(state);
  const olhando = state.zonaSobrevoada ?? state.zonaSelecionada;

  return (
    <section className="cartao frase">
      <span className="frase__faixa" style={{ background: corDoDestaque(destaque) }} />

      <p className="frase__titulo">{titulo}</p>

      {detalhe && <p className="frase__detalhe">{detalhe}</p>}

      <p className="frase__dica">
        {olhando
          ? 'Tire o cursor da zona para voltar à leitura do período.'
          : 'Passe o cursor por uma zona do mapa ou da lista para ela se explicar.'}
      </p>
    </section>
  );
}

function corDoDestaque(vezes) {
  if (!vezes) return 'var(--linha-forte)';
  if (vezes >= 1.6) return '#BD0026';
  if (vezes >= 1.15) return '#FD8D3C';
  if (vezes >= 0.85) return '#FECC5C';
  return '#7dd3fc';
}
