"""Envia para a API as janelas de 15 minutos que já fecharam.

Roda ao lado do worker, lendo o mesmo banco local. Fica separado de propósito: se a API cair,
a contagem continua, e as janelas pendentes vão embora no próximo ciclo.

O que sai daqui é só o contrato: zona, janela, duração, contagem e fonte. A ocupação média
da janela é arredondada antes de sair, como manda o RNF01.

Uso: python enviar_para_api.py              (a cada 60 s)
     python enviar_para_api.py --uma-vez    (envia o que está pendente e sai)
"""
import argparse
import json
import sqlite3
import time
import urllib.error
import urllib.request
from collections import defaultdict
from datetime import datetime, timedelta

from armazenamento import carregar_config, conectar

JANELA_MIN = 15
FONTE = "sensor"
ROTA_INGESTAO = "/api/ingestao/v1/contagens"
ROTA_CONFIG = "/api/v1/config"

CONTROLE = """
CREATE TABLE IF NOT EXISTS enviados (
    zona_id       TEXT NOT NULL,
    inicio_janela TEXT NOT NULL,
    contagem      INTEGER NOT NULL,
    enviado_em    TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    PRIMARY KEY (zona_id, inicio_janela)
);
"""


def janelas_fechadas(con, zona_por_camera, agora):
    """Ocupação média por zona em cada janela de 15 min já encerrada.

    Duas câmeras na mesma zona somam. A janela em curso não é enviada, porque ainda
    pode receber amostras.
    """
    limite = agora - timedelta(minutes=JANELA_MIN)
    soma = defaultdict(float)
    quantas = defaultdict(int)
    for ts, camera, pessoas in con.execute(
            "SELECT ts, camera, pessoas FROM amostras ORDER BY ts"):
        if camera not in zona_por_camera:
            continue
        quando = datetime.fromtimestamp(ts)
        inicio = quando.replace(minute=quando.minute // JANELA_MIN * JANELA_MIN,
                                second=0, microsecond=0)
        if inicio > limite:
            continue  # janela ainda aberta
        chave = (zona_por_camera[camera], inicio.strftime("%Y-%m-%dT%H:%M"), camera)
        soma[chave] += pessoas
        quantas[chave] += 1

    por_zona_janela = defaultdict(float)
    for chave, total in soma.items():
        zona, janela, _ = chave
        por_zona_janela[(zona, janela)] += total / quantas[chave]
    return {k: round(v) for k, v in sorted(por_zona_janela.items())}


def pendentes(con, fechadas):
    ja = {(z, j): c for z, j, c in con.execute("SELECT zona_id, inicio_janela, contagem FROM enviados")}
    return {k: v for k, v in fechadas.items() if ja.get(k) != v}


def postar(api, token, registros):
    corpo = json.dumps({"registros": registros}).encode("utf-8")
    pedido = urllib.request.Request(f"{api.rstrip('/')}{ROTA_INGESTAO}", data=corpo, method="POST",
                                    headers={"Content-Type": "application/json",
                                             "Accept": "application/json"})
    if token:
        pedido.add_header("Authorization", f"Bearer {token}")
    with urllib.request.urlopen(pedido, timeout=20) as resposta:
        return json.load(resposta)


def ciclo(con, cfg, api, token, horario):
    zona_por_camera = {c["id"]: c["zona_id"] for c in cfg["cameras"] if c.get("zona_id")}
    fechadas = janelas_fechadas(con, zona_por_camera, datetime.now())
    novas = pendentes(con, fechadas)
    if not novas:
        return 0

    registros, fora_do_horario = [], 0
    for (zona, janela), contagem in novas.items():
        hora = datetime.fromisoformat(janela).time()
        if not (horario[0] <= hora < horario[1]):
            fora_do_horario += 1
            continue
        registros.append({"zona_id": zona, "inicio_janela": janela,
                          "duracao_min": JANELA_MIN, "contagem": contagem, "fonte": FONTE})
    if fora_do_horario:
        print(f"  {fora_do_horario} janela(s) fora do horário de funcionamento: não enviadas", flush=True)
    if not registros:
        return 0

    try:
        resposta = postar(api, token, registros)
    except urllib.error.HTTPError as erro:
        print(f"  API recusou ({erro.code}): {erro.read().decode('utf-8', 'replace')[:300]}", flush=True)
        return 0
    except urllib.error.URLError as erro:
        print(f"  API fora do ar ({erro.reason}); tento de novo no próximo ciclo", flush=True)
        return 0

    with con:
        con.executemany(
            """INSERT INTO enviados (zona_id, inicio_janela, contagem) VALUES (?, ?, ?)
               ON CONFLICT(zona_id, inicio_janela) DO UPDATE SET
                   contagem = excluded.contagem, enviado_em = datetime('now', 'localtime')""",
            [(r["zona_id"], r["inicio_janela"], r["contagem"]) for r in registros])
    print(f"  enviados {len(registros)} registro(s): {resposta}", flush=True)
    return len(registros)


def main():
    cfg = carregar_config()
    destino = cfg.get("api", {})
    ap = argparse.ArgumentParser()
    ap.add_argument("--api", default=destino.get("url", "http://localhost:8000"))
    ap.add_argument("--token", default=destino.get("token", ""))
    ap.add_argument("--intervalo", type=float, default=60)
    ap.add_argument("--uma-vez", action="store_true")
    a = ap.parse_args()

    if not a.api:
        raise SystemExit("Sem endereço de API. Preencha api.url em cameras.json ou use --api.")

    con = conectar()
    con.executescript(CONTROLE)
    horario = _horario_de_funcionamento(a.api)
    print(f"Enviando janelas de {JANELA_MIN} min para {a.api} "
          f"(funcionamento {horario[0]:%H:%M}–{horario[1]:%H:%M})", flush=True)

    while True:
        print(datetime.now().strftime("%H:%M:%S"), flush=True)
        ciclo(con, cfg, a.api, a.token, horario)
        if a.uma_vez:
            return
        time.sleep(a.intervalo)


def _horario_de_funcionamento(api):
    """Pergunta à API; se ela não responder, usa 10h–22h, o padrão do projeto."""
    from datetime import time as hora_do_dia
    try:
        with urllib.request.urlopen(f"{api.rstrip('/')}{ROTA_CONFIG}", timeout=5) as r:
            cfg = json.load(r)
        return (hora_do_dia.fromisoformat(cfg["horario_abre"]),
                hora_do_dia.fromisoformat(cfg["horario_fecha"]))
    except Exception:
        print("  não consegui ler /v1/config; usando 10:00–22:00", flush=True)
        return hora_do_dia(10, 0), hora_do_dia(22, 0)


if __name__ == "__main__":
    main()
