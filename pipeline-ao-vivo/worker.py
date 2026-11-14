"""Worker de detecção e contagem: lê os streams do MediaMTX, roda YOLO + ByteTrack
e grava só números no banco que o dashboard lê.

Cada frame é processado em memória e descartado. Nada de imagem vai para o disco.

Uso:
    python worker.py              # câmeras de cameras.json
    python worker.py --mostrar    # abre janelas de conferência (só na tela, sem gravar)
"""
import argparse
import os
import threading
import time

# Precisa vir antes de importar o cv2: RTSP por TCP e sem buffer de atraso.
os.environ.setdefault(
    "OPENCV_FFMPEG_CAPTURE_OPTIONS",
    "rtsp_transport;tcp|fflags;nobuffer|flags;low_delay|max_delay;500000",
)

import cv2
import numpy as np
import torch
from ultralytics import YOLO

from armazenamento import apagar_amostras_ate, carregar_config, conectar, gravar, ler_zerar

CLASSE_PESSOA = 0
ESQUECER_TRILHA_S = 30   # tempo sem ver uma trilha até apagar o estado dela
OFFLINE_APOS_S = 5       # sem frame novo por esse tempo, a câmera conta como fora do ar


class Leitor(threading.Thread):
    """Lê o stream sem parar e guarda só o frame mais recente (evita atraso acumulado)."""

    def __init__(self, url):
        super().__init__(daemon=True)
        self.url = url
        self.trava = threading.Lock()
        self.frame, self.numero, self.visto = None, 0, 0.0

    def abrir(self):
        if str(self.url).isdigit():  # "0", "1": webcam do próprio PC, útil para teste
            return cv2.VideoCapture(int(self.url), cv2.CAP_DSHOW)
        return cv2.VideoCapture(self.url, cv2.CAP_FFMPEG)

    def run(self):
        while True:
            cap = self.abrir()
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            while cap.isOpened():
                ok, frame = cap.read()
                if not ok or frame is None:
                    break
                with self.trava:
                    self.frame, self.visto = frame, time.time()
                    self.numero += 1
            cap.release()
            time.sleep(2)  # stream caiu ou ainda não começou: tenta de novo

    def mais_recente(self):
        with self.trava:
            return self.frame, self.numero, self.visto


def lado_da_linha(linha, x, y):
    (ax, ay), (bx, by) = linha
    return (bx - ax) * (y - ay) - (by - ay) * (x - ax)


class Camera(threading.Thread):
    """Uma câmera: leitor + modelo + rastreador próprios (o ByteTrack guarda estado por stream)."""

    def __init__(self, cfg, geral, mostrar):
        super().__init__(daemon=True)
        self.id, self.nome, self.linha = cfg["id"], cfg["nome"], cfg.get("linha")
        self.geral, self.mostrar = geral, mostrar
        self.leitor = Leitor(cfg["url"])
        self.modelo = YOLO(geral["modelo"])
        self.trava = threading.Lock()
        self.trilhas = {}                                  # id -> {frames, contada, lado, visto}
        self.calor = np.zeros(geral["grade_calor"], np.float32)  # pessoa-segundos por célula
        self.zerar_intervalo()
        self.pessoas_agora, self.fps, self.resolucao = 0, 0.0, None
        self.previa = None

    def zerar_intervalo(self):
        self.soma_pessoas = self.frames = self.novos = self.entradas = self.saidas = 0

    def zerar_tudo(self):
        """Reinício pedido pelo dashboard: contagem recomeça do zero.

        Quem já está em cena vira trilha nova e volta a ser contado como pessoa distinta.
        """
        with self.trava:
            self.trilhas.clear()
            self.calor[:] = 0
            self.zerar_intervalo()

    def run(self):
        self.leitor.start()
        g = self.geral
        intervalo_min = 1.0 / g["fps_maximo"]
        ultimo_numero, ultimo_t = 0, time.time()
        while True:
            frame, numero, _ = self.leitor.mais_recente()
            agora = time.time()
            if frame is None or numero == ultimo_numero or agora - ultimo_t < intervalo_min:
                time.sleep(0.005)
                continue
            dt, ultimo_numero, ultimo_t = min(agora - ultimo_t, 1.0), numero, agora

            r = self.modelo.track(
                frame, persist=True, tracker="bytetrack.yaml", classes=[CLASSE_PESSOA],
                conf=g["confianca"], imgsz=g["tamanho_imagem"], verbose=False,
            )[0]
            alt, larg = frame.shape[:2]
            caixas = r.boxes.xyxy.cpu().numpy() if r.boxes.id is not None else np.empty((0, 4))
            ids = r.boxes.id.int().cpu().tolist() if r.boxes.id is not None else []

            with self.trava:
                for (x1, y1, x2, y2), tid in zip(caixas, ids):
                    # ponto dos pés: é onde a pessoa está no chão
                    px = min(max((x1 + x2) / 2 / larg, 0), 0.9999)
                    py = min(max(y2 / alt, 0), 0.9999)
                    self.calor[int(py * self.calor.shape[0]), int(px * self.calor.shape[1])] += dt

                    t = self.trilhas.setdefault(tid, {"frames": 0, "contada": False, "lado": 0.0})
                    t["frames"] += 1
                    t["visto"] = agora
                    if not t["contada"] and t["frames"] >= g["min_frames_para_contar"]:
                        t["contada"] = True
                        self.novos += 1
                    if self.linha:
                        lado = lado_da_linha(self.linha, px, py)
                        if t["lado"] < 0 < lado:
                            self.entradas += 1
                        elif lado < 0 < t["lado"]:
                            self.saidas += 1
                        if lado != 0:
                            t["lado"] = lado
                self.trilhas = {k: t for k, t in self.trilhas.items() if agora - t["visto"] <= ESQUECER_TRILHA_S}
                self.pessoas_agora = len(ids)
                self.soma_pessoas += len(ids)
                self.frames += 1
                self.fps = 0.9 * self.fps + 0.1 / dt if self.fps else 1 / dt
                self.resolucao = f"{larg}x{alt}"

            if self.mostrar:
                img = r.plot(labels=False)
                if self.linha:
                    (ax, ay), (bx, by) = self.linha
                    cv2.line(img, (int(ax * larg), int(ay * alt)), (int(bx * larg), int(by * alt)), (0, 255, 255), 2)
                self.previa = cv2.resize(img, (640, int(640 * alt / larg)))
            del frame, r  # o frame nunca é gravado

    def fechar_intervalo(self, agora):
        """Devolve (amostra ou None, estado) e zera os acumuladores do intervalo."""
        online = agora - self.leitor.mais_recente()[2] <= OFFLINE_APOS_S
        with self.trava:
            amostra = None
            if online and self.frames:
                amostra = (self.id, round(self.soma_pessoas / self.frames, 2), self.novos, self.entradas, self.saidas)
            estado = {
                "nome": self.nome,
                "online": online,
                "pessoas_agora": self.pessoas_agora if online else 0,
                "fps": round(self.fps, 1) if online else 0.0,
                "resolucao": self.resolucao,
                "atualizado_em": agora,
                "calor": np.round(self.calor, 1).tolist(),
            }
            self.zerar_intervalo()
        return amostra, estado


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--mostrar", action="store_true", help="abre janelas com as detecções, para conferência")
    ap.add_argument("--intervalo", type=float, default=1.0, help="segundos entre gravações no banco")
    a = ap.parse_args()

    geral = carregar_config()
    n = len(geral["cameras"])
    torch.set_num_threads(max(1, (os.cpu_count() or 4) // n))  # divide a CPU entre as câmeras

    con = conectar()
    cameras = [Camera(c, geral, a.mostrar) for c in geral["cameras"]]
    for c in cameras:
        c.start()
        print(f"[{c.id}] {c.nome}: aguardando stream em {c.leitor.url}", flush=True)

    proxima = time.time() + a.intervalo
    ultimo_zerar = ler_zerar(con)
    try:
        while True:
            if a.mostrar:
                for c in cameras:
                    if c.previa is not None:
                        cv2.imshow(f"{c.id} - {c.nome}", c.previa)
                cv2.waitKey(30)
            else:
                time.sleep(0.05)
            agora = time.time()
            if agora < proxima:
                continue
            proxima = agora + a.intervalo

            pedido = ler_zerar(con)
            if pedido > ultimo_zerar:
                ultimo_zerar = pedido
                for c in cameras:
                    c.zerar_tudo()
                apagar_amostras_ate(con, pedido)  # pega o que foi gravado antes de eu notar
                print("Contagem reiniciada pelo dashboard.", flush=True)
                continue  # este intervalo ficou pela metade; começa limpo no próximo

            amostras, estados = [], {}
            for c in cameras:
                amostra, estados[c.id] = c.fechar_intervalo(agora)
                if amostra:
                    amostras.append(amostra)
            gravar(con, agora, amostras, estados)
            print("  ".join(
                f"{cid}: {e['pessoas_agora']} pessoa(s), {e['fps']} fps" if e["online"] else f"{cid}: fora do ar"
                for cid, e in estados.items()), flush=True)
    except KeyboardInterrupt:
        print("Encerrado.")


if __name__ == "__main__":
    main()
