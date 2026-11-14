import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Activity } from 'lucide-react';

// O selo que dizia "Dados simulados" / "Dados medidos por câmera" foi retirado a pedido.
//
// Ele atendia ao RF05 e ao RNF07 do REQUISITOS.md ("dado simulado nunca é apresentado
// como real"). A fonte continua no contrato e em /v1/resumo e /v1/saude, então quem
// consome a API sabe a origem; o que sumiu foi o aviso na tela. Para trazer de volta,
// basta voltar o bloco de selo que estava aqui.
//
// O selo "limiar k = 5 · N registros" também saiu, a pedido. O limiar continua escrito
// onde ele muda o que a tela diz: na legenda do mapa, no ranking e no panorama do topo.

export default function Header() {
  const { state } = useContext(DashboardContext);

  return (
    <header style={{
      background: '#0f172a',
      color: '#f8fafc',
      padding: '1rem 2rem',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: '1rem',
      flexWrap: 'wrap',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <Activity size={24} color="#38bdf8" />
        <div>
          <h1 style={{ margin: 0, fontSize: '1.15rem', fontWeight: 700 }}>Mapa de calor de fluxo de pessoas</h1>
          <p style={{ margin: 0, fontSize: '0.78rem', color: '#94a3b8' }}>
            Contagem agregada por zona e janela de {state.config?.duracao_min ?? 15} minutos.
            Sem imagem e sem identificação de pessoas.
          </p>
        </div>
      </div>

    </header>
  );
}
