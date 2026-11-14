"""Le a planta pintada pelo grupo e devolve as zonas no sistema da planta do projeto.

O grupo pintou de verde toda a circulacao que existe e contornou de rosa a praca de
alimentacao, que fica no piso de cima. Area pintada se separa por cor, sem adivinhacao:
a tentativa anterior lia tracos finos de caneta, que tem a mesma espessura das linhas
impressas do CAD e por isso nao davam para separar.

A pintura diz onde ha circulacao; ela nao diz onde uma zona acaba e comeca a outra.
Essa divisao esta em EIXOS: cada pixel verde vai para o eixo mais proximo, e o contorno
do que sobrou vira o poligono da zona. Assim o limite entre duas zonas fica no meio do
caminho entre elas, e o contorno externo continua sendo exatamente o que foi pintado.

A praca de eventos central nao foi pintada: ela e o buraco no meio do anel. O poligono
dela vem do circulo impresso na planta e esta em CIRCULO_DA_PRACA.

Uso:  python ferramentas/zonas_da_pintura.py
Saida: o literal PHP para o GerarZonas e uma imagem de conferencia no scratchpad.
"""
import sys

import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFont

PASTA = r"C:\Users\muril\AppData\Local\Temp\claude\d3c1bdfea2d8e46c\5d341726-cac6-4db7-a7dc-6858bc52f706\scratchpad"
PINTADO = r"C:\Users\muril\AppData\Local\Temp\claude\d3c1bdfea2d8e46c\5d341726-cac6-4db7-a7dc-6858bc52f706\images\4.png"
PLANTA = r"C:\Users\muril\hackarun-mapa-calor\dashboard\public\planta-piso1.jpg"

# A imagem pintada e um print do painel. Estes tres numeros vieram de alinhar as linhas
# pretas dela com as da planta (96% dos pixels de linha caem sobre linha da planta).
ESCALA, DX, DY = 0.4125, 84, -23
LARGURA, ALTURA = 2740, 1820

# Eixos aproximados de cada corredor, em coordenadas da planta. Servem so para dividir
# o verde; nenhum contorno sai daqui.
EIXOS = [
    ("Z02", "Mall da C&A", "corredor",
     [(610, 1020), (700, 930), (820, 840), (950, 770), (1080, 715), (1200, 700), (1280, 720)]),
    ("Z05", "Mall Stilo", "corredor",
     [(610, 1020), (650, 1100), (700, 1190), (760, 1290), (820, 1390)]),
    ("Z06", "Mall Oeste", "corredor",
     [(840, 1420), (950, 1420), (1060, 1405), (1160, 1395)]),
    ("Z08", "Mall Leste", "corredor",
     [(1320, 1370), (1460, 1355), (1600, 1345), (1740, 1340), (1860, 1340)]),
    ("Z16", "Mall Nordeste", "corredor",
     [(1280, 720), (1400, 790), (1520, 850), (1650, 910), (1780, 960), (1880, 1010)]),
    ("Z17", "Galeria Leste", "corredor",
     [(1950, 220), (1950, 450), (1950, 680), (1952, 900), (1958, 1120), (1960, 1300)]),
]

# A praca de eventos e o circulo impresso na planta, de 265,90 m2; o grupo pintou em volta.
CIRCULO_DA_PRACA = ("Z07", "Praça de Eventos Central", "convivencia", 1,
                    [[1139, 1204], [1207, 1147], [1293, 1137], [1360, 1185], [1384, 1267],
                     [1356, 1353], [1279, 1401], [1192, 1397], [1139, 1339]])

PRACA_DE_ALIMENTACAO = ("Z01", "Praça de Alimentação", "alimentacao", 2)

VIZINHOS = {
    "Z01": [],
    "Z02": ["Z05", "Z16"],
    "Z05": ["Z02", "Z06"],
    "Z06": ["Z05", "Z07", "Z08"],
    "Z07": ["Z06", "Z08"],
    "Z08": ["Z06", "Z07", "Z17"],
    "Z16": ["Z02", "Z17"],
    "Z17": ["Z08", "Z16"],
}


def _para_planta(mask):
    """Leva uma mascara da imagem pintada para o sistema de coordenadas da planta."""
    ys, xs = np.nonzero(mask)
    x = np.round((xs - DX) / ESCALA).astype(np.int32)
    y = np.round((ys - DY) / ESCALA).astype(np.int32)
    dentro = (x >= 0) & (x < LARGURA) & (y >= 0) & (y < ALTURA)
    tela = np.zeros((ALTURA, LARGURA), np.uint8)
    tela[y[dentro], x[dentro]] = 255
    # a reamostragem para uma imagem maior deixa buracos de 1 px
    return cv2.morphologyEx(tela, cv2.MORPH_CLOSE, np.ones((9, 9), np.uint8))


def _so_os_grandes(mask, minimo):
    quantas, rotulos, estatisticas, _ = cv2.connectedComponentsWithStats(mask, 8)
    saida = np.zeros_like(mask)
    for i in range(1, quantas):
        if estatisticas[i, cv2.CC_STAT_AREA] >= minimo:
            saida[rotulos == i] = 255
    return saida


def mascaras():
    """Devolve (praca_de_alimentacao, corredores) como mascaras do tamanho da planta."""
    imagem = np.asarray(Image.open(PINTADO).convert("RGB")).astype(np.int16)
    r, g, b = imagem[:, :, 0], imagem[:, :, 1], imagem[:, :, 2]

    verde = _para_planta((g > 110) & (g - r > 50) & (g - b > 50))
    rosa = _para_planta((r > 200) & (r - g > 40) & (b - g > 20))

    # so o maior pedaco de rosa e o contorno; o resto e cor do painel que estava embaixo
    quantas, rotulos, estatisticas, _ = cv2.connectedComponentsWithStats(rosa, 8)
    if quantas > 1:
        rosa = np.where(rotulos == 1 + np.argmax(estatisticas[1:, cv2.CC_STAT_AREA]), 255, 0)
        rosa = rosa.astype(np.uint8)

    # interior do anel rosa: inunda a partir do canto e inverte
    inundado = rosa.copy()
    cv2.floodFill(inundado, np.zeros((ALTURA + 2, LARGURA + 2), np.uint8), (0, 0), 255)
    interior = cv2.bitwise_not(inundado) | rosa

    praca = cv2.bitwise_and(verde, interior)
    corredores = cv2.subtract(verde, cv2.dilate(interior, np.ones((5, 5), np.uint8)))

    # a praca de eventos tem poligono proprio: tira do verde para as zonas nao se sobreporem
    cv2.fillPoly(corredores, [np.array(CIRCULO_DA_PRACA[4], np.int32)], 0)

    return _so_os_grandes(praca, 5000), _so_os_grandes(corredores, 5000)


def _distancia_ao_eixo(eixo):
    """Distancia de cada pixel da planta ate a poligonal do eixo."""
    traco = np.zeros((ALTURA, LARGURA), np.uint8)
    cv2.polylines(traco, [np.array(eixo, np.int32)], False, 255, 1)
    return cv2.distanceTransform(cv2.bitwise_not(traco), cv2.DIST_L2, 5)


def _contorno(mask, tolerancia=8):
    contornos, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    maior = max(contornos, key=cv2.contourArea)
    aproximado = cv2.approxPolyDP(maior, tolerancia, True)
    return [[int(p[0][0]), int(p[0][1])] for p in aproximado]


def zonas():
    praca, corredores = mascaras()
    distancias = [_distancia_ao_eixo(eixo) for _, _, _, eixo in EIXOS]
    mais_perto = np.argmin(np.stack(distancias), axis=0)

    saida = []
    for i, (zid, nome, tipo, _) in enumerate(EIXOS):
        meu = np.where((mais_perto == i) & (corredores > 0), 255, 0).astype(np.uint8)
        meu = _so_os_grandes(cv2.morphologyEx(meu, cv2.MORPH_OPEN, np.ones((7, 7), np.uint8)), 3000)
        saida.append((zid, nome, tipo, 1, _contorno(meu)))

    zid, nome, tipo, piso = PRACA_DE_ALIMENTACAO
    saida.append((zid, nome, tipo, piso, _contorno(praca, 14)))
    saida.append(CIRCULO_DA_PRACA)
    saida.sort(key=lambda z: z[0])
    return saida


def conferir(lista):
    fundo = np.asarray(Image.open(PLANTA).convert("RGB")).astype(np.float32)
    fundo = (fundo * 0.45 + 255 * 0.55).astype(np.uint8)
    cores = [(0, 140, 70), (210, 90, 0), (0, 90, 200), (180, 0, 140),
             (0, 150, 150), (150, 120, 0), (120, 0, 200), (200, 40, 40)]

    camada = fundo.copy()
    for i, (zid, nome, tipo, piso, pontos) in enumerate(lista):
        cv2.fillPoly(camada, [np.array(pontos, np.int32)], cores[i % len(cores)])
    fundo = cv2.addWeighted(camada, 0.5, fundo, 0.5, 0)
    for i, (zid, nome, tipo, piso, pontos) in enumerate(lista):
        cv2.polylines(fundo, [np.array(pontos, np.int32)], True, cores[i % len(cores)], 4)

    imagem = Image.fromarray(fundo)
    desenho = ImageDraw.Draw(imagem)
    try:
        fonte = ImageFont.truetype("arialbd.ttf", 34)
    except OSError:
        fonte = ImageFont.load_default()
    for zid, nome, tipo, piso, pontos in lista:
        cx = sum(p[0] for p in pontos) / len(pontos)
        cy = sum(p[1] for p in pontos) / len(pontos)
        desenho.text((cx - 20, cy - 16), zid, fill=(0, 0, 0), font=fonte,
                     stroke_width=4, stroke_fill=(255, 255, 255))
    imagem.resize((LARGURA // 2, ALTURA // 2), Image.LANCZOS).save(rf"{PASTA}\conferencia-zonas.png")


def main():
    lista = zonas()
    conferir(lista)

    saida = sys.stdout
    print("// gerado por ferramentas/zonas_da_pintura.py", file=saida)
    for zid, nome, tipo, piso, pontos in lista:
        vizinhos = ", ".join(f"'{v}'" for v in VIZINHOS.get(zid, []))
        corpo = ", ".join(f"[{x}, {y}]" for x, y in pontos)
        print(f"['{zid}', '{nome}', '{tipo}', {piso}, [{vizinhos}],", file=saida)
        print(f"    [{corpo}]],", file=saida)
        print(f"   // {len(pontos)} pontos", file=saida)


if __name__ == "__main__":
    main()
