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
      <div className="glass-panel" style={painel}>
        <Cabecalho aoVivo={state.aoVivo} />
        <div style={{ textAlign: 'center', padding: '2rem 0', color: '#94a3b8' }}>
          <MapPin size={32} style={{ margin: '0 auto 0.5rem auto', opacity: 0.5 }} />
          <p style={{ margin: 0 }}>Clique em uma zona da planta para ver a curva do dia e o horário em que ela enche.</p>
        </div>
      </div>
    );
  }

  const publicado = state.porZona[zona.id];
  const curva = state.curva;

  return (
    <div className="glass-panel" style={painel}>
      <Cabecalho aoVivo={state.aoVivo} />

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1.5rem', marginBottom: '1.25rem' }}>
        <Campo titulo="Zona">
          <strong style={{ fontSize: '1.1rem', color: '#0f172a' }}>{zona.nome}</strong>
          <div style={{ color: '#64748b', fontSize: '0.8rem' }}>
            {zona.id} · {zona.tipo} · {numero(zona.area_m2, 0)} m²
          </div>
        </Campo>

        <Campo titulo={state.aoVivo ? 'Agora, pela câmera' : 'Na seleção'}>
          {publicado?.pessoas ?? publicado?.pessoas_media ? (
            <>
              <strong style={{ fontSize: '1.1rem', color: '#0f172a' }}>
                {numero(publicado.pessoas ?? publicado.pessoas_media, state.janela || state.aoVivo ? 0 : 1)} pessoas
              </strong>
              <div style={{ color: '#64748b', fontSize: '0.8rem' }}>
                {numero(publicado.densidade_100m2, 2)} por 100 m²
                {state.aoVivo && publicado.ha_segundos !== undefined
                  ? ` · medido há ${publicado.ha_segundos}s`
                  : ''}
              </div>
            </>
          ) : (
            <span style={{ color: '#64748b' }}>
              {state.aoVivo && !publicado?.medindo
                ? 'sem câmera nesta zona agora'
                : 'abaixo do limiar, sem número'}
            </span>
          )}
        </Campo>

        <Campo titulo="Pico do dia nesta zona">
          {curva?.pico ? (
            <>
              <strong style={{ fontSize: '1.1rem', color: '#0f172a', display: 'flex', alignItems: 'center', gap: 6 }}>
                <TrendingUp size={16} color="#ef4444" /> {curva.pico.hora}
              </strong>
              <div style={{ color: '#64748b', fontSize: '0.8rem' }}>
                {numero(curva.pico.pessoas, 0)} pessoas
              </div>
            </>
          ) : (
            <span style={{ color: '#64748b' }}>
              {curva ? 'nenhuma janela passou do limiar' : 'carregando…'}
            </span>
          )}
        </Campo>
      </div>

      {curva && <Curva curva={curva} />}
    </div>
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
      <svg viewBox={`0 0 ${largura} ${altura}`} style={{ width: '100%', height: 150 }} role="img" aria-label={`Curva do dia da zona ${curva.nome}`}>
        {pontos.map((ponto, i) =>
          ponto.pessoas === null ? (
            <rect key={i} x={i * passo - passo / 2} y={0} width={passo} height={altura} fill="#eef2f6" opacity="0.8" />
          ) : null
        )}

        {trechos.map((trecho, i) => (
          <polyline key={i} points={trecho.join(' ')} fill="none" stroke="#2563eb" strokeWidth="3" strokeLinejoin="round" />
        ))}

        {curva.pico && (
          <circle
            cx={pontos.findIndex((ponto) => ponto.hora === curva.pico.hora) * passo}
            cy={emY(curva.pico.pessoas)}
            r="6"
            fill="#ef4444"
            stroke="#ffffff"
            strokeWidth="2"
          />
        )}
      </svg>

      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.7rem', color: '#94a3b8' }}>
        <span>{pontos[0]?.hora}</span>
        <span>
          {curva.janelas_suprimidas > 0
            ? `${curva.janelas_suprimidas} de ${pontos.length} janelas abaixo do limiar (faixas cinzas)`
            : 'nenhuma janela suprimida'}
        </span>
        <span>{pontos[pontos.length - 1]?.hora}</span>
      </div>
    </div>
  );
}

function Cabecalho({ aoVivo = false }) {
  return (
    <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, marginBottom: '1rem', flexWrap: 'wrap' }}>
      <MapPin size={20} color="#3b82f6" style={{ alignSelf: 'center' }} />
      <h3 style={{ margin: 0, color: '#1e293b' }}>Detalhe da zona</h3>
      {aoVivo && (
        <span style={{ fontSize: '0.78rem', color: '#64748b' }}>
          a curva abaixo é o histórico do dia, não o ao vivo
        </span>
      )}
    </div>
  );
}

function Campo({ titulo, children }) {
  return (
    <div>
      <p style={{ margin: '0 0 4px 0', fontSize: '0.8rem', color: '#64748b' }}>{titulo}</p>
      {children}
    </div>
  );
}

const painel = {
  padding: '1.5rem',
  borderRadius: 12,
  boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)',
};
