import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import Header from './Header';
import Panorama from './Panorama';
import Frase from './Frase';
import Indicadores from './Indicadores';
import BarraDoDia from './BarraDoDia';
import MallMap from './MallMap';
import Ranking from './Ranking';
import AreaDetails from './AreaDetails';
import PeriodoSelector from './PeriodoSelector';
import JanelaSelector from './JanelaSelector';
import BotaoAoVivo from './BotaoAoVivo';

// De cima para baixo, cada faixa responde uma pergunta:
//   0. a abertura escura diz o que se repete toda semana, antes de qualquer filtro;
//   1. a barra de filtros escolhe o recorte, e fica grudada no topo ao rolar;
//   2. o hero diz a conclusão do recorte, com os quatro números ao lado;
//   3. a barra do dia mostra a forma do dia e deixa escolher o instante;
//   4. mapa e ranking provam: onde, e quanto. O mapa tem os dois pavimentos em abas.
//
// As mesmas peças valem no modo ao vivo. O que muda é o papel delas: a barra do dia
// deixa de ser o seletor e passa a ser o histórico ao lado do agora, e clicar nela sai
// do ao vivo para aquele horário.

export default function Dashboard() {
  const { state } = useContext(DashboardContext);

  return (
    <div className="pagina">
      <Header />

      <main className="conteudo">
        <Panorama />

        <div className="barra-filtros">
          <BotaoAoVivo />

          {state.aoVivo ? (
            <span style={{ fontSize: '0.8rem', color: 'var(--tinta-3)' }}>
              Mostrando o que as câmeras veem agora. Desligue para voltar ao histórico por período.
            </span>
          ) : (
            <>
              <PeriodoSelector />
              <JanelaSelector />
            </>
          )}
        </div>

        <div className="hero">
          <Frase />
          <Indicadores />
        </div>

        <BarraDoDia />

        <div className="colunas">
          <MallMap />
          <Ranking />
        </div>

        {state.zonaSelecionada && <AreaDetails />}
      </main>
    </div>
  );
}
