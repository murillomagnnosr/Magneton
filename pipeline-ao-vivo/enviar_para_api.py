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

FONTE = "sensor"
ROTA_INGESTAO = "/api/ingestao/v1/contagens"
# A contagem ao vivo é a média deste tanto de segundos, para a tela não tremer.
SUAVIZAR_S = 5
ROTA_AO_VIVO = "/api/ingestao/v1/ao-vivo"
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


def janelas_fechadas(con, zona_por_camera, agora, janela_min):
    """Ocupação média por zona em cada janela já encerrada.

    Duas câmeras na mesma zona somam. A janela em curso não é enviada, porque ainda
    pode receber amostras.
    """
    limite = agora - timedelta(minutes=janela_min)
    soma = defaultdict(float)
    quantas = defaultdict(int)
    for ts, camera, pessoas in con.execute(
            "SELECT ts, camera, pessoas FROM amostras ORDER BY ts"):
        if camera not in zona_por_camera:
            continue
        quando = datetime.fromtimestamp(ts)
        inicio = quando.replace(minute=quando.minute // janela_min * janela_min,
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


def ciclo(con, cfg, api, token, regras):
    janela_min, horario = regras["duracao_min"], regras["horario"]
    zona_por_camera = {c["id"]: c["zona_id"] for c in cfg["cameras"] if c.get("zona_id")}
    fechadas = janelas_fechadas(con, zona_por_camera, datetime.now(), janela_min)
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
                          "duracao_min": janela_min, "contagem": contagem, "fonte": FONTE})
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


def estado_ao_vivo(con, zona_por_camera, validade_s, suavizar_s=SUAVIZAR_S):
    """Quantas pessoas há agora em cada zona.

    Usa a média das amostras dos últimos `suavizar_s` segundos, e não a última amostra
    sozinha. O detector perde e reencontra pessoas entre quadros, então a última amostra
    pula de 5 para 2 e volta; a média de poucos segundos continua sendo "agora" e não
    treme na tela.

    Duas câmeras na mesma zona somam. Câmera sem amostra recente fica de fora, e a API
    marca a zona como sem medição.
    """
    agora = time.time()
    por_zona = defaultdict(float)
    visto = {}

    for camera, zona in zona_por_camera.items():
        linha = con.execute(
            """SELECT MAX(ts), AVG(pessoas) FROM amostras
               WHERE camera = ? AND ts >= (SELECT MAX(ts) FROM amostras WHERE camera = ?) - ?""",
            (camera, camera, suavizar_s)).fetchone()
        if not linha or linha[0] is None or agora - linha[0] > validade_s:
            continue
        por_zona[zona] += linha[1]
        visto[zona] = max(visto.get(zona, 0), linha[0])

    return [{"zona_id": zona,
             "pessoas": round(pessoas),
             "medido_em": datetime.fromtimestamp(visto[zona]).strftime("%Y-%m-%dT%H:%M:%S")}
            for zona, pessoas in sorted(por_zona.items())]


def enviar_ao_vivo(api, token, zonas):
    corpo = json.dumps({"zonas": zonas}).encode("utf-8")
    pedido = urllib.request.Request(f"{api.rstrip('/')}{ROTA_AO_VIVO}", data=corpo, method="POST",
                                    headers={"Content-Type": "application/json",
                                             "Accept": "application/json"})
    if token:
        pedido.add_header("Authorization", f"Bearer {token}")
    with urllib.request.urlopen(pedido, timeout=10) as resposta:
        return json.load(resposta)


def main():
    cfg = carregar_config()
    destino = cfg.get("api", {})
    ap = argparse.ArgumentParser()
    ap.add_argument("--api", default=destino.get("url", "http://localhost:8000"))
    ap.add_argument("--token", default=destino.get("token", ""))
    ap.add_argument("--ao-vivo", type=float, default=3, help="segundos entre envios do estado atual")
    ap.add_argument("--janelas", type=float, default=60, help="segundos entre envios das janelas fechadas")
    ap.add_argument("--uma-vez", action="store_true")
    a = ap.parse_args()

    if not a.api:
        raise SystemExit("Sem endereço de API. Preencha api.url em cameras.json ou use --api.")

    con = conectar()
    con.executescript(CONTROLE)
    regras = _regras_da_api(a.api)
    inicio, fim = regras["horario"]
    zona_por_camera = {c["id"]: c["zona_id"] for c in cfg["cameras"] if c.get("zona_id")}

    print(f"Ao vivo a cada {a.ao_vivo:g}s; janelas de {regras['duracao_min']} min a cada "
          f"{a.janelas:g}s. API em {a.api} (funcionamento {inicio:%H:%M}–{fim:%H:%M})", flush=True)

    proxima_janela = 0.0
    ultimo_aviso = None

    while True:
        agora = time.time()

        # estado atual: barato e frequente, é o que faz o painel reagir na hora
        zonas = estado_ao_vivo(con, zona_por_camera, regras["validade_ao_vivo_s"])
        try:
            if zonas:
                enviar_ao_vivo(a.api, a.token, zonas)
            aviso = f"ao vivo: {sum(z['pessoas'] for z in zonas)} pessoa(s) em {len(zonas)} zona(s)"
        except urllib.error.HTTPError as erro:
            aviso = f"ao vivo recusado ({erro.code}): {erro.read().decode('utf-8', 'replace')[:160]}"
        except urllib.error.URLError as erro:
            aviso = f"API fora do ar ({erro.reason})"

        if aviso != ultimo_aviso:
            print(f"{datetime.now():%H:%M:%S}  {aviso}", flush=True)
            ultimo_aviso = aviso

        # janelas fechadas: o histórico agregado, bem mais espaçado
        if agora >= proxima_janela:
            proxima_janela = agora + a.janelas
            ciclo(con, cfg, a.api, a.token, regras)

        if a.uma_vez:
            return

        time.sleep(a.ao_vivo)


def _regras_da_api(api):
    """Duração da janela e horário de funcionamento, perguntados à própria API.

    Assim existe um lugar só para esses parâmetros: mudar no .env do servidor muda aqui
    também, e o enviador nunca manda uma janela que a API recusaria.
    """
    from datetime import time as hora_do_dia
    try:
        with urllib.request.urlopen(f"{api.rstrip('/')}{ROTA_CONFIG}", timeout=5) as r:
            cfg = json.load(r)
        return {
            "duracao_min": int(cfg["duracao_min"]),
            "validade_ao_vivo_s": int(cfg.get("ao_vivo_valido_por_s", 15)),
            "horario": (hora_do_dia.fromisoformat(cfg["horario_abre"]),
                        hora_do_dia.fromisoformat(cfg["horario_fecha"])),
        }
    except Exception:
        print("  não consegui ler /v1/config; usando janela de 15 min, das 10:00 às 22:00", flush=True)
        return {"duracao_min": 15, "validade_ao_vivo_s": 15,
                "horario": (hora_do_dia(10, 0), hora_do_dia(22, 0))}


if __name__ == "__main__":
    main()
