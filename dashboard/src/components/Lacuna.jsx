import React from 'react';
import { DoorOpen, MapPinned } from 'lucide-react';

// Para que serve o resto da tela, em duas colunas.
//
// O shopping já conta entrada e saída — os contadores de porta existem e funcionam. O
// que ninguém sabe é quantas pessoas ficam, onde ficam e por quanto tempo. Um número de
// entrada diz o tamanho do público do dia; não diz se as pessoas foram até a ala leste
// ou se deram meia-volta depois da praça.
//
// Fica na abertura do relatório e não na tela inicial porque lá ela empurraria o mapa
// para baixo da dobra, e quem abre o painel quer o mapa. Está também no REQUISITOS.md,
// seção 2.4, com a oportunidade de integração que ela abre.

export default function Lacuna() {
  return (
    <section className="lacuna">
      <div className="lacuna__lado">
        <span className="lacuna__rotulo">
          <DoorOpen size={12} style={{ verticalAlign: '-1px' }} /> O shopping já sabe
        </span>
        <p className="lacuna__frase">Quantas pessoas entram e saem.</p>
        <p className="lacuna__nota">
          O contador de porta dá o tamanho do público do dia.
        </p>
      </div>

      <div className="lacuna__lado lacuna__lado--nosso">
        <span className="lacuna__rotulo">
          <MapPinned size={12} style={{ verticalAlign: '-1px' }} /> O que faltava
        </span>
        <p className="lacuna__frase">Quantas ficam, e onde ficam.</p>
        <p className="lacuna__nota">
          Ocupação por zona a cada 15 minutos — sem seguir ninguém: é média de gente no
          espaço, não tempo de estadia de cada pessoa.
        </p>
      </div>
    </section>
  );
}
