"""Banco compartilhado entre o worker (escreve) e o dashboard (lê).

Só guarda números: contagem por câmera e por segundo, e a grade abstrata do mapa de
calor. Não existe coluna para imagem, vídeo ou identificador de pessoa.
"""
import json
import sqlite3
from pathlib import Path

AQUI = Path(__file__).resolve().parent
CONFIG = AQUI / "cameras.json"
BANCO = AQUI / "dados" / "contagens.db"

ESQUEMA = """
CREATE TABLE IF NOT EXISTS amostras (
    ts       REAL NOT NULL,     -- instante (epoch, segundos)
    camera   TEXT NOT NULL,
    pessoas  REAL NOT NULL,     -- média de pessoas presentes no intervalo
    novos    INTEGER NOT NULL,  -- pessoas distintas confirmadas no intervalo
    entradas INTEGER NOT NULL,  -- cruzamentos da linha no sentido de entrada
    saidas   INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS amostras_ts ON amostras (ts);
CREATE TABLE IF NOT EXISTS estado (
    camera TEXT PRIMARY KEY,
    dados  TEXT NOT NULL        -- JSON: online, fps, pessoas_agora, calor...
);
CREATE TABLE IF NOT EXISTS comandos (
    chave TEXT PRIMARY KEY,
    valor REAL NOT NULL         -- 'zerar_em': instante do último pedido de reinício
);
"""


def carregar_config():
    return json.loads(CONFIG.read_text(encoding="utf-8"))


def conectar():
    BANCO.parent.mkdir(exist_ok=True)
    con = sqlite3.connect(BANCO, timeout=5, check_same_thread=False)
    con.execute("PRAGMA journal_mode=WAL")
    con.execute("PRAGMA synchronous=NORMAL")
    con.executescript(ESQUEMA)
    return con


def gravar(con, ts, amostras, estados):
    """amostras: [(camera, pessoas, novos, entradas, saidas)]; estados: {camera: dict}."""
    with con:
        con.executemany(
            "INSERT INTO amostras VALUES (?, ?, ?, ?, ?, ?)",
            [(ts, *a) for a in amostras],
        )
        con.executemany(
            "INSERT INTO estado VALUES (?, ?) ON CONFLICT(camera) DO UPDATE SET dados = excluded.dados",
            [(cam, json.dumps(d)) for cam, d in estados.items()],
        )


def ler_estado(con):
    return {cam: json.loads(d) for cam, d in con.execute("SELECT camera, dados FROM estado")}


def pedir_zerar(con, ts):
    """Registra o pedido de reinício e já apaga o que ficou para trás.

    O worker enxerga o pedido no ciclo seguinte e zera o estado em memória. Apagar aqui
    também faz o botão funcionar mesmo com o worker parado.
    """
    with con:
        con.execute(
            "INSERT INTO comandos VALUES ('zerar_em', ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor",
            (ts,),
        )
        con.execute("DELETE FROM amostras WHERE ts <= ?", (ts,))


def ler_zerar(con):
    """Instante do último pedido de reinício, ou 0 se nunca houve."""
    linha = con.execute("SELECT valor FROM comandos WHERE chave = 'zerar_em'").fetchone()
    return linha[0] if linha else 0.0


def apagar_amostras_ate(con, ts):
    with con:
        con.execute("DELETE FROM amostras WHERE ts <= ?", (ts,))


def ler_amostras(con, desde_ts):
    return con.execute(
        "SELECT ts, camera, pessoas, novos, entradas, saidas FROM amostras WHERE ts >= ? ORDER BY ts",
        (desde_ts,),
    ).fetchall()
