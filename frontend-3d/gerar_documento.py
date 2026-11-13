"""Gera a ilustração do conceito (SVG) e o PDF do FRONTEND-3D.md."""
import html
import random
import re
import subprocess
from pathlib import Path

AQUI = Path(__file__).resolve().parent
MD = AQUI / "FRONTEND-3D.md"
SVG = AQUI / "conceito-blocos.svg"
HTML = AQUI / "FRONTEND-3D.html"
PDF = AQUI / "FRONTEND-3D.pdf"
NAVEGADORES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
]

# ---------- ilustração isométrica ----------
S, OX, OY = 17, 330, 70


def iso(x, y, z=0.0):
    return (OX + (x - y) * 0.866 * S, OY + (x + y) * 0.5 * S - z * S)


def pts(lista):
    return " ".join(f"{a:.1f},{b:.1f}" for a, b in lista)


def clarear(cor, f):
    r, g, b = (int(cor[i:i + 2], 16) for i in (1, 3, 5))
    return "#%02x%02x%02x" % tuple(max(0, min(255, int(c * f))) for c in (r, g, b))


# nome, x0, y0, x1, y1, blocos, cor, tipo
ZONAS = [
    ("Lojas: 7", 11, 0, 22, 7, 7, "#f6d55c", "camera"),
    ("Praça de alimentação: 34", 0, 0, 10, 7, 34, "#e03a3a", "camera"),
    ("Corredor: 14", 0, 8, 22, 11, 14, "#f08a3c", "camera"),
    ("Convivência: abaixo do limiar", 0, 12, 10, 18, 0, None, "limiar"),
    ("Ala simulada: 9", 11, 12, 22, 18, 9, "#6ec6ff", "simulado"),
]


def gerar_svg():
    rnd = random.Random(20261113)
    o = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 760 500" font-family="Segoe UI, Arial, sans-serif">',
         '<defs><pattern id="h" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
         '<rect width="8" height="8" fill="#141b2d"/><line x1="0" y1="0" x2="0" y2="8" stroke="#55627d" stroke-width="2"/></pattern></defs>',
         '<rect width="760" height="500" fill="#0a0f1c"/>']
    cubos, rotulos = [], []
    for nome, x0, y0, x1, y1, n, cor, tipo in ZONAS:
        chao = [iso(x0, y0), iso(x1, y0), iso(x1, y1), iso(x0, y1)]
        fill = "url(#h)" if tipo == "limiar" else "#141b2d"
        traco = ' stroke-dasharray="5 4"' if tipo == "simulado" else ""
        o.append(f'<polygon points="{pts(chao)}" fill="{fill}" stroke="#3d7dd8" stroke-width="1.3"{traco}/>')
        celulas = [(i, j) for i in range(x0, x1) for j in range(y0, y1)]
        rnd.shuffle(celulas)
        ocupadas = {}
        for k in range(n):
            i, j = celulas[k % len(celulas)]
            nivel = ocupadas.get((i, j), 0)
            ocupadas[(i, j)] = nivel + 1
            cubos.append((i, j, nivel, cor, tipo))
        rotulos.append((nome, iso((x0 + x1) / 2, y1)))
    # praça de alimentação: empilha parte dos blocos para mostrar a "torre"
    extra = [(i, j, 1, "#e03a3a", "camera") for i, j in [(3, 2), (4, 2), (4, 3), (5, 3), (3, 3), (5, 2)]]
    extra += [(4, 2, 2, "#e03a3a", "camera"), (4, 3, 2, "#e03a3a", "camera")]
    for i, j, nivel, cor, tipo in sorted(cubos + extra, key=lambda c: (c[0] + c[1], c[2])):
        a, b, z0, z1 = i + 0.12, j + 0.12, nivel * 0.8, nivel * 0.8 + 0.76
        c, d = i + 0.88, j + 0.88
        topo = [iso(a, b, z1), iso(c, b, z1), iso(c, d, z1), iso(a, d, z1)]
        dire = [iso(c, b, z0), iso(c, d, z0), iso(c, d, z1), iso(c, b, z1)]
        esq = [iso(a, d, z0), iso(c, d, z0), iso(c, d, z1), iso(a, d, z1)]
        if tipo == "simulado":
            for face in (topo, dire, esq):
                o.append(f'<polygon points="{pts(face)}" fill="{cor}" fill-opacity=".22" stroke="{cor}" stroke-width="1"/>')
        else:
            o.append(f'<polygon points="{pts(esq)}" fill="{clarear(cor, .62)}"/>')
            o.append(f'<polygon points="{pts(dire)}" fill="{clarear(cor, .8)}"/>')
            o.append(f'<polygon points="{pts(topo)}" fill="{cor}"/>')
    for nome, (x, y) in rotulos:
        o.append(f'<text x="{x:.0f}" y="{y + 16:.0f}" text-anchor="middle" font-size="12.5" fill="#e8eefc" '
                 f'stroke="#0a0f1c" stroke-width="3" paint-order="stroke">{html.escape(nome)}</text>')
    o.append('<text x="20" y="30" font-size="14" font-weight="600" fill="#e8eefc">1 bloco = 1 pessoa contada na zona · posição sorteada</text>')
    y = 455
    for i, (cor, txt) in enumerate([("#f6d55c", "baixa"), ("#f08a3c", "média"), ("#e03a3a", "alta")]):
        o.append(f'<rect x="{20 + i * 82}" y="{y}" width="14" height="14" fill="{cor}"/>'
                 f'<text x="{40 + i * 82}" y="{y + 12}" font-size="12" fill="#e8eefc">{txt}</text>')
    o.append(f'<rect x="276" y="{y}" width="14" height="14" fill="#6ec6ff" fill-opacity=".22" stroke="#6ec6ff" stroke-width="1.3"/>'
             f'<text x="296" y="{y + 12}" font-size="12" fill="#e8eefc">zona simulada</text>')
    o.append(f'<rect x="400" y="{y}" width="14" height="14" fill="url(#h)" stroke="#55627d"/>'
             f'<text x="420" y="{y + 12}" font-size="12" fill="#e8eefc">abaixo do limiar: sem blocos</text>')
    o.append('<text x="740" y="488" text-anchor="end" font-size="11" fill="#8fa0c2">Ilustração do conceito. Não é a planta real.</text></svg>')
    SVG.write_text("\n".join(o), encoding="utf-8")


# ---------- Markdown para HTML ----------
CSS = """
@page { size: A4; margin: 16mm 15mm; }
* { box-sizing: border-box; }
body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10.5pt; line-height: 1.45; color: #1a1d21; margin: 0; }
h1 { font-size: 19pt; margin: 0 0 6px; color: #12233b; }
h2 { font-size: 14.5pt; color: #1f4e79; margin: 20px 0 8px; border-bottom: 1px solid #c9d0d8; padding-bottom: 3px; break-after: avoid; }
h3 { font-size: 11.5pt; margin: 14px 0 6px; break-after: avoid; }
p { margin: 6px 0; }
ul, ol { margin: 6px 0; padding-left: 22px; }
li { margin: 3px 0; }
table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 9.3pt; }
th, td { border: 1px solid #c9d0d8; padding: 5px 7px; text-align: left; vertical-align: top; }
th { background: #eaf0f6; }
tr { break-inside: avoid; }
code { font-family: Consolas, monospace; font-size: 9.3pt; background: #f0f2f5; padding: 0 3px; border-radius: 3px; }
pre { background: #f0f2f5; border: 1px solid #d5dbe2; border-radius: 4px; padding: 8px 10px; font-family: Consolas, monospace; font-size: 9pt; white-space: pre-wrap; break-inside: avoid; }
figure { margin: 12px 0; break-inside: avoid; }
figure img { width: 100%; border-radius: 6px; display: block; }
figcaption { font-size: 9pt; color: #5b6570; margin-top: 4px; }
"""


def inline(t):
    t = html.escape(t, quote=False)
    t = re.sub(r"`([^`]+)`", r"<code>\1</code>", t)
    return re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", t)


def lista(linhas, i, padrao, tag):
    out = [f"<{tag}>"]
    while i < len(linhas) and re.match(padrao, linhas[i]):
        out.append(f"<li>{inline(re.sub(padrao, '', linhas[i], count=1))}</li>")
        i += 1
    out.append(f"</{tag}>")
    return out, i - 1


def converter(linhas):
    out, i = [], 0
    while i < len(linhas):
        l = linhas[i]
        img = re.match(r"!\[(.*)\]\((.+)\)$", l.strip())
        if l.startswith("```"):
            bloco = []
            i += 1
            while not linhas[i].startswith("```"):
                bloco.append(linhas[i])
                i += 1
            out.append("<pre>" + html.escape("\n".join(bloco), quote=False) + "</pre>")
        elif img:
            out.append(f'<figure><img src="{img.group(2)}" alt=""><figcaption>{inline(img.group(1))}</figcaption></figure>')
        elif l.startswith("|"):
            tab = []
            while i < len(linhas) and linhas[i].startswith("|"):
                tab.append([c.strip() for c in linhas[i].strip().strip("|").split("|")])
                i += 1
            i -= 1
            out.append("<table><tr>" + "".join(f"<th>{inline(c)}</th>" for c in tab[0]) + "</tr>")
            out += ["<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>" for r in tab[2:]]
            out.append("</table>")
        elif l.startswith("- "):
            bloco, i = lista(linhas, i, r"- ", "ul")
            out += bloco
        elif re.match(r"\d+\. ", l):
            bloco, i = lista(linhas, i, r"\d+\. ", "ol")
            out += bloco
        elif l.startswith("### "):
            out.append(f"<h3>{inline(l[4:])}</h3>")
        elif l.startswith("## "):
            out.append(f"<h2>{inline(l[3:])}</h2>")
        elif l.startswith("# "):
            out.append(f"<h1>{inline(l[2:])}</h1>")
        elif l.strip():
            out.append(f"<p>{inline(l)}</p>")
        i += 1
    return "\n".join(out)


gerar_svg()
corpo = converter(MD.read_text(encoding="utf-8").split("\n"))
HTML.write_text(f'<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Frontend 3D</title>'
                f"<style>{CSS}</style></head><body>{corpo}</body></html>", encoding="utf-8")
nav = next((p for p in NAVEGADORES if Path(p).exists()), None)
if not nav:
    raise SystemExit("ERRO: Chrome ou Edge não encontrado.")
subprocess.run([nav, "--headless", "--disable-gpu", "--no-pdf-header-footer",
                f"--print-to-pdf={PDF}", HTML.as_uri()], check=True, capture_output=True, timeout=120)
print(f"{PDF} ({PDF.stat().st_size} bytes)")
