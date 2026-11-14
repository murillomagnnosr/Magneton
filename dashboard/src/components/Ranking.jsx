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
        <strong style={{ fontSize: '0.95rem' }}>{publicado.pessoas}</strong>
        <span style={{ display: 'block', color: '#64748b', fontSize: '0.68rem' }}>
          {publicado.pessoas === 1 ? 'pessoa' : 'pessoas'}
        </span>
      </>
    );
  }

  return (
    <span style={{ fontSize: '0.68rem', lineHeight: 1.2, display: 'block' }}>
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
    <section style={{ background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: 12, padding: '1.25rem 1.5rem' }}>
      <h2 style={{ margin: '0 0 0.35rem 0', fontSize: '0.95rem', color: '#334155' }}>
        Zonas, da mais cheia para a mais vazia
      </h2>
      <p style={{ margin: '0 0 0.7rem 0', fontSize: '0.78rem', color: '#64748b' }}>
        {state.aoVivo
          ? 'Quantas pessoas há agora em cada zona. Só aparecem as zonas com câmera.'
          : `A barra compara a zona com a média do piso${piso ? `, que é de ${numero(piso, 2)} pessoas por 100 m²` : ''}.`}
      </p>

      {suprimidas >= 2 && (
        <p style={{
          margin: '0 0 0.9rem 0', padding: '0.6rem 0.75rem', fontSize: '0.74rem',
          color: '#475569', background: '#f8fafc', border: '1px solid #e2e8f0',
          borderRadius: 6, lineHeight: 1.5,
        }}>
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

      <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '0.3rem' }}>
        {linhas.map(({ zona, publicado, vezes, pessoas }) => {
          const selecionada = state.zonaSelecionada === zona.id;
          const emFoco = state.zonaSobrevoada === zona.id;
          const classe = publicado?.classe ?? 'sem_dado';

          return (
            <li key={zona.id}>
              <button
                onClick={() => escolherZona(selecionada ? null : zona.id)}
                onMouseEnter={() => sobrevoarZona(zona.id)}
                onMouseLeave={() => sobrevoarZona(null)}
                style={{
                  width: '100%',
                  display: 'grid',
                  gridTemplateColumns: '1fr 72px',
                  alignItems: 'center',
                  gap: '0.6rem',
                  padding: '0.4rem 0.5rem',
                  border: `1px solid ${selecionada ? '#0f172a' : 'transparent'}`,
                  borderRadius: 6,
                  background: emFoco || selecionada ? '#f1f5f9' : 'transparent',
                  cursor: 'pointer',
                  textAlign: 'left',
                }}
              >
                <span style={{ display: 'block', minWidth: 0 }}>
                  <span style={{
                    display: 'block',
                    fontSize: '0.82rem',
                    color: '#0f172a',
                    fontWeight: selecionada ? 700 : 500,
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                  }}>
                    {zona.nome}
                  </span>

                  <span style={{ display: 'block', height: 8, background: '#f1f5f9', borderRadius: 4, marginTop: 4, overflow: 'hidden' }}>
                    <span style={{
                      display: 'block',
                      height: '100%',
                      width: vezes ? `${Math.min(100, (vezes / maior) * 100)}%` : '100%',
                      background: vezes ? CLASSES[classe].cor : '#e2e8f0',
                      borderRadius: 4,
                    }} />
                  </span>
                </span>

                <span style={{ textAlign: 'right', fontSize: '0.78rem', color: pessoas !== null ? '#0f172a' : '#94a3b8' }}>
                  {state.aoVivo ? (
                    <AoVivoValor publicado={publicado} />
                  ) : vezes ? (
                    <>
                      <strong style={{ fontSize: '0.95rem' }}>{numero(vezes, 1)}×</strong>
                      <span style={{ display: 'block', color: '#64748b', fontSize: '0.68rem' }}>
                        {numero(pessoas, state.janela ? 0 : 1)} pessoas
                      </span>
                    </>
                  ) : (
                    <span style={{ fontSize: '0.68rem', lineHeight: 1.2, display: 'block' }}>
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
