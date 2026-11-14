"""Converte as zonas lidas sobre a imagem marcada para o sistema da planta do projeto,
desenha o resultado por cima da marcacao e imprime o literal PHP para o GerarZonas.

Leitura: coordenadas da imagem marcada (1152x648).
Saida:  coordenadas do recorte da planta usado no projeto (origem 880,440 da piso1.jpg).

As zonas aqui sao as sete que existem de fato na marcacao. As seis que foram apagadas
(Noroeste, Renner, Sul, Cinemark, STZ, Big, Pernambucanas) nao voltam: os numeros pulam de
proposito, para um id nunca mudar de significado.
"""
from PIL import Image, ImageDraw, ImageFont

PASTA = r"C:\Users\muril\AppData\Local\Temp\claude\d3c1bdfea2d8e46c\5d341726-cac6-4db7-a7dc-6858bc52f706\scratchpad"
MARCADO = r"C:\Users\muril\Downloads\MAPA COM MARCACAO.png"

ESCALA, DX, DY = 0.208, 72, 14
ORIGEM = (880, 440)
PIXELS_POR_METRO = 11.29

# ---------------------------------------------------------------- zonas lidas na marcacao
# Corredor: eixo (a linha desenhada a mao) e largura em metros.
# Area: poligono fechado, para pracas e para a praca de alimentacao do piso 2.
CORREDORES = [
    # O traco do Mall da C&A nao para na curva: ele desce para sudeste depois da C&A e
    # so termina na esquina leste, onde a marcacao vira para o norte.
    ("Z02", "Mall da C&A", "corredor", 9,
     [(352, 302), (378, 308), (402, 309), (426, 310), (438, 306), (450, 300), (462, 291),
      (474, 282), (486, 273), (498, 264), (510, 258), (522, 260), (534, 262), (545, 268),
      (557, 279), (569, 288), (581, 298), (592, 307), (604, 311), (618, 312), (632, 312),
      (645, 311), (652, 307)]),
    ("Z05", "Mall Stilo", "corredor", 8,
     [(386, 352), (400, 352), (410, 361), (418, 376), (425, 392), (428, 401)]),
    ("Z06", "Mall Oeste", "corredor", 9,
     [(428, 401), (446, 401), (464, 399), (480, 392), (492, 383)]),
    # O Mall Leste comeca na borda sudeste da praca central e segue ate a esquina leste.
    ("Z08", "Mall Leste", "corredor", 9,
     [(535, 384), (547, 383), (559, 383), (571, 386), (583, 391), (595, 396), (607, 400),
      (620, 401), (633, 401), (646, 401), (655, 397)]),
]

AREAS = [
    ("Z01", "Pra\u00e7a de Alimenta\u00e7\u00e3o", "alimentacao", 2,
     [(272, 358), (300, 345), (345, 341), (378, 345), (388, 362), (386, 392), (375, 420),
      (352, 438), (320, 442), (292, 434), (272, 414), (264, 386)]),
    ("Z07", "Pra\u00e7a de Eventos Central", "convivencia", 1,
     [(492, 356), (506, 344), (524, 342), (538, 352), (543, 369), (537, 387), (521, 397),
      (503, 396), (492, 384)]),
]

VIZINHOS = {
    "Z01": [],
    "Z02": [],
    "Z05": ["Z06"],
    "Z06": ["Z05", "Z07"],
    "Z07": ["Z06", "Z08"],
    "Z08": ["Z07"],
}


def para_planta(p):
    x, y = p
    return (round((x - DX) / ESCALA - ORIGEM[0]), round((y - DY) / ESCALA - ORIGEM[1]))


def faixa(eixo, largura_m):
    """Transforma o eixo em poligono, afastando metade da largura para cada lado."""
    meia = largura_m * PIXELS_POR_METRO / 2
    esquerda, direita = [], []

    for i, (x, y) in enumerate(eixo):
        ax, ay = eixo[max(0, i - 1)]
        bx, by = eixo[min(len(eixo) - 1, i + 1)]
        dx, dy = bx - ax, by - ay
        comp = (dx * dx + dy * dy) ** 0.5 or 1
        nx, ny = -dy / comp * meia, dx / comp * meia
        esquerda.append((round(x + nx), round(y + ny)))
        direita.append((round(x - nx), round(y - ny)))

    return esquerda + direita[::-1]


def main():
    zonas = []

    for zid, nome, tipo, largura, eixo in CORREDORES:
        zonas.append((zid, nome, tipo, 1, faixa([para_planta(p) for p in eixo], largura)))

    for zid, nome, tipo, piso, poligono in AREAS:
        zonas.append((zid, nome, tipo, piso, [para_planta(p) for p in poligono]))

    zonas.sort(key=lambda z: z[0])

    # desenho sobre a marcacao, para conferir
    fundo = Image.open(MARCADO).convert("RGB")
    camada = Image.new("RGBA", fundo.size, (0, 0, 0, 0))
    desenho = ImageDraw.Draw(camada)
    try:
        fonte = ImageFont.truetype("arialbd.ttf", 11)
    except OSError:
        fonte = ImageFont.load_default()

    def para_marcado(p):
        x, y = p
        return ((x + ORIGEM[0]) * ESCALA + DX, (y + ORIGEM[1]) * ESCALA + DY)

    for zid, nome, tipo, piso, poligono in zonas:
        pontos = [para_marcado(p) for p in poligono]
        cor = (220, 0, 0, 90) if piso == 1 else (0, 120, 220, 110)
        desenho.polygon(pontos, fill=cor, outline=cor[:3] + (255,), width=2)
        cx = sum(p[0] for p in pontos) / len(pontos)
        cy = sum(p[1] for p in pontos) / len(pontos)
        desenho.text((cx - 8, cy - 5), zid, fill=(0, 0, 0), font=fonte,
                     stroke_width=2, stroke_fill=(255, 255, 255))

    Image.alpha_composite(fundo.convert("RGBA"), camada).convert("RGB").save(
        rf"{PASTA}\conferencia-zonas.png")

    print("// gerado por ferramentas/converter_zonas.py")
    for zid, nome, tipo, piso, poligono in zonas:
        pontos = ", ".join(f"[{x}, {y}]" for x, y in poligono)
        vizinhos = ", ".join(f"'{v}'" for v in VIZINHOS.get(zid, []))
        print(f"['{zid}', '{nome}', '{tipo}', {piso}, [{vizinhos}],")
        print(f"    [{pontos}]],")


if __name__ == "__main__":
    main()
