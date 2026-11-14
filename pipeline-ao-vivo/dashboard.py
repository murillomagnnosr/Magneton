"""Dashboard em tempo real: lê os números que o worker grava e mostra por câmera.

Não exibe nem recebe imagem de câmera. O mapa de calor é um desenho abstrato das contagens.

Uso: streamlit run dashboard.py
"""
import json
import time
from datetime import datetime

import cv2
import numpy as np
import pandas as pd
import streamlit as st

from armazenamento import carregar_config, conectar, ler_amostras, ler_estado, ler_zerar, pedir_zerar

PERIODOS = {"Últimos 5 min": 5 * 60, "Últimos 15 min": 15 * 60, "Última hora": 3600, "Últimas 6 horas": 6 * 3600}
JANELA_MIN = 15  # contrato de dados do projeto (REQUISITOS.md, RNF03)
FUSO = datetime.now().astimezone().tzinfo

st.set_page_config(page_title="Fluxo de pessoas ao vivo", page_icon="📊", layout="wide")


@st.cache_resource
def banco():
    return conectar()


def tabela_amostras(desde_ts):
    df = pd.DataFrame(ler_amostras(banco(), desde_ts),
                      columns=["ts", "camera", "pessoas", "novos", "entradas", "saidas"])
    df["hora"] = pd.to_datetime(df["ts"], unit="s", utc=True).dt.tz_convert(FUSO).dt.tz_localize(None)
    return df


def imagem_calor(grade):
    """Grade de pessoa-segundos -> desenho colorido (sem nenhum pixel da câmera)."""
    g = np.asarray(grade, np.float32)
    img = cv2.resize(g, (640, 360), interpolation=cv2.INTER_CUBIC)
    img = np.clip(cv2.GaussianBlur(img, (0, 0), 18), 0, None)
    if img.max() > 0:
        img /= img.max()
    cor = cv2.applyColorMap((img * 255).astype(np.uint8), cv2.COLORMAP_INFERNO)
    return cv2.cvtColor(cor, cv2.COLOR_BGR2RGB)


def exportar_contrato(cfg):
    """Janelas de 15 min já fechadas, no contrato {zona_id, inicio_janela, duracao_min, contagem, fonte}."""
    df = tabela_amostras(time.time() - 24 * 3600)
    zona = {c["id"]: c["zona_id"] for c in cfg["cameras"] if c.get("zona_id")}
    df = df[df["camera"].isin(zona)]
    if df.empty:
        return "[]", 0
    df["zona_id"] = df["camera"].map(zona)
    df["janela"] = df["hora"].dt.floor(f"{JANELA_MIN}min")
    fechadas = df[df["janela"] + pd.Timedelta(minutes=JANELA_MIN) <= pd.Timestamp(datetime.now())]
    # ocupação média da zona na janela; duas câmeras na mesma zona somam
    por_camera = fechadas.groupby(["zona_id", "janela", "camera"])["pessoas"].mean()
    por_zona = por_camera.groupby(["zona_id", "janela"]).sum().round().astype(int).reset_index()
    registros = [
        {"zona_id": r.zona_id, "inicio_janela": r.janela.strftime("%Y-%m-%dT%H:%M"),
         "duracao_min": JANELA_MIN, "contagem": int(r.pessoas), "fonte": "sensor"}
        for r in por_zona.itertuples()
    ]
    return json.dumps(registros, ensure_ascii=False, indent=2), len(registros)


cfg = carregar_config()
nomes = {c["id"]: c["nome"] for c in cfg["cameras"]}

st.title("Fluxo de pessoas ao vivo")
st.caption("Celulares (Larix) → MediaMTX → YOLO + ByteTrack → este painel. "
           "Só contagens chegam aqui: nenhuma imagem é gravada ou exibida.")

with st.sidebar:
    periodo = st.radio("Período dos gráficos", list(PERIODOS), index=1)
    st.divider()

    st.subheader("Contagem")
    zerado_em = ler_zerar(banco())
    if zerado_em:
        st.caption(f"Contando desde {datetime.fromtimestamp(zerado_em).strftime('%H:%M:%S')}.")
    else:
        st.caption("Contando desde que o worker iniciou.")
    with st.popover("Reiniciar contagem", width="stretch"):
        st.write("Apaga o histórico, os mapas de calor e as pessoas já contadas. "
                 "Quem estiver em cena volta a ser contado. Não dá para desfazer.")
        if st.button("Confirmar reinício", type="primary", width="stretch"):
            pedir_zerar(banco(), time.time())
            st.toast("Contagem reiniciada.")
            st.rerun()
    st.divider()
    st.subheader("Exportar para o mapa de calor")
    st.caption(f"Janelas de {JANELA_MIN} min já fechadas das últimas 24 h, no contrato de `contagens.json`.")
    conteudo, quantos = exportar_contrato(cfg)
    st.download_button(f"Baixar contagens.json ({quantos} registros)", conteudo,
                       file_name="contagens.json", mime="application/json", disabled=quantos == 0)


@st.fragment(run_every=2)
def painel():
    agora = time.time()
    estado = ler_estado(banco())
    zerado = ler_zerar(banco())
    df = tabela_amostras(max(agora - PERIODOS[periodo], zerado))

    # Entre o clique e a próxima gravação do worker, o estado na tela ainda é o antigo.
    for e in estado.values():
        if e["atualizado_em"] <= zerado:
            e["pessoas_agora"], e["calor"] = 0, np.zeros_like(np.asarray(e["calor"])).tolist()

    if not estado:
        st.info("Aguardando o worker. Inicie com `python worker.py`.")
        return
    worker_parado = agora - max(e["atualizado_em"] for e in estado.values()) > 10
    if worker_parado:
        st.warning("O worker não grava há mais de 10 segundos. Os números abaixo estão parados.")

    online = [cid for cid, e in estado.items() if e["online"] and not worker_parado]
    k1, k2, k3, k4 = st.columns(4)
    k1.metric("Pessoas agora", sum(estado[c]["pessoas_agora"] for c in online))
    k2.metric("Câmeras no ar", f"{len(online)} de {len(estado)}")
    k3.metric("Pessoas distintas no período", int(df["novos"].sum()))
    total_por_instante = df.groupby("ts")["pessoas"].sum()
    k4.metric("Pico simultâneo no período", int(round(total_por_instante.max())) if len(total_por_instante) else 0)

    st.subheader("Pessoas presentes por câmera")
    if df.empty:
        st.caption("Sem dados no período.")
    else:
        passo = "5s" if PERIODOS[periodo] <= 900 else "1min"
        serie = (df.assign(camera=df["camera"].map(lambda c: nomes.get(c, c)))
                   .pivot_table(index="hora", columns="camera", values="pessoas")
                   .resample(passo).mean())
        st.line_chart(serie, height=280, y_label="pessoas", x_label=None)

    st.subheader("Câmeras")
    colunas = st.columns(max(len(estado), 1))
    for coluna, (cid, e) in zip(colunas, estado.items()):
        with coluna:
            no_ar = cid in online
            st.markdown(f"**{e['nome']}** · `{cid}` · {'🟢 no ar' if no_ar else '🔴 fora do ar'}")
            dc = df[df["camera"] == cid]
            m1, m2, m3 = st.columns(3)
            m1.metric("Agora", e["pessoas_agora"] if no_ar else "–")
            m2.metric("Distintas", int(dc["novos"].sum()))
            m3.metric("Média", f"{dc['pessoas'].mean():.1f}".replace(".", ",") if len(dc) else "–")
            if dc["entradas"].sum() or dc["saidas"].sum():
                st.caption(f"Linha de contagem: {int(dc['entradas'].sum())} entradas, {int(dc['saidas'].sum())} saídas")
            st.image(imagem_calor(e["calor"]), width="stretch",
                     caption="Onde as pessoas ficam, desde o início da contagem. Desenho abstrato, sem imagem da câmera.")
            if no_ar:
                st.caption(f"{e['resolucao']} · {str(e['fps']).replace('.', ',')} quadros/s analisados")


painel()
