"""Conta pessoas pela webcam durante um intervalo, sem guardar imagem.

Cada frame é processado em memória e descartado. A saída é só contagem agregada.
Uso: python contar_pessoas.py --segundos 180
"""
import argparse
import json
import time
from pathlib import Path

import cv2
import numpy as np
import onnxruntime as ort

AQUI = Path(__file__).resolve().parent
MODELO = AQUI / "modelo" / "yolox_s.onnx"
TAM = 640
CLASSE_PESSOA = 0


def preparar(frame):
    """Letterbox para 640x640 (preenchimento 114), RGB, float32, NCHW."""
    h, w = frame.shape[:2]
    r = min(TAM / h, TAM / w)
    nh, nw = int(h * r), int(w * r)
    img = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    img = cv2.resize(img, (nw, nh), interpolation=cv2.INTER_LINEAR)
    tela = np.full((TAM, TAM, 3), 114, dtype=np.float32)
    tela[:nh, :nw] = img
    return tela.transpose(2, 0, 1)[None], r


def montar_grades():
    grades, passos = [], []
    for s in (8, 16, 32):
        n = TAM // s
        ys, xs = np.meshgrid(np.arange(n), np.arange(n), indexing="ij")
        grades.append(np.stack((xs, ys), -1).reshape(-1, 2))
        passos.append(np.full((n * n, 1), s))
    return np.concatenate(grades).astype(np.float32), np.concatenate(passos).astype(np.float32)


GRADES, PASSOS = montar_grades()


def detectar(sessao, nome_entrada, frame, conf, nms):
    """Devolve lista de caixas [x1, y1, x2, y2] de pessoas no frame."""
    blob, r = preparar(frame)
    saida = sessao.run(None, {nome_entrada: blob})[0][0]
    xy = (saida[:, :2] + GRADES) * PASSOS
    wh = np.exp(saida[:, 2:4]) * PASSOS
    pontos = saida[:, 4] * saida[:, 5 + CLASSE_PESSOA]
    # só conta como pessoa se "pessoa" for a classe mais provável
    eh_pessoa = saida[:, 5:].argmax(1) == CLASSE_PESSOA
    ok = (pontos >= conf) & eh_pessoa
    if not ok.any():
        return []
    xy, wh, pontos = xy[ok] / r, wh[ok] / r, pontos[ok]
    caixas_xywh = np.concatenate((xy - wh / 2, wh), 1)
    idx = cv2.dnn.NMSBoxes(caixas_xywh.tolist(), pontos.tolist(), conf, nms)
    idx = np.array(idx).reshape(-1)
    return [[x, y, x + w, y + h] for x, y, w, h in caixas_xywh[idx]]


def iou(a, b):
    x1, y1 = max(a[0], b[0]), max(a[1], b[1])
    x2, y2 = min(a[2], b[2]), min(a[3], b[3])
    inter = max(0.0, x2 - x1) * max(0.0, y2 - y1)
    uniao = (a[2] - a[0]) * (a[3] - a[1]) + (b[2] - b[0]) * (b[3] - b[1]) - inter
    return inter / uniao if uniao > 0 else 0.0


class Rastreador:
    """Rastreador simples por IoU. Só existe para não contar a mesma pessoa a cada frame.

    Uma trilha é confirmada (vira "pessoa distinta") depois de `min_acertos` frames.
    Quem sai da cena por mais de `tolerancia_s` e volta é contado de novo.
    """

    def __init__(self, min_acertos=8, tolerancia_s=3.0, iou_min=0.25):
        self.trilhas = []
        self.min_acertos, self.tolerancia_s, self.iou_min = min_acertos, tolerancia_s, iou_min
        self.confirmadas = 0

    def atualizar(self, caixas, agora):
        livres = list(range(len(caixas)))
        pares = sorted(
            ((iou(t["caixa"], caixas[j]), i, j) for i, t in enumerate(self.trilhas) for j in livres),
            reverse=True,
        )
        usadas_t, usadas_c = set(), set()
        for valor, i, j in pares:
            if valor < self.iou_min:
                break
            if i in usadas_t or j in usadas_c:
                continue
            usadas_t.add(i)
            usadas_c.add(j)
            t = self.trilhas[i]
            t["caixa"], t["visto"] = caixas[j], agora
            t["acertos"] += 1
            if not t["confirmada"] and t["acertos"] >= self.min_acertos:
                t["confirmada"] = True
                self.confirmadas += 1
        for j in livres:
            if j not in usadas_c:
                self.trilhas.append({"caixa": caixas[j], "visto": agora, "acertos": 1, "confirmada": False})
        self.trilhas = [t for t in self.trilhas if agora - t["visto"] <= self.tolerancia_s]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--segundos", type=float, default=180)
    ap.add_argument("--camera", type=int, default=0)
    ap.add_argument("--conf", type=float, default=0.5)
    ap.add_argument("--nms", type=float, default=0.45)
    ap.add_argument("--bloco", type=float, default=15, help="tamanho do bloco do relatório, em segundos")
    ap.add_argument("--saida", default=str(AQUI / "resultado.json"))
    a = ap.parse_args()

    sessao = ort.InferenceSession(str(MODELO), providers=["CPUExecutionProvider"])
    nome_entrada = sessao.get_inputs()[0].name

    cam = cv2.VideoCapture(a.camera, cv2.CAP_DSHOW)
    if not cam.isOpened():
        raise SystemExit("ERRO: não foi possível abrir a câmera (em uso por outro programa ou bloqueada nas configurações de privacidade do Windows).")
    cam.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    cam.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
    for _ in range(10):  # descarta os primeiros frames (ajuste de exposição)
        cam.read()

    rastreador = Rastreador()
    inicio = time.time()
    frames = falhas = frames_com_pessoa = 0
    soma = maximo = 0
    brilho = []
    blocos, bloco_atual, resolucao = [], [], None

    def fechar_bloco(indice):
        if bloco_atual:
            blocos.append({
                "inicio_s": int(indice * a.bloco),
                "media": round(float(np.mean(bloco_atual)), 2),
                "max": int(max(bloco_atual)),
            })
            print(f"  {int(indice * a.bloco):>4}s  média {blocos[-1]['media']:.2f}  máx {blocos[-1]['max']}", flush=True)
            bloco_atual.clear()

    indice = 0
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
        resolucao = f"{frame.shape[1]}x{frame.shape[0]}"
        if frames % 30 == 0:
            brilho.append(float(frame.mean()))
        caixas = detectar(sessao, nome_entrada, frame, a.conf, a.nms)
        del frame  # o frame nunca é gravado
        rastreador.atualizar(caixas, agora)
        n = len(caixas)
        frames += 1
        soma += n
        maximo = max(maximo, n)
        frames_com_pessoa += n > 0
        if int(agora // a.bloco) != indice:
            fechar_bloco(indice)
            indice = int(agora // a.bloco)
        bloco_atual.append(n)
    fechar_bloco(indice)
    duracao = time.time() - inicio
    cam.release()

    resumo = {
        "duracao_s": round(duracao, 1),
        "resolucao": resolucao,
        "frames_analisados": frames,
        "frames_por_segundo": round(frames / duracao, 1) if duracao else 0,
        "falhas_de_leitura": falhas,
        "brilho_medio_0_255": round(float(np.mean(brilho)), 1) if brilho else None,
        "pessoas_distintas_estimadas": rastreador.confirmadas,
        "maximo_simultaneo": maximo,
        "media_por_frame": round(soma / frames, 2) if frames else 0,
        "percentual_frames_com_pessoa": round(100 * frames_com_pessoa / frames, 1) if frames else 0,
        "confianca_minima": a.conf,
        "blocos": blocos,
        "imagens_gravadas": 0,
    }
    Path(a.saida).write_text(json.dumps(resumo, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({k: v for k, v in resumo.items() if k != "blocos"}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
