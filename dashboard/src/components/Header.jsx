import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Activity } from 'lucide-react';

// A faixa escura do topo. Só identifica a tela e diz, em uma linha, o que ela é —
// contagem agregada, sem imagem e sem identificação de ninguém.
//
// O selo que dizia "Dados simulados" / "Dados medidos por câmera" foi retirado a pedido.
// Ele atendia ao RF05 e ao RNF07 do REQUISITOS.md ("dado simulado nunca é apresentado
// como real"). A fonte continua no contrato e em /v1/resumo e /v1/saude, então quem
// consome a API sabe a origem; o que sumiu foi o aviso na tela.
//
// O selo "limiar k = 5 · N registros" também saiu, a pedido. O limiar continua escrito
// onde ele muda o que a tela diz: na legenda do mapa, no ranking e no panorama do topo.

export default function Header() {
  const { state } = useContext(DashboardContext);

  return (
    <header className="cabecalho">
      <span className="cabecalho__marca">
        <Activity size={20} color="#7daaff" />
      </span>

      <div style={{ minWidth: 0 }}>
        <h1 className="cabecalho__titulo">Mapa de calor de fluxo de pessoas</h1>
        <p className="cabecalho__nota">
          Contagem agregada por zona e janela de {state.config?.duracao_min ?? 15} minutos.
          Sem imagem e sem identificação de pessoas.
        </p>
      </div>
    </header>
  );
}
