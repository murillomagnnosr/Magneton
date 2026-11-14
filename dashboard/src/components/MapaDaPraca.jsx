import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CLASSES, comparacaoEmPalavras, numero, pessoasDe, vezesAMedia } from '../api';
import { Layers } from 'lucide-react';

// A praça de alimentação na folha dela, em cima do mapa do térreo — não no lugar dele.
//
// No mapa principal a praça aparece tracejada sobre a planta do térreo, porque ela fica
// no pavimento de cima: a posição está certa, mas o desenho embaixo é de outro andar. A
// folha 2/2 é o pavimento dela inteiro, em escala maior, e aí dá para ver onde as
// pessoas de fato sentam.
//
// É um segundo desenho da mesma zona, não uma zona nova. O número, a área e a densidade
// continuam vindo do registro da Z01; esta tela só muda o fundo e o contorno.

export default function MapaDaPraca() {
  const { state, escolherZona, sobrevoarZona } = useContext(DashboardContext);
  const planta = state.config?.planta_praca;

  if (!planta) return null;

  const zona = state.zonas.find((z) => z.id === planta.zona_id);

  if (!zona) return null;

  const publicado = state.porZona[zona.id];
  const classe = publicado?.classe ?? 'sem_dado';
  const vezes = vezesAMedia(publicado, state.densidadeDoPiso);
  const pessoas = pessoasDe(publicado);
  const selecionada = state.zonaSelecionada === zona.id;
  const emFoco = state.zonaSobrevoada === zona.id;
  const recorte = planta.recorte;

  return (
    <section style={{ background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: 12, padding: '1.25rem 1.5rem' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', flexWrap: 'wrap' }}>
        <div style={{ minWidth: 0 }}>
          <h2 style={{ margin: '0 0 0.35rem 0', fontSize: '0.95rem', color: '#334155', display: 'flex', alignItems: 'center', gap: 7 }}>
            <Layers size={16} color="#1d4ed8" /> {zona.nome}, de perto
          </h2>
          <p style={{ margin: '0 0 0.9rem 0', fontSize: '0.78rem', color: '#64748b' }}>
            {planta.descricao} No mapa do térreo esta área aparece tracejada, por ser de
            outro pavimento; aqui ela está na planta dela.
          </p>
        </div>

        <div style={{ textAlign: 'right', flexShrink: 0 }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: '#0f172a', lineHeight: 1.1 }}>
            {classe === 'abaixo_do_limiar'
              ? `menos de ${publicado.limiar ?? 5}`
              : pessoas === null ? '—' : numero(pessoas, state.aoVivo ? 0 : 1)}
          </div>
          <div style={{ fontSize: '0.75rem', color: '#64748b' }}>
            {state.aoVivo ? 'pessoas agora' : 'pessoas na média do período'}
          </div>
        </div>
      </div>

      <svg
        viewBox={recorte
          ? `${recorte.x} ${recorte.y} ${recorte.largura} ${recorte.altura}`
          : `0 0 ${planta.largura} ${planta.altura}`}
        style={{ width: '100%', height: 'auto', display: 'block', background: '#ffffff' }}
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
          stroke={selecionada || emFoco ? '#0f172a' : '#1d4ed8'}
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
            x={centro(planta.poligono)[0]}
            y={centro(planta.poligono)[1]}
            textAnchor="middle"
            fontSize="46"
            fontWeight="700"
            fill="#0f172a"
            stroke="#ffffff"
            strokeWidth="7"
            paintOrder="stroke"
            style={{ pointerEvents: 'none' }}
          >
            {numero(vezes, 1)}×
          </text>
        )}
      </svg>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem', marginTop: '0.9rem', fontSize: '0.75rem', color: '#475569' }}>
        <span>
          {comparacaoEmPalavras(vezes)
            ?? (classe === 'abaixo_do_limiar'
              ? `Menos de ${publicado.limiar ?? 5} pessoas: o número não é publicado.`
              : 'Sem contagem para este período.')}
        </span>

        <span style={{ color: '#94a3b8' }}>
          As lojas em volta não são zonas: só a área de mesas e circulação é medida.
          Área e densidade vêm do registro da zona, medido na planta marcada pelo grupo.
        </span>
      </div>
    </section>
  );
}

/** Centro aproximado do polígono, para pousar o rótulo. */
function centro(pontos) {
  const x = pontos.reduce((soma, [px]) => soma + px, 0) / pontos.length;
  const y = pontos.reduce((soma, [, py]) => soma + py, 0) / pontos.length;

  return [x, y];
}
