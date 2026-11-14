import React, { useContext, useState } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CLASSES, numero, pessoasDe, vezesAMedia } from '../api';
import { useCampoDeCalor } from '../campoDeCalor';
import MapaDaPraca from './MapaDaPraca';
import { Building2, Utensils } from 'lucide-react';

// A planta real do shopping, com as zonas de circulação por cima.
//
// A imagem de fundo e o sistema de coordenadas vêm de /v1/config; os polígonos vêm de
// /v1/zonas, no mesmo sistema. Esta tela não tem nenhuma coordenada escrita à mão:
// trocar a planta é trocar o arquivo de zonas no servidor.
//
// O shopping tem dois pavimentos e o mapa tem duas abas, não dois cartões. A praça de
// alimentação já era desenhada aqui, tracejada, por ficar no andar de cima; a aba leva
// à folha dela, em escala maior. Como cartão separado no fim da página, parecia um
// segundo mapa de um segundo lugar — e é o mesmo lugar, outro andar.

export default function MallMap() {
  const { state, escolherZona, sobrevoarZona } = useContext(DashboardContext);
  const [suavizado, setSuavizado] = useState(true);
  const [pavimento, setPavimento] = useState('terreo');
  const planta = state.config?.planta;
  const plantaDaPraca = state.config?.planta_praca;
  const zonaDaPraca = plantaDaPraca
    ? state.zonas.find((zona) => zona.id === plantaDaPraca.zona_id)
    : null;
  const naPraca = pavimento === 'praca' && zonaDaPraca;

  const campo = useCampoDeCalor(
    state.zonas,
    state.porZona,
    planta,
    state.config?.suavizacao_m ?? 8
  );

  if (state.carregando) return <Aviso texto="Carregando a planta e as contagens…" />;
  if (state.erro) return <Aviso texto={state.erro} erro />;
  if (state.zonas.length === 0) return <Aviso texto="A API não devolveu nenhuma zona." erro />;
  if (!planta) return <Aviso texto="A API não informou qual planta desenhar." erro />;

  const publicadoDaPraca = zonaDaPraca ? state.porZona[zonaDaPraca.id] : null;
  const vezesDaPraca = vezesAMedia(publicadoDaPraca, state.densidadeDoPiso);

  return (
    <section className="cartao">
      <div className="cartao__cabeca">
        <div style={{ minWidth: 0 }}>
          <h2 className="cartao__titulo">Onde as pessoas estão</h2>
          <p className="cartao__nota">
            {naPraca
              ? `${plantaDaPraca.descricao} No térreo esta área aparece tracejada, por ser de
                 outro pavimento; aqui ela está na planta dela.`
              : `${planta.descricao} As áreas coloridas são a circulação pública: corredores,
                 praças e entradas. Clique para abrir a curva do dia.`}
          </p>
        </div>

        <div className="ferramentas-mapa">
          {zonaDaPraca && (
            <div className="abas" role="tablist" aria-label="Pavimento">
              <button
                type="button"
                role="tab"
                className="aba"
                aria-selected={pavimento === 'terreo'}
                onClick={() => setPavimento('terreo')}
              >
                <Building2 size={14} /> Térreo
              </button>
              <button
                type="button"
                role="tab"
                className="aba"
                aria-selected={pavimento === 'praca'}
                onClick={() => setPavimento('praca')}
              >
                <Utensils size={14} /> 2º piso
                {pessoasDe(publicadoDaPraca) !== null && (
                  <span className="numero" style={{ color: 'var(--tinta-4)', fontWeight: 600 }}>
                    {numero(pessoasDe(publicadoDaPraca), 0)}
                  </span>
                )}
              </button>
            </div>
          )}

          {!naPraca && (
            <div className="pilulas" role="group" aria-label="Como desenhar as zonas">
              {[['Mapa de calor', true], ['Por zona', false]].map(([rotulo, ligado]) => (
                <button
                  key={rotulo}
                  type="button"
                  className="pilula"
                  aria-pressed={suavizado === ligado}
                  onClick={() => setSuavizado(ligado)}
                >
                  {rotulo}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {naPraca && <MapaDaPraca planta={plantaDaPraca} zona={zonaDaPraca} vezes={vezesDaPraca} />}

      {!naPraca && (
        <>

      <svg
        viewBox={vista(planta)}
        style={{ width: '100%', height: 'auto', display: 'block', borderRadius: 'var(--raio)' }}
        role="img"
        aria-label="Planta do shopping com as zonas de circulação coloridas pela densidade de pessoas"
      >
        <defs>
          {/* Hachura das zonas suprimidas: diferente do cinza liso de "sem dado" */}
          <pattern id="hachura" width="24" height="24" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect width="24" height="24" fill="#eef2f6" />
            <line x1="0" y1="0" x2="0" y2="24" stroke="#94a3b8" strokeWidth="8" />
          </pattern>
        </defs>

        <image href={`/${planta.imagem}`} x="0" y="0" width={planta.largura} height={planta.altura} />

        {/* Véu claro: a planta continua legível, mas as cores saltam */}
        <rect x="0" y="0" width={planta.largura} height={planta.altura} fill="#ffffff" opacity="0.32" />

        {/* O campo suavizado entra por baixo dos polígonos, que viram só contorno */}
        {suavizado && campo && (
          <image href={campo} x="0" y="0" width={planta.largura} height={planta.altura} />
        )}

        {state.zonas.map((zona) => {
          const publicado = state.porZona[zona.id];
          const classe = publicado?.classe ?? 'sem_dado';
          const vezes = vezesAMedia(publicado, state.densidadeDoPiso);
          const selecionada = state.zonaSelecionada === zona.id;
          const emFoco = state.zonaSobrevoada === zona.id;
          const pontos = zona.planta.pontos.map(([x, y]) => `${x},${y}`).join(' ');

          return (
            <polygon
              key={zona.id}
              points={pontos}
              // No mapa de calor a cor é do campo; a zona vira contorno, menos quando
              // está suprimida ou sem dado, que precisam continuar visíveis.
              fill={suavizado && classe !== 'abaixo_do_limiar' && classe !== 'sem_dado'
                ? 'transparent'
                : CLASSES[classe].cor}
              fillOpacity={classe === 'sem_dado' ? 0.45 : suavizado ? 0.75 : 0.82}
              stroke={selecionada || emFoco ? '#0b1220' : outroPiso(zona) ? '#1d4ed8' : '#475569'}
              strokeWidth={selecionada || emFoco ? 10 : outroPiso(zona) ? 6 : suavizado ? 2 : 3}
              // tracejado marca que a zona não está neste pavimento
              strokeDasharray={outroPiso(zona) ? '22 14' : undefined}
              strokeOpacity={suavizado && !(selecionada || emFoco) && !outroPiso(zona) ? 0.5 : 1}
              opacity={state.zonaSobrevoada && !emFoco ? 0.55 : 1}
              onClick={() => escolherZona(selecionada ? null : zona.id)}
              onMouseEnter={() => sobrevoarZona(zona.id)}
              onMouseLeave={() => sobrevoarZona(null)}
              style={{ cursor: 'pointer' }}
            >
              <title>{`${zona.nome} — ${rotuloDaZona(state, publicado, vezes)}`}</title>
            </polygon>
          );
        })}

        {state.zonas.map((zona) => {
          const publicado = state.porZona[zona.id];
          const vezes = vezesAMedia(publicado, state.densidadeDoPiso);
          const [cx, cy] = centro(zona.planta.pontos);
          const destaque = state.zonaSelecionada === zona.id || state.zonaSobrevoada === zona.id;

          return (
            <text
              key={zona.id}
              x={cx}
              y={cy + 10}
              textAnchor="middle"
              fontSize={destaque ? 40 : 32}
              fontWeight="700"
              fill="#0b1220"
              stroke="#ffffff"
              strokeWidth="6"
              paintOrder="stroke"
              style={{ pointerEvents: 'none' }}
            >
              {valorCurto(state, publicado, vezes)}
            </text>
          );
        })}

        {/* Quem está em outro pavimento ganha etiqueta, senão o mapa mente sobre o lugar */}
        {state.zonas.filter(outroPiso).map((zona) => {
          const [cx, cy] = centro(zona.planta.pontos);

          return (
            <text
              key={`piso-${zona.id}`}
              x={cx}
              y={cy + 52}
              textAnchor="middle"
              fontSize="26"
              fontWeight="600"
              fill="#1d4ed8"
              stroke="#ffffff"
              strokeWidth="6"
              paintOrder="stroke"
              style={{ pointerEvents: 'none' }}
            >
              {zona.nome} · {zona.piso}º pavimento
            </text>
          );
        })}
      </svg>

      <Legenda limiar={state.resumo?.limiar} aoVivo={state.aoVivo} suavizado={suavizado} />
        </>
      )}
    </section>
  );
}

/** A zona está em outro pavimento, desenhada sobre a planta deste. */
function outroPiso(zona) {
  return (zona.piso ?? 1) !== 1;
}

/** O viewBox: só o prédio, quando a API diz onde ele está na imagem. */
function vista(planta) {
  const r = planta.recorte;

  return r ? `${r.x} ${r.y} ${r.largura} ${r.altura}` : `0 0 ${planta.largura} ${planta.altura}`;
}

/** Centro aproximado do polígono, para pousar o rótulo. */
function centro(pontos) {
  const x = pontos.reduce((soma, [px]) => soma + px, 0) / pontos.length;
  const y = pontos.reduce((soma, [, py]) => soma + py, 0) / pontos.length;

  return [x, y];
}

/** O número que cabe dentro da zona no mapa. */
function valorCurto(state, publicado, vezes) {
  if (state.aoVivo) {
    if (publicado?.pessoas !== undefined) return String(publicado.pessoas);
    return publicado?.medindo ? `<${publicado.limiar}` : '';
  }

  if (vezes) return `${numero(vezes, 1)}×`;
  if (publicado?.classe === 'abaixo_do_limiar') return `<${state.resumo?.limiar ?? 5}`;

  return '';
}

/** O texto completo, que aparece ao parar o cursor sobre a zona. */
function rotuloDaZona(state, publicado, vezes) {
  if (!publicado || publicado.classe === 'sem_dado') return 'sem câmera medindo';
  if (publicado.classe === 'abaixo_do_limiar') return `menos de ${publicado.limiar ?? 5} pessoas`;
  if (state.aoVivo) return `${publicado.pessoas} pessoas agora`;

  return `${numero(vezes, 1)}× a média do piso`;
}

function Legenda({ limiar, aoVivo, suavizado }) {
  return (
    <div className="legenda">
      <span className="legenda__item">
        mais vazia
        <span className="legenda__escada">
          {['muito_baixa', 'baixa', 'media', 'alta', 'muito_alta'].map((classe) => (
            <i key={classe} className="legenda__degrau" style={{ background: CLASSES[classe].cor }} />
          ))}
        </span>
        mais cheia
      </span>

      <span className="legenda__item">
        <svg width="16" height="16" style={{ borderRadius: 3, border: '1px solid var(--linha-forte)' }}>
          <rect width="16" height="16" fill="#eef2f6" />
          <line x1="0" y1="16" x2="16" y2="0" stroke="#94a3b8" strokeWidth="2.5" />
        </svg>
        menos de {limiar ?? 5} pessoas: o número não é publicado
      </span>

      <span className="legenda__item">
        <i style={{
          width: 16, height: 16, borderRadius: 3,
          background: CLASSES.sem_dado.cor, border: '1px solid var(--linha-forte)',
        }} />
        {aoVivo ? 'sem câmera agora' : 'sem câmera medindo'}
      </span>

      <span className="legenda__item">
        <svg width="22" height="14">
          <rect x="1" y="1" width="20" height="12" fill="none" stroke="#1d4ed8" strokeWidth="2.5" strokeDasharray="5 3" />
        </svg>
        zona de outro pavimento, desenhada sobre a planta deste
      </span>

      <span className="legenda__nota">
        {suavizado
          ? 'A suavização é visual; o dado é por zona. Os contornos vieram da planta marcada pelo grupo e são aproximados.'
          : 'Os contornos vieram da planta marcada pelo grupo e são aproximados.'}
      </span>
    </div>
  );
}

function Aviso({ texto, erro = false }) {
  return (
    <div className={erro ? 'vazio vazio--erro' : 'vazio'}>
      <p style={{ margin: 0, maxWidth: 460, lineHeight: 1.55 }}>{texto}</p>
    </div>
  );
}
