import React, { useEffect, useState } from 'react';
import { CalendarDays, Clock, TrendingDown, TrendingUp } from 'lucide-react';
import { api, CLASSES, data, hora, numero, variacao } from '../api';

// O relatório de um intervalo: um dia, um mês ou o que a pessoa escolher.
//
// É a mesma tela para os três, porque é a mesma conta no servidor — o que muda é o
// intervalo pedido. O que a tela decide é o que vale mostrar: com um dia só, a curva
// das horas; com vários, a série dos dias também.

function Bloco({ titulo, children }) {
  return (
    <div>
      <p className="campo-dado__rotulo">{titulo}</p>
      {children}
    </div>
  );
}

function SeloDeVariacao({ valor }) {
  if (valor === null || valor === undefined) return null;

  const classe = Math.abs(valor) < 0.01 ? 'igual' : valor > 0 ? 'sobe' : 'desce';
  const Icone = valor > 0 ? TrendingUp : TrendingDown;

  return (
    <span className={`selo-variacao selo-variacao--${classe}`}>
      {classe !== 'igual' && <Icone size={13} />} {variacao(valor)}
    </span>
  );
}

/** Barras de um dia por coluna. Serve para ver a forma do mês de relance. */
function SerieDosDias({ dias }) {
  const maior = Math.max(...dias.map((d) => d.pessoas_media), 1);
  const largura = 1000;
  const altura = 90;
  const passo = largura / dias.length;

  return (
    <div>
      <svg viewBox={`0 0 ${largura} ${altura}`} style={{ width: '100%', height: 96, display: 'block' }}
        role="img" aria-label="Média de pessoas no piso em cada dia do intervalo">
        {dias.map((d, i) => {
          const h = Math.max(2, (d.pessoas_media / maior) * (altura - 10));
          const fimDeSemana = d.dia_da_semana === 'sábado' || d.dia_da_semana === 'domingo';

          return (
            <g key={d.dia}>
              <rect
                x={i * passo + 1}
                y={altura - h}
                width={Math.max(1, passo - 2)}
                height={h}
                rx={2}
                fill={fimDeSemana ? '#e11d48' : '#2563eb'}
                opacity={fimDeSemana ? 0.9 : 0.75}
              />
              <title>{`${data(d.dia)} (${d.dia_da_semana}): ${numero(d.pessoas_media, 0)} pessoas por janela`}</title>
            </g>
          );
        })}
      </svg>

      <div className="numero" style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.7rem', color: 'var(--tinta-4)' }}>
        <span>{data(dias[0].dia)}</span>
        <span style={{ color: 'var(--vivo)' }}>■ fim de semana</span>
        <span>{data(dias[dias.length - 1].dia)}</span>
      </div>
    </div>
  );
}

/** A curva média das horas: a forma do dia típico do intervalo. */
function CurvaDasHoras({ horas }) {
  const maior = Math.max(...horas.map((h) => h.pessoas_media), 1);
  const largura = 1000;
  const altura = 110;
  const passo = largura / Math.max(1, horas.length - 1);
  const emY = (pessoas) => altura - (pessoas / maior) * (altura - 14) - 7;
  const pontos = horas.map((h, i) => `${i * passo},${emY(h.pessoas_media)}`).join(' ');
  const area = `0,${altura} ${pontos} ${largura},${altura}`;

  return (
    <div>
      <svg viewBox={`0 0 ${largura} ${altura}`} style={{ width: '100%', height: 116, display: 'block' }}
        role="img" aria-label="Média de pessoas no piso em cada horário">
        <defs>
          <linearGradient id="sob-a-curva" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#2563eb" stopOpacity="0.22" />
            <stop offset="100%" stopColor="#2563eb" stopOpacity="0" />
          </linearGradient>
        </defs>

        <polygon points={area} fill="url(#sob-a-curva)" />
        <polyline points={pontos} fill="none" stroke="#2563eb" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
      </svg>

      <div className="numero" style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.7rem', color: 'var(--tinta-4)' }}>
        <span>{hora(horas[0].hora)}</span>
        <span>{hora(horas[Math.floor(horas.length / 2)].hora)}</span>
        <span>{hora(horas[horas.length - 1].hora)}</span>
      </div>
    </div>
  );
}

function TabelaDeZonas({ zonas, limiar }) {
  const maior = Math.max(...zonas.map((z) => z.densidade_100m2 ?? 0), 0.1);

  return (
    <table className="tabela-zonas">
      <thead>
        <tr>
          <th>Zona</th>
          <th className="tabela-zonas__oculta-no-celular">Área</th>
          <th>Pessoas</th>
          <th>Por 100 m²</th>
          <th className="tabela-zonas__oculta-no-celular">Fatia do piso</th>
        </tr>
      </thead>

      <tbody>
        {zonas.map((zona) => (
          <tr key={zona.zona_id}>
            <td>
              <span className="tabela-zonas__nome">
                <span style={{ fontWeight: 600 }}>{zona.nome}</span>
                <span className="trilho" style={{ height: 6 }}>
                  <span
                    className="trilho__preenchido"
                    style={{
                      width: zona.densidade_100m2 ? `${(zona.densidade_100m2 / maior) * 100}%` : '100%',
                      background: zona.densidade_100m2 ? CLASSES[zona.classe].cor : 'var(--linha)',
                    }}
                  />
                </span>
              </span>
            </td>

            <td className="numero tabela-zonas__oculta-no-celular">{numero(zona.area_m2, 0)} m²</td>

            {zona.pessoas_media === undefined ? (
              <td colSpan={3} className="tabela-zonas__vazio">
                {zona.classe === 'sem_dado'
                  ? 'sem contagem no intervalo'
                  : `menos de ${limiar} pessoas em alguma janela: o número não é publicado`}
              </td>
            ) : (
              <>
                <td className="numero" style={{ fontWeight: 700 }}>{numero(zona.pessoas_media, 1)}</td>
                <td className="numero">{numero(zona.densidade_100m2, 2)}</td>
                <td className="numero tabela-zonas__oculta-no-celular">
                  {numero(zona.participacao * 100, 1)}%
                </td>
              </>
            )}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

export default function RelatorioDoPeriodo({ de, ate }) {
  // A chave do que já chegou. Enquanto ela não for a do intervalo pedido, a tela está
  // carregando — assim não há um setState só para ligar o "carregando".
  const [estado, setEstado] = useState({ chave: null });
  const chave = `${de}|${ate}`;

  useEffect(() => {
    let cancelado = false;

    api
      .relatorio(de, ate)
      .then((corpo) => !cancelado && setEstado({ chave: `${de}|${ate}`, corpo }))
      .catch((erro) => !cancelado && setEstado({ chave: `${de}|${ate}`, erro: erro.message }));

    return () => {
      cancelado = true;
    };
  }, [de, ate]);

  if (estado.chave !== chave) {
    return <div className="cartao" style={{ textAlign: 'center', color: 'var(--tinta-3)' }}>Montando o relatório…</div>;
  }

  if (estado.erro) {
    return <div className="cartao aviso">{estado.erro}</div>;
  }

  const { periodo, totais, comparacao, por_zona: zonas, por_dia: porDia, por_hora: porHora } = estado.corpo;
  const umDia = porDia.length === 1;

  return (
    <div style={{ display: 'grid', gap: 'var(--e3)' }}>
      <section className="cartao">
        <div className="cartao__cabeca" style={{ marginBottom: 'var(--e3)' }}>
          <div>
            <h3 className="cartao__titulo">
              <CalendarDays size={15} color="var(--acento)" />
              {umDia
                ? `${data(periodo.de, true)}`
                : `${data(periodo.de)} a ${data(periodo.ate, true)}`}
            </h3>
            <p className="cartao__nota">
              {periodo.dias_com_dado} {periodo.dias_com_dado === 1 ? 'dia com contagem' : 'dias com contagem'}
              {' · '}{numero(periodo.janelas, 0)} janelas
              {periodo.fonte ? ` · fonte: ${periodo.fonte}` : ' · fontes misturadas'}
            </p>
          </div>

          {comparacao && (
            <div style={{ textAlign: 'right' }}>
              <SeloDeVariacao valor={comparacao.variacao} />
              <div style={{ fontSize: '0.72rem', color: 'var(--tinta-4)', marginTop: 3 }}>
                contra {data(comparacao.de)}–{data(comparacao.ate)}
              </div>
            </div>
          )}
        </div>

        <div className="campos" style={{ marginBottom: 0 }}>
          <Bloco titulo="Média por janela">
            <div className="campo-dado__valor numero">{numero(totais.pessoas_media_por_janela, 1)}</div>
            <div className="campo-dado__abaixo">pessoas no piso</div>
          </Bloco>

          <Bloco titulo="Pico do intervalo">
            <div className="campo-dado__valor numero">{numero(totais.pico.pessoas_no_piso, 0)}</div>
            <div className="campo-dado__abaixo">
              {totais.pico.dia_da_semana}, {data(totais.pico.dia)} às {hora(totais.pico.hora)}
            </div>
          </Bloco>

          <Bloco titulo="Momento mais vazio">
            <div className="campo-dado__valor numero">{numero(totais.vale.pessoas_no_piso, 0)}</div>
            <div className="campo-dado__abaixo">
              {totais.vale.dia_da_semana}, {data(totais.vale.dia)} às {hora(totais.vale.hora)}
            </div>
          </Bloco>

          {comparacao && (
            <Bloco titulo="Intervalo anterior">
              <div className="campo-dado__valor numero">
                {numero(comparacao.pessoas_media_por_janela, 1)}
              </div>
              <div className="campo-dado__abaixo">pessoas por janela</div>
            </Bloco>
          )}
        </div>
      </section>

      {!umDia && (
        <section className="cartao">
          <h3 className="cartao__titulo" style={{ marginBottom: 'var(--e3)' }}>
            <CalendarDays size={15} color="var(--acento)" /> Dia a dia
          </h3>
          <SerieDosDias dias={porDia} />
        </section>
      )}

      <section className="cartao">
        <h3 className="cartao__titulo" style={{ marginBottom: 'var(--e3)' }}>
          <Clock size={15} color="var(--acento)" />
          {umDia ? 'Ao longo do dia' : 'O dia típico do intervalo'}
        </h3>
        <CurvaDasHoras horas={porHora} />
      </section>

      <section className="cartao">
        <h3 className="cartao__titulo" style={{ marginBottom: 'var(--e3)' }}>Por zona</h3>
        <TabelaDeZonas zonas={zonas} limiar={estado.corpo.limiar} />
      </section>
    </div>
  );
}
