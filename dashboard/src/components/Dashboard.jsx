import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import Header from './Header';
import Panorama from './Panorama';
import Frase from './Frase';
import BarraDoDia from './BarraDoDia';
import MallMap from './MallMap';
import Ranking from './Ranking';
import AreaDetails from './AreaDetails';
import PeriodoSelector from './PeriodoSelector';
import JanelaSelector from './JanelaSelector';
import BotaoAoVivo from './BotaoAoVivo';

// De cima para baixo, cada faixa responde uma pergunta:
//   0. o panorama diz o que se repete toda semana, antes de qualquer filtro;
//   1. a frase diz a conclusão do dia escolhido;
//   2. a barra mostra a forma do dia e deixa escolher o instante;
//   3. mapa e ranking provam: onde, e quanto.
//
// As mesmas peças valem no modo ao vivo. O que muda é o papel delas: a barra do dia
// deixa de ser o seletor e passa a ser o histórico ao lado do agora, e clicar nela sai
// do ao vivo para aquele horário.

export default function Dashboard() {
  const { state } = useContext(DashboardContext);

  return (
    <div style={{ fontFamily: 'Inter, system-ui, sans-serif', background: '#f1f5f9', minHeight: '100vh', color: '#1e293b' }}>
      <style>{`
        @keyframes pulsar { 0%, 100% { opacity: 1 } 50% { opacity: 0.35 } }
        .pulsando { animation: pulsar 1.4s ease-in-out infinite; }
      `}</style>

      <Header />

      <main style={{ maxWidth: 1400, margin: '0 auto', padding: '1.5rem', display: 'grid', gap: '1.25rem' }}>
        <Panorama />

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
          <BotaoAoVivo />
          {!state.aoVivo && (
            <>
              <PeriodoSelector />
              <JanelaSelector />
            </>
          )}
          {state.aoVivo && (
            <span style={{ fontSize: '0.82rem', color: '#64748b' }}>
              Mostrando o que as câmeras veem agora. Desligue para voltar ao histórico por período.
            </span>
          )}
        </div>

        <Frase />
        <BarraDoDia />

        <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.65fr) minmax(300px, 1fr)', gap: '1.25rem', alignItems: 'start' }}>
          <MallMap />
          <Ranking />
        </div>

        {state.zonaSelecionada && <AreaDetails />}
      </main>
    </div>
  );
}
