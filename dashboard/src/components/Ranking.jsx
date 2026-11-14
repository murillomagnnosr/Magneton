import React, { useContext } from 'react';
import { DashboardContext } from '../context/DashboardContext';
import { CLASSES, numero, pessoasDe, vezesAMedia } from '../api';

// O mapa responde onde; esta lista responde quanto.
//
// Comparar comprimento de barra é instantâneo. Comparar tons de laranja no mapa não é,
// e é por isso que as duas coisas aparecem lado a lado em vez de só o mapa.

/** O limiar em vigor na tela: o do ao vivo pode ser diferente do histórico. */
function limiarDaTela(state) {
  if (state.aoVivo) return state.vivo?.limiar ?? state.config?.limiar_ao_vivo ?? 5;

  return state.resumo?.limiar ?? state.config?.k ?? 5;
}

/** Ao vivo o que importa é quantas pessoas há agora, não a comparação com a média. */
function AoVivoValor({ publicado }) {
  if (publicado?.pessoas !== undefined) {
    return (
      <>
        <strong className="numero">{publicado.pessoas}</strong>
        <span className="zona-linha__unidade">
          {publicado.pessoas === 1 ? 'pessoa' : 'pessoas'}
        </span>
      </>
    );
  }

  return (
    <span className="zona-linha__vazio">
      {publicado?.medindo ? `medindo, menos de ${publicado.limiar}` : 'sem câmera'}
    </span>
  );
}

export default function Ranking() {
  const { state, escolherZona, sobrevoarZona } = useContext(DashboardContext);
  const piso = state.densidadeDoPiso;

  if (state.zonas.length === 0) return null;

  const linhas = state.zonas
    .map((zona) => {
      const publicado = state.porZona[zona.id];
      return {
        zona,
        publicado,
        vezes: vezesAMedia(publicado, piso),
        pessoas: pessoasDe(publicado),
      };
    })
    // zonas sem número vão para o fim, para que a posição não revele o valor
    .sort((a, b) => (b.vezes ?? -1) - (a.vezes ?? -1) || a.zona.nome.localeCompare(b.zona.nome));

  const maior = Math.max(1.2, ...linhas.map((linha) => linha.vezes ?? 0));
  const suprimidas = linhas.filter((linha) => linha.publicado?.classe === 'abaixo_do_limiar').length;

  return (
    <section className="cartao">
      <div className="cartao__cabeca" style={{ marginBottom: 'var(--e3)', display: 'block' }}>
        <h2 className="cartao__titulo">Da mais cheia para a mais vazia</h2>
        <p className="cartao__nota">
          {state.aoVivo
            ? 'Quantas pessoas há agora em cada zona. Só aparecem as zonas com câmera.'
            : `A barra compara a zona com a média do piso${
                piso ? `, que é de ${numero(piso, 2)} pessoas por 100 m²` : ''
              }.`}
        </p>
      </div>

      {suprimidas >= 2 && (
        <p className="aviso" style={{ marginBottom: 'var(--e3)' }}>
          <strong>{suprimidas} {suprimidas === 1 ? 'zona sem número' : 'zonas sem número'}.</strong>{' '}
          {state.aoVivo
            ? `A câmera está medindo, mas há menos de ${limiarDaTela(state)} pessoas e o número
               não é publicado. O mesmo limiar vale aqui e no histórico.`
            : `Basta uma janela de ${state.config?.duracao_min ?? 15} minutos com menos de
               ${limiarDaTela(state)} pessoas para a zona perder o número no período inteiro${
              state.periodo === 'dia_inteiro' ? ', e num dia inteiro isso acontece quase sempre de manhã' : ''
            }. É a regra que impede reconstruir a janela vazia por diferença.`}
        </p>
      )}

      <ul className="lista-zonas">
        {linhas.map(({ zona, publicado, vezes, pessoas }, posicao) => {
          const selecionada = state.zonaSelecionada === zona.id;
          const classe = publicado?.classe ?? 'sem_dado';

          return (
            <li key={zona.id}>
              <button
                type="button"
                className="zona-linha"
                aria-pressed={selecionada}
                onClick={() => escolherZona(selecionada ? null : zona.id)}
                onMouseEnter={() => sobrevoarZona(zona.id)}
                onMouseLeave={() => sobrevoarZona(null)}
              >
                <span className="zona-linha__posicao numero">{posicao + 1}</span>

                <span style={{ display: 'block', minWidth: 0 }}>
                  <span className="zona-linha__nome">{zona.nome}</span>

                  <span className="trilho" style={{ height: 8, marginTop: 5 }}>
                    <span
                      className="trilho__preenchido"
                      style={{
                        width: vezes ? `${Math.min(100, (vezes / maior) * 100)}%` : '100%',
                        background: vezes ? CLASSES[classe].cor : 'var(--linha)',
                      }}
                    />
                  </span>
                </span>

                <span className="zona-linha__valor">
                  {state.aoVivo ? (
                    <AoVivoValor publicado={publicado} />
                  ) : vezes ? (
                    <>
                      <strong className="numero">{numero(vezes, 1)}×</strong>
                      <span className="zona-linha__unidade numero">
                        {numero(pessoas, state.janela ? 0 : 1)} pessoas
                      </span>
                    </>
                  ) : (
                    <span className="zona-linha__vazio">
                      {classe === 'abaixo_do_limiar' ? `menos de ${state.resumo?.limiar ?? 5}` : 'sem dado'}
                    </span>
                  )}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
