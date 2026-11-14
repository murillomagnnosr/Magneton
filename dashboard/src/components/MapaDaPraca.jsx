import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CLASSES, comparacaoEmPalavras, numero } from '../api';

// A praça de alimentação na folha do pavimento dela.
//
// Não é um cartão: é o conteúdo da aba "2º piso" dentro do mapa. Antes isto era um
// cartão solto no fim da página, o que fazia parecer um segundo mapa de um segundo
// lugar. É o mesmo lugar, outro andar — então é uma aba, não outra seção.
//
// No mapa do térreo a praça aparece tracejada, porque ela fica em cima: a posição está
// certa, mas o desenho embaixo é de outro pavimento. Aqui ela está na planta dela, com
// as mesas, e dá para ver onde as pessoas de fato sentam.
//
// É um segundo desenho da mesma zona, não uma zona nova. O número, a área e a densidade
// continuam vindo do registro da Z01.

export default function MapaDaPraca({ planta, zona, vezes }) {
  const { state, escolherZona, sobrevoarZona } = useContext(DashboardContext);

  const publicado = state.porZona[zona.id];
  const classe = publicado?.classe ?? 'sem_dado';
  const selecionada = state.zonaSelecionada === zona.id;
  const emFoco = state.zonaSobrevoada === zona.id;
  const recorte = planta.recorte;
  const [cx, cy] = centro(planta.poligono);

  return (
    <>
      <svg
        viewBox={recorte
          ? `${recorte.x} ${recorte.y} ${recorte.largura} ${recorte.altura}`
          : `0 0 ${planta.largura} ${planta.altura}`}
        style={{ width: '100%', height: 'auto', display: 'block', borderRadius: 'var(--raio)' }}
        role="img"
        aria-label={`Planta do 2º pavimento com a ${zona.nome} destacada`}
      >
        <defs>
          <pattern id="hachura-praca" width="18" height="18" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect width="18" height="18" fill="#eef2f6" />
            <line x1="0" y1="0" x2="0" y2="18" stroke="#94a3b8" strokeWidth="6" />
          </pattern>
        </defs>

        <image href={`/${planta.imagem}`} x="0" y="0" width={planta.largura} height={planta.altura} />
        <rect x="0" y="0" width={planta.largura} height={planta.altura} fill="#ffffff" opacity="0.3" />

        <polygon
          points={planta.poligono.map(([x, y]) => `${x},${y}`).join(' ')}
          fill={classe === 'abaixo_do_limiar' ? 'url(#hachura-praca)' : CLASSES[classe].cor}
          fillOpacity={classe === 'sem_dado' ? 0.35 : 0.45}
          stroke={selecionada || emFoco ? '#0b1220' : '#1d4ed8'}
          strokeWidth={selecionada || emFoco ? 7 : 4}
          onClick={() => escolherZona(selecionada ? null : zona.id)}
          onMouseEnter={() => sobrevoarZona(zona.id)}
          onMouseLeave={() => sobrevoarZona(null)}
          style={{ cursor: 'pointer' }}
        >
          <title>{`${zona.nome} — clique para abrir a curva do dia`}</title>
        </polygon>

        {vezes && (
          <text
            x={cx}
            y={cy}
            textAnchor="middle"
            fontSize="46"
            fontWeight="700"
            fill="#0b1220"
            stroke="#ffffff"
            strokeWidth="7"
            paintOrder="stroke"
            style={{ pointerEvents: 'none' }}
          >
            {numero(vezes, 1)}×
          </text>
        )}
      </svg>

      <div className="legenda">
        <span>
          {comparacaoEmPalavras(vezes)
            ?? (classe === 'abaixo_do_limiar'
              ? `Menos de ${publicado.limiar ?? 5} pessoas: o número não é publicado.`
              : 'Sem contagem para este período.')}
        </span>

        <span className="legenda__nota">
          As lojas em volta não são zonas: só a área de mesas e circulação é medida.
          Área e densidade vêm do registro da zona, medido na planta marcada pelo grupo.
        </span>
      </div>
    </>
  );
}

/** Centro aproximado do polígono, para pousar o rótulo. */
function centro(pontos) {
  const x = pontos.reduce((soma, [px]) => soma + px, 0) / pontos.length;
  const y = pontos.reduce((soma, [, py]) => soma + py, 0) / pontos.length;

  return [x, y];
}
