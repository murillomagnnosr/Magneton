import { useEffect, useMemo, useRef, useState } from 'react';
import { CLASSES } from './api';

// Mapa de calor suavizado (REQUISITOS.md, RF15).
//
// A densidade de cada zona é espalhada a partir do centro dela com peso gaussiano:
//
//     d(x) = Σ w_z(x) · d[z]  /  Σ w_z(x),    w_z(x) = exp(-|x - c_z|² / 2σ²)
//
// Só entram as zonas com número publicado. Zona abaixo do limiar fica de fora da conta,
// então o campo não vaza o valor que a camada de publicação suprimiu.
//
// A suavização é só visual: o dado continua tendo resolução de zona, e a tela diz isso.

// Lado da célula de cálculo, em pixels da planta. Grosso de propósito: o navegador
// interpola ao ampliar, e sai igual por uma fração do custo.
const PASSO = 14;

/** As cinco classes de densidade viram a rampa de cor do campo. */
const RAMPA = ['muito_baixa', 'baixa', 'media', 'alta', 'muito_alta'].map((classe) => {
  const hex = CLASSES[classe].cor;

  return [
    parseInt(hex.slice(1, 3), 16),
    parseInt(hex.slice(3, 5), 16),
    parseInt(hex.slice(5, 7), 16),
  ];
});

/** Interpola a rampa: 0 é a classe mais vazia, 1 a mais cheia. */
function corDa(fracao) {
  const posicao = Math.min(0.9999, Math.max(0, fracao)) * (RAMPA.length - 1);
  const i = Math.floor(posicao);
  const t = posicao - i;
  const [r1, g1, b1] = RAMPA[i];
  const [r2, g2, b2] = RAMPA[Math.min(i + 1, RAMPA.length - 1)];

  return [r1 + (r2 - r1) * t, g1 + (g2 - g1) * t, b1 + (b2 - b1) * t];
}

/** Centro do polígono da zona, em coordenadas da planta. */
function centroDa(zona) {
  const pontos = zona.planta.pontos;

  return [
    pontos.reduce((soma, [x]) => soma + x, 0) / pontos.length,
    pontos.reduce((soma, [, y]) => soma + y, 0) / pontos.length,
  ];
}

/**
 * Devolve o campo de calor como data URL, pronto para o SVG desenhar, ou null quando
 * nenhuma zona tem número publicado.
 */
export function useCampoDeCalor(zonas, porZona, planta, suavizacaoM) {
  const tela = useRef(null);
  const [url, setUrl] = useState(null);

  const fontes = useMemo(
    () =>
      zonas
        .map((zona) => ({ zona, publicado: porZona[zona.id] }))
        .filter(({ publicado }) => publicado?.densidade_100m2 !== undefined)
        .map(({ zona, publicado }) => {
          const [x, y] = centroDa(zona);

          return { x, y, densidade: publicado.densidade_100m2 };
        }),
    [zonas, porZona]
  );

  useEffect(() => {
    if (!planta || fontes.length === 0) {
      setUrl(null);

      return;
    }

    if (!tela.current) tela.current = document.createElement('canvas');

    const canvas = tela.current;
    const largura = Math.ceil(planta.largura / PASSO);
    const altura = Math.ceil(planta.altura / PASSO);
    canvas.width = largura;
    canvas.height = altura;

    const pincel = canvas.getContext('2d');
    const imagem = pincel.createImageData(largura, altura);

    const sigma = suavizacaoM * (planta.pixels_por_metro ?? 11.3);
    const doisSigmaAoQuadrado = 2 * sigma * sigma;
    // Além de três sigmas o peso é desprezível; cortar aí evita varrer todas as zonas.
    const alcance = (3 * sigma) ** 2;
    const maior = Math.max(...fontes.map((fonte) => fonte.densidade));

    for (let linha = 0; linha < altura; linha++) {
      for (let coluna = 0; coluna < largura; coluna++) {
        const x = coluna * PASSO;
        const y = linha * PASSO;

        let soma = 0;
        let pesos = 0;
        let maisProxima = Infinity;

        for (const fonte of fontes) {
          const distancia = (x - fonte.x) ** 2 + (y - fonte.y) ** 2;

          if (distancia < maisProxima) maisProxima = distancia;
          if (distancia > alcance) continue;

          const peso = Math.exp(-distancia / doisSigmaAoQuadrado);
          soma += peso * fonte.densidade;
          pesos += peso;
        }

        if (pesos === 0) continue;

        const [r, g, b] = corDa(soma / pesos / maior);
        // Longe de qualquer zona o campo desaparece: não se pinta o que ninguém mediu.
        const desvanece = Math.min(1, Math.exp(-maisProxima / doisSigmaAoQuadrado) * 1.6);
        const indice = (linha * largura + coluna) * 4;

        imagem.data[indice] = r;
        imagem.data[indice + 1] = g;
        imagem.data[indice + 2] = b;
        imagem.data[indice + 3] = Math.round(225 * desvanece);
      }
    }

    pincel.putImageData(imagem, 0, 0);
    setUrl(canvas.toDataURL());
  }, [fontes, planta, suavizacaoM]);

  return url;
}
