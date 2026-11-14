"""Converte as zonas lidas sobre a imagem marcada para o sistema da planta do projeto,
desenha o resultado por cima da marcacao e imprime o literal PHP para o GerarZonas.

Leitura: coordenadas da imagem marcada (1152x648).
Saida:  coordenadas do recorte da planta usado no projeto (origem 880,440 da piso1.jpg).
"""
from PIL import Image, ImageDraw, ImageFont

PASTA = r"C:\Users\muril\AppData\Local\Temp\claude\d3c1bdfea2d8e46c\5d341726-cac6-4db7-a7dc-6858bc52f706\scratchpad"
MARCADO = r"C:\Users\muril\AppData\Local\Temp\claude\d3c1bdfea2d8e46c\5d341726-cac6-4db7-a7dc-6858bc52f706\images\3.png"

ESCALA, DX, DY = 0.208, 72, 14
ORIGEM = (880, 440)
PIXELS_POR_METRO = 11.29

# ---------------------------------------------------------------- zonas lidas na marcacao
# Corredor: eixo (a linha que voce desenhou) e largura em metros.
# Area: poligono fechado, para pracas e para a praca de alimentacao do piso 2.
CORREDORES = [
    ("Z02", "Mall da C&A", "corredor", 9,
     [(352, 300), (378, 304), (402, 302), (426, 298), (450, 291), (470, 280), (488, 268),
      (512, 261), (540, 258), (558, 261)]),
    ("Z03", "Mall Noroeste", "corredor", 8,
     [(352, 300), (340, 310), (326, 322), (311, 333), (298, 344)]),
    ("Z04", "Mall da Renner", "corredor", 8,
     [(298, 344), (330, 341), (360, 342), (386, 350)]),
    ("Z05", "Mall Stilo", "corredor", 8,
     [(386, 352), (400, 352), (410, 361), (418, 376), (425, 392), (428, 401)]),
    ("Z06", "Mall Oeste", "corredor", 9,
     [(428, 401), (446, 401), (464, 399), (480, 392), (492, 383)]),
    ("Z08", "Mall Leste", "corredor", 9,
     [(540, 372), (560, 378), (582, 384), (604, 391), (626, 397), (644, 401)]),
    ("Z09", "Mall Sul", "corredor", 9,
     [(428, 406), (442, 416), (462, 421), (492, 424), (522, 429), (556, 438)]),
    ("Z10", "Mall do Cinemark", "corredor", 10,
     [(558, 266), (558, 300), (558, 340), (558, 382), (558, 420), (558, 440)]),
    ("Z11", "Galeria do STZ", "corredor", 9,
     [(562, 262), (600, 262), (650, 262), (706, 262), (758, 262)]),
    ("Z12", "Galeria do Big", "corredor", 9,
     [(657, 126), (657, 152), (657, 178), (657, 200)]),
    ("Z13", "Galeria da Pernambucanas", "corredor", 9,
     [(660, 200), (692, 200), (724, 200), (758, 200)]),
]

AREAS = [
    ("Z01", "Praça de Alimentação", "alimentacao", 2,
     [(272, 358), (300, 345), (345, 341), (378, 345), (388, 362), (386, 392), (375, 420),
      (352, 438), (320, 442), (292, 434), (272, 414), (264, 386)]),
    ("Z07", "Praça de Eventos Central", "convivencia", 1,
     [(492, 356), (506, 344), (524, 342), (538, 352), (543, 369), (537, 387), (521, 397),
      (503, 396), (492, 384)]),
]

VIZINHOS = {
    "Z01": ["Z04"],
    "Z02": ["Z03", "Z11"],
    "Z03": ["Z02", "Z04"],
    "Z04": ["Z01", "Z03", "Z05"],
    "Z05": ["Z04", "Z06", "Z09"],
    "Z06": ["Z05", "Z07"],
    "Z07": ["Z06", "Z08", "Z09"],
    "Z08": ["Z07", "Z10"],
    "Z09": ["Z05", "Z07", "Z10"],
    "Z10": ["Z08", "Z09", "Z11"],
    "Z11": ["Z02", "Z10"],
    "Z12": ["Z13"],
    "Z13": ["Z12"],
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
