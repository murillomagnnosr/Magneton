# -*- coding: utf-8 -*-
"""Gera os wireframes das quatro telas (SVG 1280x800) e renderiza PNG com o Chrome headless.
Todos os números são ilustrativos."""
import math, os, subprocess, sys
from html import escape

HERE = os.path.dirname(os.path.abspath(__file__))
W, H = 1280, 800
FONT = "system-ui, 'Segoe UI', Arial, Helvetica, sans-serif"
INK, MUTED, LINE, BG, CARD = "#1F2933", "#5B6770", "#C9D1D9", "#F4F6F8", "#FFFFFF"
ACCENT = "#1F4E79"
SEQ = ["#FFFFB2", "#FECC5C", "#FD8D3C", "#F03B20", "#BD0026"]
DIV = {"qf": "#2166AC", "q": "#92C5DE", "e": "#EEF0F2", "a": "#F4A582", "af": "#B2182B"}


def T(x, y, s, size=12, weight=400, fill=INK, anchor="start", extra=""):
    return (f'<text x="{x}" y="{y}" font-size="{size}" font-weight="{weight}" fill="{fill}" '
            f'text-anchor="{anchor}" {extra}>{escape(s)}</text>')


def R(x, y, w, h, fill=CARD, stroke=LINE, rx=6, sw=1, extra=""):
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{fill}" '
            f'stroke="{stroke}" stroke-width="{sw}" {extra}/>')


def L(x1, y1, x2, y2, stroke=LINE, sw=1, extra=""):
    return f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{stroke}" stroke-width="{sw}" {extra}/>'


def num(v):
    return f"{v:.1f}".replace(".", ",")


DEFS = f'''<defs>
<pattern id="hach" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
<rect width="8" height="8" fill="#FFFFFF"/><line x1="0" y1="0" x2="0" y2="8" stroke="#7B8794" stroke-width="2.4"/></pattern>
<marker id="seta" markerUnits="userSpaceOnUse" markerWidth="12" markerHeight="12" refX="9" refY="5" orient="auto">
<path d="M0,0 L10,5 L0,10 z" fill="{MUTED}"/></marker>
</defs>'''

NAV = [("Mapa de calor", 122), ("Comparação de períodos", 186), ("Detalhe da zona", 134), ("Câmeras e privacidade", 178)]


def frame(active, body):
    o = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" font-family="{FONT}">',
         DEFS, f'<rect width="{W}" height="{H}" fill="{BG}"/>',
         f'<rect width="{W}" height="56" fill="{CARD}"/>', L(0, 56, W, 56)]
    # logo + nome
    o.append(f'<rect x="24" y="16" width="24" height="24" rx="5" fill="{SEQ[4]}"/>')
    o.append(f'<rect x="29" y="21" width="8" height="8" fill="{SEQ[1]}"/><rect x="37" y="29" width="6" height="6" fill="{SEQ[0]}"/>')
    o.append(T(58, 34, "Mapa de Calor do Shopping", 16, 700))
    x = 286
    for i, (name, w) in enumerate(NAV):
        if i == active:
            o.append(R(x, 12, w, 32, fill="#E4EDF6", stroke=ACCENT, rx=16))
            o.append(T(x + w / 2, 33, name, 13, 700, ACCENT, "middle"))
        else:
            o.append(T(x + w / 2, 33, name, 13, 400, MUTED, "middle"))
        x += w + 6
    # selo da fonte
    o.append(R(968, 14, 288, 28, fill="#F0F4F8", stroke=LINE, rx=14))
    o.append(f'<circle cx="984" cy="28" r="5" fill="{INK}"/>')
    o.append(T(996, 32.5, "Fonte: 5 zonas por câmera · 11 simuladas", 12, 600))
    o.append(body)
    o.append(T(W - 24, 790, "Wireframe — dados ilustrativos", 11, 400, "#7B8794", "end"))
    o.append("</svg>")
    return "\n".join(o)


# ---------------------------------------------------------------- planta
# id: (nome, x, y, w, h, vertical)
ZONAS = {
    "Z01": ("Âncora", 0, 0, 260, 100, False),
    "Z02": ("Lojas Norte", 260, 0, 270, 100, False),
    "Z03": ("Lojas Ala Norte", 530, 0, 270, 100, False),
    "Z04": ("Entrada Oeste", 0, 100, 110, 50, False),
    "Z05": ("Corredor Norte", 110, 100, 580, 50, False),
    "Z06": ("Entrada Leste", 690, 100, 110, 50, False),
    "Z08": ("Lojas Oeste", 0, 150, 180, 180, False),
    "Z09": ("Corredor Oeste", 180, 150, 60, 180, True),
    "Z07": ("Praça de alimentação", 240, 150, 200, 180, False),
    "Z10": ("Convivência", 440, 150, 120, 180, False),
    "Z11": ("Corredor Leste", 560, 150, 60, 180, True),
    "Z12": ("Lojas Leste", 620, 150, 180, 180, False),
    "Z13": ("Corredor Principal", 0, 330, 800, 50, False),
    "Z14": ("Lojas Sul A", 0, 380, 300, 100, False),
    "Z15": ("Entrada Principal", 300, 380, 200, 100, False),
    "Z16": ("Lojas Sul B", 500, 380, 300, 100, False),
}
CAMERA = {"Z15", "Z13", "Z07", "Z10", "Z05"}


def planta(ox, oy, fills, sub, dark=(), icones=True, s=1.0, rotulos=True, destaque=None, borda_esp=()):
    """fills: id -> cor/url; sub: id -> segunda linha; dark: ids com texto branco."""
    o = [f'<g transform="translate({ox},{oy}) scale({s})">']
    for zid, (nome, x, y, w, h, vert) in ZONAS.items():
        o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fills[zid]}" stroke="#52606D" stroke-width="1.2"/>')
    for zid in borda_esp:
        nome, x, y, w, h, vert = ZONAS[zid]
        o.append(f'<rect x="{x+3}" y="{y+3}" width="{w-6}" height="{h-6}" fill="none" stroke="{INK}" stroke-width="2.5" stroke-dasharray="7 4"/>')
    if destaque:
        nome, x, y, w, h, vert = ZONAS[destaque]
        o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="none" stroke="{INK}" stroke-width="5"/>')
    o.append(f'<rect x="0" y="0" width="800" height="480" fill="none" stroke="{INK}" stroke-width="2.5"/>')
    if rotulos:
        for zid, (nome, x, y, w, h, vert) in ZONAS.items():
            cx, cy = x + w / 2, y + h / 2
            col = "#FFFFFF" if zid in dark else INK
            hach = str(fills[zid]).startswith("url")
            small = h <= 50 and w <= 120
            fs = 11 if small else 12.5
            l2 = sub.get(zid, "")
            if vert:
                g = f'<g transform="rotate(-90 {cx} {cy})">'
                if hach:
                    g += f'<rect x="{cx-62}" y="{cy-19}" width="124" height="38" rx="3" fill="#FFFFFF" opacity="0.92"/>'
                g += T(cx, cy - 3, nome, 12, 700, col, "middle") + T(cx, cy + 13, l2, 11.5, 400, col, "middle") + "</g>"
                o.append(g)
            else:
                if hach:
                    bw = max(len(nome), len(l2)) * 7.2 + 16
                    o.append(f'<rect x="{cx-bw/2}" y="{cy-20}" width="{bw}" height="40" rx="3" fill="#FFFFFF" opacity="0.92"/>')
                tx = cx - 9 if (small and icones) else cx
                o.append(T(tx, cy - 3, nome, fs, 700, col, "middle"))
                o.append(T(tx, cy + 13, l2, fs - 0.5, 400, col, "middle"))
            if icones and not vert:
                ix, iy = x + w - 13, y + 13
                if zid in CAMERA:
                    o.append(f'<circle cx="{ix}" cy="{iy}" r="5.5" fill="{INK}" stroke="#FFFFFF" stroke-width="1.5"/>')
                else:
                    o.append(f'<circle cx="{ix}" cy="{iy}" r="5" fill="#FFFFFF" stroke="{INK}" stroke-width="1.5"/>')
            elif icones:
                ix, iy = x + w / 2, y + 13
                o.append(f'<circle cx="{ix}" cy="{iy}" r="5" fill="#FFFFFF" stroke="{INK}" stroke-width="1.5"/>')
        # setas das entradas
        for (px, py, rot) in [(-16, 125, 0), (816, 125, 180), (400, 496, -90)]:
            o.append(f'<path d="M-7,-6 L5,0 L-7,6 z" fill="{INK}" transform="translate({px},{py}) rotate({rot})"/>')
    o.append("</g>")
    return "\n".join(o)


def select(x, y, w, text, h=30):
    return (R(x, y, w, h, rx=5, stroke="#9AA5B1") + T(x + 10, y + h / 2 + 4.5, text, 12.5)
            + f'<path d="M{x+w-20},{y+h/2-3} l5,6 l5,-6" fill="none" stroke="{MUTED}" stroke-width="1.6"/>')


def wrap(s, n):
    out, cur = [], ""
    for wd in s.split():
        if len(cur) + len(wd) + 1 > n and cur:
            out.append(cur); cur = wd
        else:
            cur = (cur + " " + wd).strip()
    if cur:
        out.append(cur)
    return out


# ================================================================ TELA 1
DENS = {"Z01": 3.8, "Z02": 2.2, "Z04": 2.6, "Z06": 1.2, "Z08": 2.4, "Z09": 3.4, "Z07": 9.6, "Z10": 6.1,
        "Z11": 1.1, "Z12": 0.9, "Z13": 6.8, "Z14": 3.2, "Z15": 5.4, "Z16": 2.0}
LIM = [1.5, 3.0, 5.0, 8.0]


def classe(d):
    return sum(d >= t for t in LIM)


def tela1():
    o = []
    # barra de controles
    o.append(R(24, 66, 1232, 62))
    o.append(T(40, 84, "Dia", 11, 600, MUTED))
    o.append(select(40, 90, 170, "Sáb, 07/11/2026"))
    o.append(T(230, 84, "Período", 11, 600, MUTED))
    x = 230
    for i, p in enumerate(["Manhã", "Almoço", "Tarde", "Noite", "Dia inteiro"]):
        w = 92 if p == "Dia inteiro" else 76
        if i == 1:
            o.append(R(x, 90, w, 30, fill=ACCENT, stroke=ACCENT, rx=5))
            o.append(T(x + w / 2, 109.5, p, 12.5, 700, "#FFFFFF", "middle"))
        else:
            o.append(R(x, 90, w, 30, rx=5, stroke="#9AA5B1"))
            o.append(T(x + w / 2, 109.5, p, 12.5, 400, INK, "middle"))
        x += w + 4
    # slider
    sx0, sx1 = 668, 1100
    o.append(T(sx0 - 12, 84, "Horário (janelas de 15 min)", 11, 600, MUTED))
    fx = lambda h: sx0 + (h - 10) / 12 * (sx1 - sx0)
    o.append(L(sx0, 100, sx1, 100, "#9AA5B1", 4, 'stroke-linecap="round"'))
    o.append(L(fx(11.5), 100, fx(14), 100, "#8FB3D9", 4))
    for h in range(10, 23, 2):
        o.append(L(fx(h), 106, fx(h), 111, MUTED))
        o.append(T(fx(h), 123, f"{h}h", 10.5, 400, MUTED, "middle"))
    o.append(f'<circle cx="{fx(12.75)}" cy="100" r="8" fill="{ACCENT}" stroke="#FFFFFF" stroke-width="2"/>')
    o.append(R(1124, 90, 116, 30, fill="#E4EDF6", stroke=ACCENT, rx=5))
    o.append(T(1182, 109.5, "12h45 – 13h00", 12.5, 700, ACCENT, "middle"))

    # cartão da planta
    o.append(R(24, 138, 848, 636))
    o.append(T(40, 162, "Piso L1 · Sáb, 07/11/2026 · janela 12h45 – 13h00", 14, 700))
    o.append(T(856, 162, "Cursor: nome, tipo e área · Clique: detalhe da zona", 11.5, 400, MUTED, "end"))
    fills, sub, dark = {}, {}, set()
    for z in ZONAS:
        if z == "Z03":
            fills[z] = "url(#hach)"; sub[z] = "abaixo do limiar"
        elif z == "Z05":
            fills[z] = "#D5DAE0"; sub[z] = "sem dado (câmera sem sinal)"
        else:
            c = classe(DENS[z]); fills[z] = SEQ[c]; sub[z] = f"{z} · {num(DENS[z])}"
            if c >= 3:
                dark.add(z)
    o.append(planta(48, 178, fills, sub, dark))
    # legenda
    ly = 692
    o.append(T(40, ly, "Densidade (pessoas por 100 m²)", 12, 700))
    labs = ["até 1,5", "1,5 a 3,0", "3,0 a 5,0", "5,0 a 8,0", "acima de 8,0"]
    for i, lab in enumerate(labs):
        x = 40 + i * 96
        o.append(R(x, ly + 10, 96, 16, fill=SEQ[i], stroke="#52606D", rx=0))
        o.append(T(x + 48, ly + 41, lab, 11.5, 400, INK, "middle"))
    o.append(R(548, ly + 10, 26, 16, fill="url(#hach)", stroke="#52606D", rx=0))
    o.append(T(582, ly + 23, "abaixo do limiar (menos de 5 pessoas)", 11.5))
    o.append(R(548, ly + 34, 26, 16, fill="#D5DAE0", stroke="#52606D", rx=0))
    o.append(T(582, ly + 47, "sem dado", 11.5))
    o.append(f'<circle cx="48" cy="{ly+64}" r="5.5" fill="{INK}"/>')
    o.append(T(60, ly + 68, "zona medida por câmera", 11.5))
    o.append(f'<circle cx="222" cy="{ly+64}" r="5" fill="#FFFFFF" stroke="{INK}" stroke-width="1.5"/>')
    o.append(T(234, ly + 68, "zona simulada", 11.5))
    o.append(f'<path d="M-7,-6 L5,0 L-7,6 z" fill="{INK}" transform="translate(352,{ly+64})"/>')
    o.append(T(364, ly + 68, "entrada", 11.5))

    # painel lateral: ranking
    px, pw = 888, 368
    o.append(R(px, 138, pw, 318))
    o.append(T(px + 16, 162, "Ranking do período · Almoço", 14, 700))
    o.append(T(px + 16, 180, "Média do período (12h às 14h). Piso: 2,9 pessoas/100 m²", 11.5, 400, MUTED))

    def linha(y, pos, nome, val, cor, frac):
        s = [T(px + 16, y, f"{pos}.", 12.5, 700), T(px + 34, y, nome, 12.5)]
        if val is None:
            s.append(R(px + 218, y - 12, 134, 16, fill="url(#hach)", stroke="#52606D", rx=0))
            s.append(R(px + 226, y - 11, 118, 14, fill="#FFFFFF", stroke="none", rx=2))
            s.append(T(px + 285, y, "abaixo do limiar", 11, 400, INK, "middle"))
        else:
            s.append(R(px + 218, y - 11, 96 * frac, 13, fill=cor, stroke="#52606D", rx=0))
            s.append(T(px + pw - 16, y, val, 12.5, 700, INK, "end"))
        return "".join(s)

    o.append(T(px + 16, 206, "MAIS QUENTES", 11, 700, SEQ[4]))
    for i, (n, v) in enumerate([("Praça de alimentação", 8.9), ("Corredor Principal", 6.5), ("Convivência", 5.8)]):
        o.append(linha(228 + i * 26, i + 1, n, num(v), SEQ[classe(v)], v / 8.9))
    o.append(L(px + 16, 300, px + pw - 16, 300))
    o.append(T(px + 16, 322, "MAIS FRIAS", 11, 700, ACCENT))
    for i, (n, v) in enumerate([("Lojas Leste", 1.0), ("Corredor Leste", 1.2), ("Lojas Ala Norte", None)]):
        o.append(linha(344 + i * 26, i + 1, n, None if v is None else num(v), SEQ[0], (v or 0) / 8.9))
    o.append(T(px + 16, 438, "Sem número: zona com menos de 5 pessoas na janela.", 11, 400, MUTED))

    # painel lateral: frases
    o.append(R(px, 468, pw, 306))
    o.append(T(px + 16, 492, "Leitura automática", 14, 700))
    frases = [
        ("Câmera", "No almoço, a Praça de alimentação é a zona mais cheia, com densidade 3,1 vezes a média do piso."),
        ("Simulação", "No almoço, a Ala Norte é a zona de menor circulação (abaixo do limiar)."),
        ("Câmera", "O Corredor Principal ficou entre as zonas mais movimentadas em 6 dos 7 dias."),
    ]
    y = 508
    for tag, f in frases:
        ls = wrap(f, 46)
        hh = 30 + len(ls) * 17
        o.append(R(px + 16, y, pw - 32, hh, fill="#F7F9FB", rx=5))
        tw = 58 if tag == "Câmera" else 74
        o.append(R(px + 26, y + 8, tw, 17, fill=INK if tag == "Câmera" else "#FFFFFF", stroke=INK, rx=8))
        o.append(T(px + 26 + tw / 2, y + 20.5, tag, 10.5, 700, "#FFFFFF" if tag == "Câmera" else INK, "middle"))
        for j, l in enumerate(ls):
            o.append(T(px + 26, y + 43 + j * 17, l, 12.5))
        y += hh + 8
    return frame(0, "\n".join(o))


# ================================================================ TELA 2
AB = {"Z01": (3.6, 3.8), "Z02": (2.3, 2.2), "Z04": (2.4, 2.6), "Z06": (1.3, 1.2), "Z08": (3.2, 2.4),
      "Z09": (3.0, 3.4), "Z07": (5.5, 8.9), "Z10": (4.3, 5.8), "Z11": (2.5, 1.2), "Z12": (1.1, 1.0),
      "Z13": (5.3, 6.5), "Z14": (3.1, 3.2), "Z15": (4.2, 5.4), "Z16": (2.2, 2.0)}


def pct(z):
    a, b = AB[z]
    return (b - a) / a * 100


def cls_div(p):
    if p <= -50: return "qf"
    if p <= -20: return "q"
    if p < 20: return "e"
    if p < 50: return "a"
    return "af"


def sinal(p):
    return ("+" if p >= 0 else "−") + f"{abs(p):.0f}%"


def tela2():
    o = []
    o.append(R(24, 66, 1232, 62))
    o.append(R(40, 78, 26, 26, fill=INK, stroke=INK, rx=13) + T(53, 96, "A", 13, 700, "#FFFFFF", "middle"))
    o.append(T(76, 84, "Período A (base)", 11, 600, MUTED))
    o.append(select(76, 90, 160, "Qui, 05/11/2026") + select(244, 90, 120, "Almoço"))
    o.append(T(404, 110, "comparado com", 12, 400, MUTED))
    o.append(R(512, 78, 26, 26, fill=INK, stroke=INK, rx=13) + T(525, 96, "B", 13, 700, "#FFFFFF", "middle"))
    o.append(T(548, 84, "Período B", 11, 600, MUTED))
    o.append(select(548, 90, 160, "Sáb, 07/11/2026") + select(716, 90, 120, "Almoço"))
    o.append(T(1240, 100, "Variação = (B − A) ÷ A, sobre a densidade média", 12, 400, MUTED, "end"))
    o.append(T(1240, 117, "Só destaca 20% ou mais com diferença absoluta relevante", 12, 400, MUTED, "end"))

    o.append(R(24, 138, 848, 636))
    o.append(T(40, 162, "Piso L1 · variação de A para B", 14, 700))
    o.append(T(856, 162, "Qui 05/11, almoço  →  Sáb 07/11, almoço", 12, 400, MUTED, "end"))
    fills, sub, dark = {}, {}, set()
    for z in ZONAS:
        if z == "Z03":
            fills[z] = "#FFFFFF"; sub[z] = "saiu do limiar em B"
        elif z == "Z05":
            fills[z] = "url(#hach)"; sub[z] = "sem comparação"
        else:
            p = pct(z); c = cls_div(p); fills[z] = DIV[c]
            sub[z] = "estável" if c == "e" else sinal(p)
            if c in ("qf", "af"):
                dark.add(z)
    o.append(planta(48, 178, fills, sub, dark, icones=False, borda_esp=("Z03",)))
    ly = 692
    o.append(T(40, ly, "Variação da densidade", 12, 700))
    labs = [("qf", "queda forte", "−50% ou menos"), ("q", "queda", "−50% a −20%"), ("e", "estável", "−20% a +20%"),
            ("a", "aumento", "+20% a +50%"), ("af", "aumento forte", "+50% ou mais")]
    for i, (k, a, b) in enumerate(labs):
        x = 40 + i * 104
        o.append(R(x, ly + 10, 104, 16, fill=DIV[k], stroke="#52606D", rx=0))
        o.append(T(x + 52, ly + 41, a, 11.5, 700, INK, "middle"))
        o.append(T(x + 52, ly + 56, b, 11, 400, MUTED, "middle"))
    o.append(R(596, ly + 10, 26, 16, fill="#FFFFFF", stroke=INK, rx=0, sw=2, extra='stroke-dasharray="5 3"'))
    o.append(T(630, ly + 23, "saiu ou caiu abaixo do limiar", 11.5))
    o.append(R(596, ly + 36, 26, 16, fill="url(#hach)", stroke="#52606D", rx=0))
    o.append(T(630, ly + 49, "sem comparação", 11.5))

    px, pw = 888, 368
    o.append(R(px, 138, pw, 636))
    o.append(T(px + 16, 162, "Zonas com variação relevante", 14, 700))
    o.append(T(px + 16, 180, "Densidade média em pessoas por 100 m²", 11.5, 400, MUTED))
    o.append(T(px + 16, 208, "ZONA", 10.5, 700, MUTED) + T(px + 232, 208, "A  →  B", 10.5, 700, MUTED, "middle")
             + T(px + pw - 16, 208, "VARIAÇÃO", 10.5, 700, MUTED, "end"))
    o.append(L(px + 16, 216, px + pw - 16, 216))
    rel = sorted([z for z in AB if cls_div(pct(z)) != "e"], key=lambda z: -pct(z))
    y = 242
    for z in rel:
        p = pct(z); c = cls_div(p); a, b = AB[z]
        o.append(T(px + 16, y, ZONAS[z][0], 12.5))
        o.append(T(px + 232, y, f"{num(a)}  →  {num(b)}", 12.5, 400, MUTED, "middle"))
        o.append(R(px + pw - 76, y - 15, 60, 21, fill=DIV[c], stroke="#52606D", rx=10))
        o.append(T(px + pw - 46, y, sinal(p), 12, 700, "#FFFFFF" if c in ("qf", "af") else INK, "middle"))
        o.append(L(px + 16, y + 12, px + pw - 16, y + 12, "#E4E7EB"))
        y += 34
    y += 12
    o.append(T(px + 16, y, "MUDANÇA DE LIMIAR", 11, 700, MUTED)); y += 24
    o.append(T(px + 16, y, "Lojas Ala Norte", 12.5))
    o.append(R(px + 198, y - 15, 154, 21, fill="#FFFFFF", stroke=INK, rx=10, sw=1.5, extra='stroke-dasharray="5 3"'))
    o.append(T(px + 275, y, "saiu do limiar em B", 11.5, 700, INK, "middle")); y += 34
    o.append(T(px + 16, y, "SEM COMPARAÇÃO", 11, 700, MUTED)); y += 24
    o.append(T(px + 16, y, "Corredor Norte", 12.5))
    o.append(T(px + pw - 16, y, "câmera sem sinal em B", 11.5, 400, MUTED, "end")); y += 30
    o.append(R(px + 16, y, pw - 32, 104, fill="#F7F9FB", rx=5))
    o.append(T(px + 26, y + 22, "Leitura automática", 12, 700))
    for j, l in enumerate(wrap("No almoço de sábado, a Praça de alimentação teve densidade "
                               f"{abs(pct('Z07')):.0f}% maior que no almoço de quinta. "
                               f"Outras {sum(cls_div(pct(z)) == 'e' for z in AB)} zonas ficaram estáveis.", 46)):
        o.append(T(px + 26, y + 42 + j * 17, l, 12.5))
    return frame(1, "\n".join(o))


# ================================================================ TELA 3
def serie():
    xs = [10 + i * 0.25 for i in range(48)]
    ys = [1.0 + 8.6 * math.exp(-((x - 12.75) ** 2) / (2 * 0.9 ** 2)) + 5.0 * math.exp(-((x - 19.75) ** 2) / (2 * 1.1 ** 2))
          for x in xs]
    return xs, ys


def tela3():
    o = []
    o.append(R(24, 66, 1232, 62))
    o.append(T(40, 102, "←  Voltar ao mapa", 13, 600, ACCENT))
    o.append(T(196, 84, "Zona", 11, 600, MUTED) + select(196, 90, 230, "Z07 · Praça de alimentação"))
    o.append(T(442, 84, "Dia", 11, 600, MUTED) + select(442, 90, 170, "Sáb, 07/11/2026"))
    o.append(T(1240, 109, "Aberta ao clicar em uma zona do mapa", 12, 400, MUTED, "end"))

    # painel da zona
    o.append(R(24, 138, 372, 636))
    o.append(T(40, 168, "Praça de alimentação", 19, 700))
    o.append(R(40, 180, 62, 22, fill=SEQ[4], stroke=SEQ[4], rx=11) + T(71, 195.5, "quente", 11.5, 700, "#FFFFFF", "middle"))
    o.append(T(112, 195.5, "no dia inteiro de Sáb, 07/11", 12, 400, MUTED))
    campos = [("Identificador", "Z07"), ("Tipo", "Alimentação"), ("Área de circulação", "620 m²"),
              ("Câmera que cobre", "CAM-03 (ativa)"), ("Fonte do dado", "Câmera"), ("Janela", "15 minutos")]
    y = 236
    for k, v in campos:
        o.append(T(40, y, k, 12.5, 400, MUTED) + T(380, y, v, 12.5, 700, INK, "end"))
        o.append(L(40, y + 11, 380, y + 11, "#E4E7EB"))
        y += 32
    o.append(T(40, 446, "Localização no piso", 12, 700))
    fills = {z: "#EEF0F2" for z in ZONAS}; fills["Z07"] = SEQ[4]
    o.append(planta(40, 458, fills, {}, s=0.425, rotulos=False, destaque=None))
    o.append(R(40, 680, 340, 78, fill="#F7F9FB", rx=5))
    for j, l in enumerate(wrap("A CAM-03 também cobre a zona Convivência. Cada zona é um polígono "
                               "desenhado sobre o piso visto pela câmera.", 50)):
        o.append(T(50, 702 + j * 17, l, 12, 400, MUTED))

    # gráfico
    o.append(R(412, 138, 844, 474))
    o.append(T(428, 162, "Ocupação ao longo do dia · Sáb, 07/11/2026", 14, 700))
    # legenda do gráfico
    o.append(L(428, 182, 452, 182, ACCENT, 2.5) + T(458, 186, "densidade por janela de 15 min", 11.5))
    o.append(f'<circle cx="656" cy="182" r="5.5" fill="{SEQ[4]}" stroke="#FFFFFF" stroke-width="1.5"/>' + T(668, 186, "horário de pico", 11.5))
    o.append(R(778, 174, 26, 16, fill="url(#hach)", stroke="#52606D", rx=0) + T(812, 186, "abaixo do limiar (menos de 5 pessoas): sem valor", 11.5))
    gx0, gx1, gy0, gy1 = 486, 1228, 212, 556
    fx = lambda h: gx0 + (h - 10) / 12 * (gx1 - gx0)
    fy = lambda v: gy1 - v / 10 * (gy1 - gy0)
    for v in range(0, 11, 2):
        o.append(L(gx0, fy(v), gx1, fy(v), "#E4E7EB" if v else "#52606D"))
        o.append(T(gx0 - 8, fy(v) + 4, str(v), 11, 400, MUTED, "end"))
    for h in range(10, 23):
        o.append(L(fx(h), gy1, fx(h), gy1 + 5, "#52606D"))
        o.append(T(fx(h), gy1 + 20, f"{h}h", 11, 400, MUTED, "middle"))
    o.append(T(442, (gy0 + gy1) / 2, "pessoas por 100 m²", 11.5, 400, MUTED, "middle",
               f'transform="rotate(-90 442 {(gy0+gy1)/2})"'))
    xs, ys = serie()
    sup = set(range(0, 4)) | set(range(45, 48))  # janelas suprimidas
    for a, b in [(10, 11), (21.25, 22)]:
        o.append(f'<rect x="{fx(a)}" y="{gy0}" width="{fx(b)-fx(a)}" height="{gy1-gy0}" fill="url(#hach)" opacity="0.75"/>')
        cx = (fx(a) + fx(b)) / 2; cy = (gy0 + gy1) / 2
        o.append(f'<rect x="{cx-10}" y="{cy-56}" width="20" height="112" rx="3" fill="#FFFFFF"/>')
        o.append(T(cx, cy + 4, "abaixo do limiar", 11.5, 700, INK, "middle", f'transform="rotate(-90 {cx} {cy})"'))
    pts = [(fx(x + 0.125), fy(y)) for i, (x, y) in enumerate(zip(xs, ys)) if i not in sup]
    area = f"M{pts[0][0]:.1f},{gy1} " + " ".join(f"L{x:.1f},{y:.1f}" for x, y in pts) + f" L{pts[-1][0]:.1f},{gy1} z"
    o.append(f'<path d="{area}" fill="{ACCENT}" opacity="0.10"/>')
    o.append('<polyline fill="none" stroke="%s" stroke-width="2.5" stroke-linejoin="round" points="%s"/>'
             % (ACCENT, " ".join(f"{x:.1f},{y:.1f}" for x, y in pts)))
    ip = max((i for i in range(48) if i not in sup), key=lambda i: ys[i])
    pxk, pyk = fx(xs[ip] + 0.125), fy(ys[ip])
    o.append(L(pxk, pyk, pxk, gy1, SEQ[4], 1.2, 'stroke-dasharray="4 3"'))
    o.append(f'<circle cx="{pxk}" cy="{pyk}" r="6.5" fill="{SEQ[4]}" stroke="#FFFFFF" stroke-width="2"/>')
    o.append(L(pxk + 7, pyk, pxk + 64, pyk, SEQ[4], 1.2))
    o.append(R(pxk + 64, pyk - 14, 196, 40, fill="#FFFFFF", stroke=SEQ[4], rx=5))
    o.append(T(pxk + 74, pyk + 3, "Pico às 12h45", 12.5, 700))
    o.append(T(pxk + 74, pyk + 19, f"{num(ys[ip])} pessoas por 100 m²", 11.5, 400, MUTED))
    o.append(T((gx0 + gx1) / 2, 598, "horário de funcionamento (10h às 22h)", 11.5, 400, MUTED, "middle"))

    # indicadores
    vis = [ys[i] for i in range(48) if i not in sup]
    cards = [("Horário de pico", "12h45", "janela de 12h45 a 13h00"),
             ("Densidade no pico", num(ys[ip]), "pessoas por 100 m²"),
             ("Média das janelas com valor", num(sum(vis) / len(vis)), "pessoas por 100 m²"),
             ("Janelas abaixo do limiar", f"{len(sup)} de 48", "sem valor exibido")]
    for i, (a, b, c) in enumerate(cards):
        x = 412 + i * 213
        o.append(R(x, 624, 205, 84))
        o.append(T(x + 14, 646, a, 11.5, 600, MUTED) + T(x + 14, 676, b, 22, 700) + T(x + 14, 696, c, 11.5, 400, MUTED))
    o.append(R(412, 720, 844, 54, fill="#F7F9FB"))
    o.append(R(426, 738, 58, 18, fill=INK, stroke=INK, rx=9) + T(455, 751, "Câmera", 10.5, 700, "#FFFFFF", "middle"))
    o.append(T(494, 752, "A Praça de alimentação atinge o pico por volta das 12h45. Exemplo de ação: reforçar a limpeza a partir das 12h.", 12.5))
    return frame(2, "\n".join(o))


# ================================================================ TELA 4
def tela4():
    o = []
    o.append(R(24, 66, 760, 372))
    o.append(T(40, 92, "Câmeras e fontes de dados", 15, 700))
    o.append(T(768, 92, "Atualizado às 12h45 de Sáb, 07/11/2026", 11.5, 400, MUTED, "end"))
    cols = [(40, "CÂMERA"), (130, "ZONAS COBERTAS"), (420, "ESTADO"), (540, "FONTE"), (660, "ÚLTIMO ENVIO")]
    for x, c in cols:
        o.append(T(x, 122, c, 10.5, 700, MUTED))
    o.append(L(40, 132, 768, 132))
    rows = [("CAM-01", "Entrada Principal", "ativa", "Câmera", "12h45"),
            ("CAM-02", "Corredor Principal", "ativa", "Câmera", "12h45"),
            ("CAM-03", "Praça de alimentação, Convivência", "ativa", "Câmera", "12h45"),
            ("CAM-04", "Corredor Norte", "sem sinal", "Câmera", "11h15"),
            ("SIM-01", "11 zonas sem câmera (lojas, âncora, acessos)", "ativa", "Simulado", "12h45")]
    y = 162
    for cam, zs, est, fonte, ult in rows:
        o.append(T(40, y, cam, 12.5, 700) + T(130, y, zs, 12.5))
        if est == "ativa":
            o.append(f'<circle cx="426" cy="{y-4}" r="5" fill="#2E7D32"/>' + T(438, y, "Ativa", 12.5))
        else:
            o.append(f'<circle cx="426" cy="{y-4}" r="5" fill="#FFFFFF" stroke="{SEQ[4]}" stroke-width="2"/>'
                     + T(438, y, "Sem sinal", 12.5, 700, SEQ[4]))
        cam_ = fonte == "Câmera"
        tw = 62 if cam_ else 74
        o.append(R(540, y - 15, tw, 21, fill=INK if cam_ else "#FFFFFF", stroke=INK, rx=10))
        o.append(T(540 + tw / 2, y, fonte, 11.5, 700, "#FFFFFF" if cam_ else INK, "middle"))
        o.append(T(660, y, ult, 12.5, 400, MUTED))
        o.append(L(40, y + 16, 768, y + 16, "#E4E7EB"))
        y += 44
    o.append(R(40, 372, 728, 50, fill="#F7F9FB", rx=5))
    o.append(T(52, 393, "Zona de câmera sem sinal aparece no mapa como “sem dado”. Zona simulada leva sempre o rótulo", 12, 400, MUTED))
    o.append(T(52, 410, "“Simulação”. Uma câmera pode cobrir mais de uma zona.", 12, 400, MUTED))

    # o que coletamos
    x0, w0 = 800, 456
    o.append(R(x0, 66, w0, 180, stroke="#2E7D32", sw=1.5))
    o.append(f'<circle cx="{x0+28}" cy="90" r="11" fill="#2E7D32"/><path d="M{x0+22},90 l4,5 l8,-9" fill="none" stroke="#FFFFFF" stroke-width="2.4"/>')
    o.append(T(x0 + 48, 95, "O que coletamos", 15, 700))
    o.append(T(x0 + 20, 126, "Só a contagem de pessoas por zona e por janela de 15 minutos.", 12.5))
    o.append(T(x0 + 20, 150, "Registro enviado ao servidor:", 12, 400, MUTED))
    o.append(R(x0 + 20, 160, w0 - 40, 34, fill="#F0F4F8", rx=4))
    o.append(T(x0 + w0 / 2, 182, "{ zona_id, inicio_janela, duracao_min, contagem, fonte }", 12.5, 600, INK, "middle",
               "font-family=\"Consolas, 'Courier New', monospace\""))
    o.append(T(x0 + 20, 220, "Contagem menor que 5 é exibida como “abaixo do limiar”, sem número.", 12, 400, MUTED))

    o.append(R(x0, 258, w0, 180, stroke=SEQ[4], sw=1.5))
    o.append(f'<circle cx="{x0+28}" cy="282" r="11" fill="{SEQ[4]}"/><path d="M{x0+23},277 l10,10 M{x0+33},277 l-10,10" stroke="#FFFFFF" stroke-width="2.4"/>')
    o.append(T(x0 + 48, 287, "O que não coletamos", 15, 700))
    nao = ["Imagem ou vídeo: nenhum frame é gravado ou transmitido", "Rosto: não há reconhecimento facial",
           "Trajetória individual: ninguém é seguido entre zonas", "Identificador de aparelho: sem Wi-Fi e sem BLE"]
    for i, t in enumerate(nao):
        yy = 320 + i * 28
        o.append(f'<path d="M{x0+22},{yy-9} l9,9 M{x0+31},{yy-9} l-9,9" stroke="{SEQ[4]}" stroke-width="2"/>')
        o.append(T(x0 + 42, yy, t, 12.5))

    # esquema
    o.append(R(24, 450, 1232, 232))
    o.append(T(40, 476, "Como a contagem é feita (no agente de borda, ao lado da câmera)", 15, 700))
    bx = [(48, "1. Frame", "A câmera entrega", "um quadro ao agente"),
          (296, "2. Detecção", "YOLOX-S acha pessoas,", "na CPU, em memória"),
          (544, "3. Contagem", "Pessoas dentro de cada", "zona, por janela de 15 min"),
          (792, "4. Frame descartado", "Apagado da memória;", "nada vai para disco ou rede")]
    for i, (x, t, a, b) in enumerate(bx):
        last = i == 3
        o.append(R(x, 498, 200, 84, fill="#FFF5F5" if last else "#F7F9FB", stroke=SEQ[4] if last else "#52606D", sw=1.5))
        o.append(T(x + 100, 524, t, 13.5, 700, INK, "middle"))
        o.append(T(x + 100, 546, a, 11.5, 400, MUTED, "middle") + T(x + 100, 562, b, 11.5, 400, MUTED, "middle"))
        if i < 3:
            o.append(L(x + 204, 540, x + 244, 540, MUTED, 1.8, 'marker-end="url(#seta)"'))
    # saída para o servidor
    o.append(f'<path d="M644,582 L644,634 L1034,634" fill="none" stroke="{MUTED}" stroke-width="1.8" marker-end="url(#seta)"/>')
    o.append(T(662, 626, "POST só com a contagem agregada", 11.5, 400, MUTED))
    o.append(R(1040, 498, 192, 160, fill="#EDF7ED", stroke="#2E7D32", sw=1.5))
    o.append(T(1136, 524, "Servidor", 13.5, 700, INK, "middle"))
    o.append(T(1136, 546, "API valida o registro", 11.5, 400, MUTED, "middle"))
    o.append(T(1136, 562, "e grava no banco", 11.5, 400, MUTED, "middle"))
    o.append(L(1056, 578, 1216, 578, "#A5D6A7"))
    o.append(T(1136, 600, "Recebe: contagens", 12, 700, INK, "middle"))
    o.append(T(1136, 620, "Nunca recebe: imagem", 12, 700, SEQ[4], "middle"))
    o.append(T(1136, 644, "Mapa, ranking e gráficos", 11.5, 400, MUTED, "middle"))
    o.append(R(36, 488, 968, 106, fill="none", stroke="#7B8794", rx=6, sw=1.4, extra='stroke-dasharray="6 4"'))
    o.append(T(48, 624, "Linha tracejada: tudo ali dentro acontece no agente de borda.", 12, 400, MUTED))
    o.append(T(48, 644, "A imagem não sai dali.", 12.5, 700, INK))

    # aviso jurídico
    o.append(R(24, 694, 1232, 76, fill="#FFF8E1", stroke="#C99A00"))
    o.append(f'<path d="M52,716 l13,24 h-26 z" fill="#C99A00"/>' + T(52, 736, "!", 13, 700, "#FFFFFF", "middle"))
    o.append(T(80, 720, "Ponto em aberto: validação jurídica (LGPD)", 13, 700))
    o.append(T(80, 740, "Captar imagem, mesmo sem armazenar, pode ser tratamento de dado pessoal. Antes de usar em um shopping real, o desenho precisa de", 12))
    o.append(T(80, 757, "validação jurídica e de aviso ao público sobre as câmeras. Isto é uma hipótese de projeto, não uma conclusão.", 12))
    return frame(3, "\n".join(o))


TELAS = {"tela-1-mapa-de-calor": tela1, "tela-2-comparacao-de-periodos": tela2,
         "tela-3-detalhe-da-zona": tela3, "tela-4-cameras-e-privacidade": tela4}
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

if __name__ == "__main__":
    for nome, fn in TELAS.items():
        svg = os.path.join(HERE, nome + ".svg")
        png = os.path.join(HERE, nome + ".png")
        with open(svg, "w", encoding="utf-8") as f:
            f.write(fn())
        html = os.path.join(HERE, "_tmp.html")
        with open(html, "w", encoding="utf-8") as f:
            f.write('<!doctype html><meta charset="utf-8"><style>html,body{margin:0;overflow:hidden;background:#F4F6F8}'
                    'img{display:block}</style><img src="%s.svg" width="1280" height="800">' % nome)
        subprocess.run([CHROME, "--headless", "--disable-gpu", "--hide-scrollbars", "--window-size=1280,800",
                        "--force-device-scale-factor=1", "--screenshot=" + png,
                        "file:///" + html.replace("\\", "/")], check=True,
                       stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        os.remove(html)
        print("ok", png, os.path.getsize(png))
