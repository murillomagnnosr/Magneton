"""Gera o PDF das seções 2 a 6 do ENTREGA-1.md (HTML + Chrome headless)."""
import html
import re
import subprocess
from pathlib import Path

AQUI = Path(__file__).resolve().parent
MD = AQUI / "ENTREGA-1.md"
HTML = AQUI / "ENTREGA-1-completa.html"
PDF = AQUI / "Entrega 1 13-11.pdf"
NAVEGADORES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
]

# Os dois diagramas Mermaid do documento, redesenhados em HTML para o PDF.
DIAGRAMAS = [
    """<div class="diag">
  <div class="col"><div class="no">Câmera do shopping</div></div>
  <div class="seta"><span>frames</span>&rarr;</div>
  <div class="col">
    <div class="grupo"><div class="tit">Agente de borda (Python)</div>
      <div class="no">Detecção de pessoas: <span style="white-space:nowrap">YOLOX-S</span></div><div class="desce">&darr;</div>
      <div class="no">Contagem por zona e janela</div><div class="desce">&darr;</div>
      <div class="no apagado">Frame descartado em memória</div>
    </div>
    <div class="no solto">Simulador (Python e NumPy)</div>
  </div>
  <div class="seta dupla">
    <div><span>POST de contagens,<br>fonte = camera</span>&rarr;</div>
    <div><span>POST de contagens,<br>fonte = simulado</span>&rarr;</div>
  </div>
  <div class="col">
    <div class="grupo"><div class="tit">Servidor (Python)</div>
      <div class="no">API FastAPI: validação do contrato</div><div class="desce">&darr;</div>
      <div class="no">SQLite</div><div class="desce">&darr;</div>
      <div class="no">Camada de publicação: limiar k = 5</div>
    </div>
  </div>
  <div class="seta"><span>GET: classes, blocos e valores já filtrados</span>&rarr;</div>
  <div class="col"><div class="no">Página web: HTML, CSS, JS, Three.js, Chart.js</div></div>
</div>""",
    """<div class="diag">
  <div class="col">
    <div class="no">Ação do usuário: mover o horário, clicar em uma zona</div>
    <div class="no solto">Resposta da API</div>
  </div>
  <div class="seta">&rarr;</div>
  <div class="col"><div class="no forte">Repositório de estado único</div></div>
  <div class="seta"><span>avisa quem está inscrito</span>&rarr;</div>
  <div class="col">
    <div class="no">Cena 3D do mapa</div><div class="no solto">Ranking e frases</div>
    <div class="no solto">Curva da zona</div><div class="no solto">Selo de fonte</div>
  </div>
</div>""",
    """<div class="diag">
  <div class="col"><div class="no forte">Tela 1: Mapa de calor</div></div>
  <div class="seta"><span>clique em uma zona</span>&rarr;</div>
  <div class="col"><div class="no">Tela 3: Detalhe da zona</div></div>
</div>
<p style="font-size:9pt;color:#5b6570">Demais ligações pelo menu do topo, que leva às quatro telas de qualquer ponto: Tela 1 para Tela 2 (comparação de períodos) e Tela 4 (câmeras e privacidade); Tela 2 e Tela 3 de volta à Tela 1; Tela 2 para Tela 3 pelo clique em uma zona.</p>""",
]

CSS = """
@page { size: A4; margin: 16mm 15mm; }
* { box-sizing: border-box; }
body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10.5pt; line-height: 1.45; color: #1a1d21; margin: 0; }
.topo { font-size: 9pt; color: #5b6570; border-bottom: 1px solid #c9d0d8; padding-bottom: 4px; margin-bottom: 10px; }
h2 { font-size: 15pt; color: #1f4e79; margin: 20px 0 8px; break-after: avoid; }
h2:first-of-type { margin-top: 0; }
h3 { font-size: 11.5pt; margin: 14px 0 6px; break-after: avoid; }
p { margin: 6px 0; }
ul { margin: 6px 0; padding-left: 20px; }
li { margin: 3px 0; }
table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 9.5pt; }
th, td { border: 1px solid #c9d0d8; padding: 5px 7px; text-align: left; vertical-align: top; }
th { background: #eaf0f6; }
tr { break-inside: avoid; }
code { font-family: Consolas, monospace; font-size: 9.5pt; background: #f0f2f5; padding: 0 3px; border-radius: 3px; }
pre { background: #f0f2f5; border: 1px solid #d5dbe2; border-radius: 4px; padding: 8px 10px; font-family: Consolas, monospace; font-size: 9pt; white-space: pre-wrap; break-inside: avoid; }
hr { border: 0; border-top: 1px solid #c9d0d8; margin: 16px 0 0; }
figure { margin: 10px 0; break-inside: avoid; }
figure img { width: 100%; border-radius: 6px; display: block; }
figcaption { font-size: 9pt; color: #5b6570; margin-top: 4px; }
.diag { display: flex; align-items: center; gap: 6px; margin: 10px 0; font-size: 8.5pt; break-inside: avoid; }
.col { flex: 1 1 0; display: flex; flex-direction: column; }
.no { border: 1px solid #1f4e79; background: #fff; border-radius: 4px; padding: 6px; text-align: center; }
.no.solto { margin-top: 8px; }
.no.apagado { border-style: dashed; color: #5b6570; }
.no.forte { background: #1f4e79; color: #fff; font-weight: 600; padding: 12px 6px; }
.grupo { border: 1px dashed #5b6570; border-radius: 6px; padding: 6px; background: #f6f8fa; }
.tit { font-weight: 600; text-align: center; margin-bottom: 5px; }
.desce { text-align: center; line-height: 1.1; }
.seta { flex: 0 0 auto; max-width: 80px; text-align: center; font-size: 13pt; line-height: 1; color: #1f4e79; }
.seta span { display: block; font-size: 7.5pt; line-height: 1.2; color: #1a1d21; margin-bottom: 2px; }
.seta.dupla { display: flex; flex-direction: column; gap: 34px; }
"""


def inline(t):
    t = html.escape(t, quote=False)
    t = re.sub(r"`([^`]+)`", r"<code>\1</code>", t)
    return re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", t)


def converter(linhas):
    out, i, n_diag = [], 0, 0
    while i < len(linhas):
        l = linhas[i]
        img = re.match(r"!\[(.*)\]\((.+)\)$", l.strip())
        if img:
            out.append(f'<figure><img src="{img.group(2)}" alt=""><figcaption>{inline(img.group(1))}</figcaption></figure>')
        elif l.startswith("```"):
            tipo, bloco = l[3:].strip(), []
            i += 1
            while not linhas[i].startswith("```"):
                bloco.append(linhas[i])
                i += 1
            if tipo == "mermaid":
                out.append(DIAGRAMAS[[2, 0, 1][n_diag]])  # ordem no documento: navegação, arquitetura, estado
                n_diag += 1
            else:
                out.append("<pre>" + html.escape("\n".join(bloco), quote=False) + "</pre>")
        elif l.startswith("|"):
            tab = []
            while i < len(linhas) and linhas[i].startswith("|"):
                tab.append([c.strip() for c in linhas[i].strip().strip("|").split("|")])
                i += 1
            i -= 1
            cab, corpo = tab[0], tab[2:]
            if any(cab):
                out.append("<table><tr>" + "".join(f"<th>{inline(c)}</th>" for c in cab) + "</tr>")
            else:
                out.append("<table>")
            out += ["<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>" for r in corpo]
            out.append("</table>")
        elif l.startswith("- "):
            out.append("<ul>")
            while i < len(linhas) and linhas[i].startswith("- "):
                out.append(f"<li>{inline(linhas[i][2:])}</li>")
                i += 1
            i -= 1
            out.append("</ul>")
        elif l.startswith("### "):
            out.append(f"<h3>{inline(l[4:])}</h3>")
        elif l.startswith("## "):
            out.append(f"<h2>{inline(l[3:])}</h2>")
        elif l.strip() == "---":
            out.append("<hr>")
        elif l.strip():
            out.append(f"<p>{inline(l)}</p>")
        i += 1
    assert n_diag == len(DIAGRAMAS), f"esperava {len(DIAGRAMAS)} diagramas, achei {n_diag}"
    return "\n".join(out)


corpo = converter(MD.read_text(encoding="utf-8").split("\n"))
HTML.write_text(
    f'<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Primeira entrega: Magneton</title>'
    f"<style>{CSS}</style></head><body>"
    f'<div class="topo">HackaRun 2026 · Desafio 02: Inteligência sobre fluxo e movimentação de pessoas · Primeira entrega<br>'
    f'Projeto e equipe Magneton: Carlos Henrique Cardoso da Silva Rocha, Guilherme Leão Teles, Murillo Magnno de Sousa Rêis e Samuel da Silva Ramos de Souza<br>'
    f'Repositório: https://github.com/murillomagnnosr/Magneton</div>'
    f"{corpo}</body></html>",
    encoding="utf-8",
)
nav = next((p for p in NAVEGADORES if Path(p).exists()), None)
if not nav:
    raise SystemExit("ERRO: Chrome ou Edge não encontrado.")
subprocess.run([nav, "--headless", "--disable-gpu", "--no-pdf-header-footer",
                f"--print-to-pdf={PDF}", HTML.as_uri()], check=True, capture_output=True, timeout=120)
print(f"{PDF} ({PDF.stat().st_size} bytes)")
