import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import Header from './Header';
import Frase from './Frase';
import Indicadores from './Indicadores';
import BarraDoDia from './BarraDoDia';
import MallMap from './MallMap';
import Ranking from './Ranking';
import AreaDetails from './AreaDetails';
import PeriodoSelector from './PeriodoSelector';
import JanelaSelector from './JanelaSelector';
import BotaoAoVivo from './BotaoAoVivo';

// A ordem da página é a ordem em que um gerente olha:
//   1. a barra de filtros escolhe o recorte, e fica grudada no topo ao rolar;
//   2. o mapa, logo abaixo dela — é para isso que se abre o painel. A frase vem
//      colada nele, porque é a leitura do que o mapa está mostrando;
//   3. à direita, os quatro números e o ranking, que respondem "quanto";
//   4. a barra do dia, para trocar de instante;
//   5. o detalhe da zona, quando alguma está selecionada.
//
// O padrão da semana saiu daqui para dentro do botão "Relatório": ele ocupava a tela
// inicial inteira e empurrava o mapa para baixo da dobra.
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

        <div className="colunas">
          <div className="coluna-mapa">
            <Frase />
            <MallMap />
          </div>

          <div className="coluna-lado">
            <Indicadores />
            <Ranking />
          </div>
        </div>

        <BarraDoDia />

        {state.zonaSelecionada && <AreaDetails />}
      </main>
    </div>
  );
}
