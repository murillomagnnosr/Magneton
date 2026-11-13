"""Mapa de calor da área vista pela webcam: em que região ficam mais pessoas.

A imagem é dividida em zonas (3 colunas x 3 linhas). A cada frame, o centro de cada
pessoa detectada soma 1 na zona onde está. No fim, a ocupação média de cada zona é
soma / frames. Nenhum frame é gravado: o PNG gerado é um desenho abstrato das contagens.

Uso: python mapa_calor_camera.py --segundos 180
"""
import argparse
import json
import time
from pathlib import Path

import cv2
import numpy as np
import onnxruntime as ort

from contar_pessoas import MODELO, detectar

AQUI = Path(__file__).resolve().parent
COLUNAS = ["Esquerda", "Centro", "Direita"]
LINHAS = ["Topo", "Meio", "Base"]
FINO = (36, 64)  # grade fina (linhas, colunas) só para o desenho


def desenhar(fino, media_zona, quente, caminho, legenda):
    L, A = 960, 540
    img = cv2.resize(fino.astype(np.float32), (L, A), interpolation=cv2.INTER_CUBIC)
    img = cv2.GaussianBlur(img, (0, 0), 28)
    img = np.clip(img, 0, None)
    if img.max() > 0:
        img = img / img.max()
    cor = cv2.applyColorMap((img * 255).astype(np.uint8), cv2.COLORMAP_INFERNO)
    nl, nc = media_zona.shape
    for i in range(nl):
        for j in range(nc):
            x0, y0 = j * L // nc, i * A // nl
            x1, y1 = (j + 1) * L // nc, (i + 1) * A // nl
            e_quente = (i, j) == quente
            cv2.rectangle(cor, (x0, y0), (x1 - 1, y1 - 1), (255, 255, 255), 4 if e_quente else 1)
            cv2.putText(cor, f"{COLUNAS[j]}/{LINHAS[i]}", (x0 + 10, y0 + 26), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1, cv2.LINE_AA)
            cv2.putText(cor, f"{media_zona[i, j]:.2f}", (x0 + 10, y0 + 62), cv2.FONT_HERSHEY_SIMPLEX, 1.0, (255, 255, 255), 2, cv2.LINE_AA)
    faixa = np.zeros((40, L, 3), np.uint8)
    cv2.putText(faixa, legenda, (10, 27), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1, cv2.LINE_AA)
    cv2.imwrite(str(caminho), np.vstack((cor, faixa)))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--segundos", type=float, default=180)
    ap.add_argument("--camera", type=int, default=0)
    ap.add_argument("--conf", type=float, default=0.5)
    ap.add_argument("--janela", type=float, default=30, help="janela de tempo do relatório, em segundos")
    ap.add_argument("--prefixo", default="mapa_calor")
    a = ap.parse_args()

    sessao = ort.InferenceSession(str(MODELO), providers=["CPUExecutionProvider"])
    entrada = sessao.get_inputs()[0].name
    cam = cv2.VideoCapture(a.camera, cv2.CAP_DSHOW)
    if not cam.isOpened():
        raise SystemExit("ERRO: não foi possível abrir a câmera.")
    cam.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
    for _ in range(10):
        cam.read()

    nl, nc = len(LINHAS), len(COLUNAS)
    n_janelas = int(np.ceil(a.segundos / a.janela))
    soma = np.zeros((n_janelas, nl, nc))      # pessoas somadas por janela e zona
    frames_j = np.zeros(n_janelas)            # frames por janela
    fino = np.zeros(FINO)
    inicio, falhas = time.time(), 0

    while True:
        agora = time.time() - inicio
        if agora >= a.segundos:
            break
        ok, frame = cam.read()
        if not ok or frame is None:
            falhas += 1
            if falhas > 50:
                break
            continue
        alt, larg = frame.shape[:2]
        caixas = detectar(sessao, entrada, frame, a.conf, 0.45)
        del frame  # o frame nunca é gravado
        j_tempo = min(int(agora // a.janela), n_janelas - 1)
        frames_j[j_tempo] += 1
        for x1, y1, x2, y2 in caixas:
            cx = min(max((x1 + x2) / 2 / larg, 0), 0.9999)
            cy = min(max((y1 + y2) / 2 / alt, 0), 0.9999)
            soma[j_tempo, int(cy * nl), int(cx * nc)] += 1
            fino[int(cy * FINO[0]), int(cx * FINO[1])] += 1
    cam.release()

    total_frames = int(frames_j.sum())
    if total_frames == 0:
        raise SystemExit("ERRO: nenhum frame lido.")
    media_zona = soma.sum(0) / total_frames               # ocupação média por zona
    quente = np.unravel_index(media_zona.argmax(), media_zona.shape)
    total = media_zona.sum()

    ranking = sorted(
        ({"zona": f"{COLUNAS[j]}/{LINHAS[i]}",
          "ocupacao_media": round(float(media_zona[i, j]), 3),
          "participacao_pct": round(100 * float(media_zona[i, j]) / total, 1) if total else 0.0}
         for i in range(nl) for j in range(nc)),
        key=lambda z: -z["ocupacao_media"])

    por_janela = []
    for t in range(n_janelas):
        if frames_j[t] == 0:
            continue
        m = soma[t] / frames_j[t]
        i, j = np.unravel_index(m.argmax(), m.shape)
        por_janela.append({"inicio_s": int(t * a.janela), "total_medio": round(float(m.sum()), 2),
                           "zona_mais_cheia": f"{COLUNAS[j]}/{LINHAS[i]}" if m.max() > 0 else None,
                           "ocupacao_da_zona": round(float(m[i, j]), 2)})

    resumo = {"duracao_s": round(time.time() - inicio, 1), "frames_analisados": total_frames,
              "zona_mais_movimentada": ranking[0], "ranking": ranking, "por_janela": por_janela,
              "imagens_de_camera_gravadas": 0}
    (AQUI / f"{a.prefixo}.json").write_text(json.dumps(resumo, ensure_ascii=False, indent=2), encoding="utf-8")
    desenhar(fino, media_zona, quente, AQUI / f"{a.prefixo}.png",
             f"Ocupacao media por zona (pessoas) - {int(a.segundos)} s - {total_frames} frames - desenho abstrato, sem imagem da camera")
    print(json.dumps(resumo, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
