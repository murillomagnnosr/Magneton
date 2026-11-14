import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { Crown, Gauge, TrendingUp, Users } from 'lucide-react';
import { numero, pessoasDe, vezesAMedia } from '../api';

// Os quatro números que respondem "e aí, como está?" sem precisar ler o mapa.
//
// Ficam ao lado da frase, não embaixo: a frase diz o que significa, os números dizem
// quanto é. Separados em cartõezinhos porque ler quatro grandezas numa linha de texto
// corrida é pior do que ler quatro blocos.
//
// Tudo aqui sai do que já está na tela, nenhuma chamada nova: soma das zonas publicadas,
// densidade do piso, líder do ranking e pico do dia. Zona suprimida não entra na soma,
// então o total é "o que a API publica", não "o que há no shopping" — e o rodapé diz isso
// quando falta alguma.

function Indicador({ icone: Icone, rotulo, valor, unidade, texto = false }) {
  return (
    <div className="indicador">
      <span className="indicador__rotulo">
        <Icone size={13} /> {rotulo}
      </span>

      <span>
        <span className={texto ? 'indicador__valor indicador__valor--texto' : 'indicador__valor numero'}>
          {valor}
        </span>
        {unidade && <span className="indicador__unidade"> {unidade}</span>}
      </span>
    </div>
  );
}

export default function Indicadores() {
  const { state } = useContext(DashboardContext);

  if (state.zonas.length === 0) return null;

  const publicados = state.zonas
    .map((zona) => ({ zona, publicado: state.porZona[zona.id] }))
    .filter(({ publicado }) => pessoasDe(publicado) !== null);

  const total = publicados.reduce((soma, { publicado }) => soma + pessoasDe(publicado), 0);

  const lider = publicados
    .map(({ zona, publicado }) => ({ zona, vezes: vezesAMedia(publicado, state.densidadeDoPiso) }))
    .filter(({ vezes }) => vezes !== null)
    .sort((a, b) => b.vezes - a.vezes)[0];

  const semNumero = state.zonas.length - publicados.length;
  const pico = state.diaInteiro?.pico;

  return (
    <div>
      <div className="indicadores">
        <Indicador
          icone={Users}
          rotulo={state.aoVivo ? 'No piso agora' : 'No piso'}
          valor={numero(total, state.janela || state.aoVivo ? 0 : 1)}
          unidade="pessoas"
        />

        <Indicador
          icone={Gauge}
          rotulo="Densidade do piso"
          valor={state.densidadeDoPiso === null ? '—' : numero(state.densidadeDoPiso, 2)}
          unidade="por 100 m²"
        />

        <Indicador
          icone={Crown}
          rotulo="Zona mais cheia"
          valor={lider ? lider.zona.nome : '—'}
          unidade={lider ? `${numero(lider.vezes, 1)}× a média` : null}
          texto
        />

        <Indicador
          icone={TrendingUp}
          rotulo="Pico do dia"
          valor={pico ? pico.janela.split('–')[0] : '—'}
          unidade={pico ? `${numero(pico.pessoas_no_piso, 0)} pessoas` : null}
        />
      </div>

      {semNumero > 0 && (
        <p className="dica" style={{ marginTop: 'var(--e2)' }}>
          {semNumero === 1
            ? '1 zona ficou abaixo do limiar e não entra nestes totais.'
            : `${semNumero} zonas ficaram abaixo do limiar e não entram nestes totais.`}
        </p>
      )}
    </div>
  );
}
