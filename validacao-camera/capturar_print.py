"""Grava UM frame da webcam com as pessoas detectadas e a grade de 9 zonas.

Atenção: ao contrário dos outros scripts, este grava imagem real da câmera.
Serve só para conferir o enquadramento; apague o arquivo depois de comparar.
"""
from pathlib import Path

import cv2
import onnxruntime as ort

from contar_pessoas import MODELO, detectar
from mapa_calor_camera import COLUNAS, LINHAS

AQUI = Path(__file__).resolve().parent

sessao = ort.InferenceSession(str(MODELO), providers=["CPUExecutionProvider"])
cam = cv2.VideoCapture(0, cv2.CAP_DSHOW)
if not cam.isOpened():
    raise SystemExit("ERRO: não foi possível abrir a câmera.")
cam.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
for _ in range(15):
    cam.read()
ok, frame = cam.read()
cam.release()
if not ok:
    raise SystemExit("ERRO: frame não lido.")

alt, larg = frame.shape[:2]
caixas = detectar(sessao, sessao.get_inputs()[0].name, frame, 0.5, 0.45)
nl, nc = len(LINHAS), len(COLUNAS)
for i in range(nl):
    for j in range(nc):
        x0, y0 = j * larg // nc, i * alt // nl
        cv2.rectangle(frame, (x0, y0), ((j + 1) * larg // nc - 1, (i + 1) * alt // nl - 1), (255, 255, 255), 1)
        cv2.putText(frame, f"{COLUNAS[j]}/{LINHAS[i]}", (x0 + 10, y0 + 28), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 0), 4, cv2.LINE_AA)
        cv2.putText(frame, f"{COLUNAS[j]}/{LINHAS[i]}", (x0 + 10, y0 + 28), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 1, cv2.LINE_AA)
zonas = []
for x1, y1, x2, y2 in caixas:
    cx, cy = int((x1 + x2) / 2), int((y1 + y2) / 2)
    cv2.rectangle(frame, (int(x1), int(y1)), (int(x2), int(y2)), (0, 255, 0), 2)
    cv2.circle(frame, (cx, cy), 8, (0, 0, 255), -1)  # ponto usado no mapa de calor
    zonas.append(f"{COLUNAS[min(cx * nc // larg, nc - 1)]}/{LINHAS[min(cy * nl // alt, nl - 1)]}")
saida = AQUI / "print_deteccao.png"
cv2.imwrite(str(saida), frame)
print(f"pessoas detectadas: {len(caixas)}")
print("zona de cada pessoa (ponto vermelho):", zonas)
print(f"arquivo: {saida} ({larg}x{alt})")
