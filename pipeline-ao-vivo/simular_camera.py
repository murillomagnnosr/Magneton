"""Publica um vídeo (ou uma foto em laço) no MediaMTX por RTMP, no lugar de um celular.

Serve para testar o pipeline inteiro sem Larix.

Uso:
    python simular_camera.py                       # foto de exemplo com 4 pessoas em live/cam1
    python simular_camera.py --arquivo video.mp4 --caminho live/cam2
"""
import argparse
import subprocess
from pathlib import Path

import imageio_ffmpeg

IMAGENS = {".jpg", ".jpeg", ".png", ".bmp"}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--arquivo", help="vídeo ou imagem; padrão: foto de exemplo do Ultralytics")
    ap.add_argument("--caminho", default="live/cam1")
    ap.add_argument("--servidor", default="rtmp://127.0.0.1:1935")
    a = ap.parse_args()

    if a.arquivo:
        arquivo = Path(a.arquivo)
    else:
        from ultralytics.utils import ASSETS
        arquivo = ASSETS / "bus.jpg"
    if not arquivo.exists():
        raise SystemExit(f"ERRO: arquivo não encontrado: {arquivo}")

    entrada = ["-loop", "1"] if arquivo.suffix.lower() in IMAGENS else ["-stream_loop", "-1"]
    comando = [
        imageio_ffmpeg.get_ffmpeg_exe(), "-hide_banner", "-loglevel", "warning",
        "-re", *entrada, "-i", str(arquivo),
        "-vf", "scale=1280:-2", "-r", "15", "-an",
        "-c:v", "libx264", "-preset", "veryfast", "-tune", "zerolatency",
        "-pix_fmt", "yuv420p", "-g", "30",
        "-f", "flv", f"{a.servidor}/{a.caminho}",
    ]
    print(f"Publicando {arquivo.name} em {a.servidor}/{a.caminho} (Ctrl+C para parar)", flush=True)
    try:
        subprocess.run(comando, check=False)
    except KeyboardInterrupt:
        pass


if __name__ == "__main__":
    main()
