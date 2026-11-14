import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { MapPin, TrendingUp } from 'lucide-react';
import { numero } from '../api';

// Detalhe de uma zona: a curva do dia inteiro, vinda de /v1/zonas/{id}/curva.
// Janela suprimida vira lacuna no gráfico, nunca um ponto interpolado.

export default function AreaDetails() {
  const { state, zonaPorId } = useContext(DashboardContext);
  const zona = state.zonaSelecionada ? zonaPorId[state.zonaSelecionada] : null;

  if (!zona) {
    return (
      <section className="cartao">
        <Cabecalho aoVivo={state.aoVivo} />
        <div className="vazio" style={{ minHeight: 160 }}>
          <div>
            <MapPin size={30} style={{ opacity: 0.45 }} />
            <p style={{ marginTop: 'var(--e2)' }}>
              Clique em uma zona da planta para ver a curva do dia e o horário em que ela enche.
            </p>
          </div>
        </div>
      </section>
    );
  }

  const publicado = state.porZona[zona.id];
  const curva = state.curva;

  return (
    <section className="cartao">
      <Cabecalho aoVivo={state.aoVivo} />

      <div className="campos">
        <Campo titulo="Zona">
          <div className="campo-dado__valor">{zona.nome}</div>
          <div className="campo-dado__abaixo">
            {zona.id} · {zona.tipo} · <span className="numero">{numero(zona.area_m2, 0)}</span> m²
          </div>
        </Campo>

        <Campo titulo={state.aoVivo ? 'Agora, pela câmera' : 'Na seleção'}>
          {publicado?.pessoas ?? publicado?.pessoas_media ? (
            <>
              <div className="campo-dado__valor numero">
                {numero(publicado.pessoas ?? publicado.pessoas_media, state.janela || state.aoVivo ? 0 : 1)}
                <span style={{ fontSize: '0.8rem', fontWeight: 500, color: 'var(--tinta-3)' }}>pessoas</span>
              </div>
              <div className="campo-dado__abaixo">
                <span className="numero">{numero(publicado.densidade_100m2, 2)}</span> por 100 m²
                {state.aoVivo && publicado.ha_segundos !== undefined
                  ? ` · medido há ${publicado.ha_segundos}s`
                  : ''}
              </div>
            </>
          ) : (
            <div className="campo-dado__abaixo">
              {state.aoVivo && !publicado?.medindo
                ? 'sem câmera nesta zona agora'
                : 'abaixo do limiar, sem número'}
            </div>
          )}
        </Campo>

        <Campo titulo="Pico do dia nesta zona">
          {curva?.pico ? (
            <>
              <div className="campo-dado__valor">
                <TrendingUp size={16} color="var(--vivo)" />
                <span className="numero">{curva.pico.hora}</span>
              </div>
              <div className="campo-dado__abaixo numero">
                {numero(curva.pico.pessoas, 0)} pessoas
              </div>
            </>
          ) : (
            <div className="campo-dado__abaixo">
              {curva ? 'nenhuma janela passou do limiar' : 'carregando…'}
            </div>
          )}
        </Campo>
      </div>

      {curva && <Curva curva={curva} />}
    </section>
  );
}

function Curva({ curva }) {
  const largura = 1000;
  const altura = 160;
  const pontos = curva.pontos;
  const maior = Math.max(1, ...pontos.map((ponto) => ponto.pessoas ?? 0));
  const passo = largura / Math.max(1, pontos.length - 1);
  const emY = (pessoas) => altura - (pessoas / maior) * (altura - 20) - 10;

  // Trechos contínuos: a linha é cortada onde a janela foi suprimida.
  const trechos = [];
  let atual = [];

  pontos.forEach((ponto, i) => {
    if (ponto.pessoas === null) {
      if (atual.length) trechos.push(atual);
      atual = [];
      return;
    }
    atual.push(`${i * passo},${emY(ponto.pessoas)}`);
  });

  if (atual.length) trechos.push(atual);

  return (
    <div>
      <svg
        viewBox={`0 0 ${largura} ${altura}`}
        style={{ width: '100%', height: 150 }}
        role="img"
        aria-label={`Curva do dia da zona ${curva.nome}`}
      >
        {pontos.map((ponto, i) =>
          ponto.pessoas === null ? (
            <rect key={i} x={i * passo - passo / 2} y={0} width={passo} height={altura} fill="#eef2f6" opacity="0.8" />
          ) : null
        )}

        {trechos.map((trecho, i) => (
          <polyline key={i} points={trecho.join(' ')} fill="none" stroke="#2563eb" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
        ))}

        {curva.pico && (
          <circle
            cx={pontos.findIndex((ponto) => ponto.hora === curva.pico.hora) * passo}
            cy={emY(curva.pico.pessoas)}
            r="6"
            fill="var(--vivo)"
            stroke="#ffffff"
            strokeWidth="2"
          />
        )}
      </svg>

      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--e3)', fontSize: '0.72rem', color: 'var(--tinta-4)' }}>
        <span className="numero">{pontos[0]?.hora}</span>
        <span>
          {curva.janelas_suprimidas > 0
            ? `${curva.janelas_suprimidas} de ${pontos.length} janelas abaixo do limiar (faixas cinzas)`
            : 'nenhuma janela suprimida'}
        </span>
        <span className="numero">{pontos[pontos.length - 1]?.hora}</span>
      </div>
    </div>
  );
}

function Cabecalho({ aoVivo = false }) {
  return (
    <div className="cartao__cabeca" style={{ marginBottom: 'var(--e4)' }}>
      <h2 className="cartao__titulo">
        <MapPin size={15} color="var(--acento)" /> Detalhe da zona
      </h2>

      {aoVivo && (
        <span className="cartao__selo">a curva abaixo é o histórico do dia, não o ao vivo</span>
      )}
    </div>
  );
}

function Campo({ titulo, children }) {
  return (
    <div>
      <p className="campo-dado__rotulo">{titulo}</p>
      {children}
    </div>
  );
}
