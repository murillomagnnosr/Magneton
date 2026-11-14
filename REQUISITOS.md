# Documento de requisitos — Mapa de calor de fluxo de pessoas no shopping

**HackaRun, desafio 02 — Inteligência sobre fluxo e movimentação de pessoas**
Versão 4 (resposta à pergunta de descoberta 7: o shopping já conta entrada e saída) · 14/11/2026

Marcações usadas: **[Fato]** vem do enunciado ou de decisão do grupo. **[Suposição]** foi adotada para seguir sem resposta. **[Estimativa]** é número sem fonte pesquisada. **[Validar com jurídico]** não é conclusão jurídica.

Cada requisito funcional responde a pelo menos uma das três perguntas do enunciado (**C** = onde concentra, **V** = onde está vazio, **T** = como muda no tempo) ou é marcado como requisito de suporte (**S** = privacidade, transparência ou infraestrutura). Os requisitos não funcionais são transversais.

---

## 1. Resumo

O shopping já sabe **quantas pessoas entram e saem**: os contadores de entrada existem e funcionam. O que ninguém sabe é **quantas ficam, onde ficam e por quanto tempo**. Um número de entrada diz o tamanho do público do dia; ele não diz se as pessoas foram até a ala leste ou se deram meia-volta depois da praça. É nessa lacuna que este projeto entra.

Por causa dela, a administração decide hoje posição de quiosques, valor de pontos, escala de limpeza e segurança e campanhas sem saber, com dados, onde as pessoas se concentram, quais espaços ficam vazios e como isso muda ao longo do dia e da semana. A solução é um mapa de calor sobre a planta do shopping, alimentado apenas por contagens agregadas por zona e por janela de tempo, com uma camada de leitura que transforma o mapa em decisão: ranking de zonas quentes e frias, zona fria persistente, comparação entre períodos e frases prontas que dizem o que mudou. Os usuários são a gestão comercial, de operações e de marketing. O motivo de fazer assim é que as abordagens que acompanham indivíduos (câmera com identificação, Wi-Fi, BLE) trazem risco ligado à LGPD, e um sistema cujo contrato de entrada não tem campo para dado pessoal reduz esse risco desde o desenho (a conclusão jurídica precisa de validação). Na demo, os dados vêm de um simulador e são sempre rotulados como simulados. Em produção, o mesmo contrato de dados recebe contagens de contadores de entrada, sensores de presença por zona ou visão computacional na borda que descarta o frame.

---

## 2. Suposições e perguntas em aberto

### 2.1 Fatos (decisões do grupo, não rediscutidas)

1. A solução é um mapa de calor sobre a planta do shopping.
2. Privacidade por design: a única entrada é contagem agregada por zona e janela de tempo. Não há imagem armazenada, reconhecimento facial, trajetória individual nem MAC de Wi-Fi ou BLE.
3. Na demo, os dados vêm de um simulador de agentes, sempre rotulado como simulado. Em produção: contadores de entrada, sensores de presença por zona ou visão computacional na borda que envia só a contagem.

**Delimitação do termo.** Neste documento, "sistema" é tudo a partir do contrato de ingestão (seção 7.1). O sensor fica fora do sistema e tem avaliação própria, inclusive jurídica.

### 2.2 Suposições adotadas

| # | Suposição | Se estiver errada |
|---|---|---|
| S1 | O hackathon dura cerca de 36 horas (faixa de 24 a 48). | O plano da seção 11 está em percentual do prazo. Com 24 horas, cortam-se todos os itens Should, menos RF08 na versão mínima. |
| S2 | O grupo tem 4 pessoas (faixa de 4 a 5). | A quinta pessoa divide o frontend (uma no mapa, outra nos painéis). |
| S3 | A banca avalia aderência ao problema, demo funcionando, inovação, viabilidade e apresentação. | Remapear a tabela da seção 10.2. |
| S4 | Não há planta nem dados reais disponíveis. A planta é fictícia: um piso com 16 zonas. | Com planta real, troca-se o arquivo de zonas. Dados reais só entram como contagem agregada. |
| S5 | A demo roda em um notebook do grupo, sem internet e sem serviço pago. | Se exigirem link público ou outra máquina: publicar a pasta estática (por exemplo, GitHub Pages) e testar o carregamento nela antes do congelamento. |
| S6 | Janela fixa de 15 minutos no MVP e funcionamento das 10h às 22h (48 janelas por dia). | Mudar a janela exige revisar as faixas de período, o validador e os textos; não é só trocar um parâmetro. |
| S7 | O pitch é ao vivo e dura 5 minutos. | Ajustar os tempos da seção 12. |

### 2.3 Perguntas de descoberta (ordenadas pelo impacto no escopo)

| # | Pergunta | Suposição adotada para seguir |
|---|---|---|
| 1 | Qual é a duração exata do hackathon e há entrega intermediária? | S1, sem entrega intermediária. |
| 2 | Quais são os critérios e os pesos da banca? | S3, com pesos iguais. |
| 3 | Quantas pessoas programam e quantas têm prática com JavaScript? | 4 pessoas, ao menos uma à vontade com JavaScript. |
| 4 | A organização fornece planta ou dados de contagem? | S4: planta fictícia e dados simulados. |
| 5 | A demo é ao vivo ou em vídeo, e quanto tempo tem o pitch? | S7, com vídeo de reserva gravado. |
| 6 | Quem da administração é o principal interessado: operações, comercial ou marketing? | Comercial e operações são as personas da demo; marketing aparece na comparação. |
| 7 | O shopping já possui contadores ou sensores, e a banca espera tempo real ou histórico? | **Respondida (14/11/2026).** O shopping conta entrada e saída, e só isso: não sabe quantas pessoas permanecem nem onde. A demo entrega as duas leituras, histórico por janela e ao vivo. Ver 2.4. |

### 2.4 O que o cliente já tem, e o que falta

Descoberto em 14/11/2026, com o cliente.

| | O shopping já tem | Este projeto acrescenta |
|---|---|---|
| **Quantas** | Entradas e saídas no total do dia | Quantas estão em cada zona, em cada janela de 15 minutos |
| **Onde** | Nada por região | A zona, sobre a planta real |
| **Quando** | O total do dia | A curva do dia e o padrão por dia da semana |
| **Permanência** | Nada | Ocupação média por zona, que é permanência medida em pessoas, não em minutos |

A diferença entre contar porta e contar zona é a diferença entre saber o tamanho do público e saber o
que ele faz lá dentro. Duas quintas com o mesmo número na catraca podem ter mix de ocupação
completamente diferente — e é o mix que muda onde colocar o quiosque e quando escalar a limpeza.

**Oportunidade de integração (roadmap).** Como o cliente já tem o número da porta, as duas fontes se
somam sem precisar de sensor novo: com entrada e ocupação por zona na mesma janela, sai a taxa de
alcance de cada região — de cada 100 pessoas que entraram, quantas chegaram à Galeria Leste. Isso dá
preço de ponto comercial com lastro. Não está no MVP: depende de acesso ao dado da catraca e de
alinhar as janelas de tempo das duas fontes.

**O que isso não é.** Permanência aqui é ocupação média, não tempo de estadia por pessoa. Medir quanto
tempo *uma pessoa* fica exigiria segui-la, que é exatamente o que o RNF01 e o RNF02 proíbem. A
ocupação média responde à mesma pergunta de negócio sem rastrear ninguém: uma zona com 40 pessoas
em média ao longo de duas horas segurou gente, independentemente de serem as mesmas 40.

---

## 3. Personas e tarefas a cumprir

| Persona | Decisão que toma | Pergunta | O que precisa ver |
|---|---|---|---|
| **Carla, gerente comercial** | Posição de quiosques, valor relativo dos pontos, revisão do mix | C, V | Ranking de zonas por período e zonas frias persistentes |
| **Rogério, coordenador de operações e segurança** | Escala de limpeza, manutenção e vigilância | C, T | Mapa por horário, horário de pico por zona |
| **Júlia, analista de marketing** | Onde e quando ativar campanhas; antes e depois de uma ação | V, T | Zonas frias e comparação entre dois períodos |

Parte interessada que não usa a tela, mas aprova o projeto: o encarregado de dados do shopping, que precisa conseguir verificar os requisitos de privacidade da seção 5.

---

## 4. Requisitos funcionais

**Período.** O mapa mostra sempre uma janela de 15 minutos. Ranking, frases, comparação e exportação usam o **período ativo**, escolhido em uma lista fechada: janela atual, manhã (10h–12h), almoço (12h–14h), tarde (14h–18h), noite (18h–22h) ou dia inteiro. Não existe faixa livre de horário (ver RNF02).

| ID | Requisito | Prior. | Pergunta | Critério de aceite |
|---|---|---|---|---|
| RF01 | Exibir a planta de um piso com cada zona colorida pela classe de densidade da janela selecionada, com legenda. | Must | C, V | Ao abrir a página, todas as zonas de `zonas.json` aparecem coloridas, com legenda de 5 classes em pessoas por 100 m². Passar o cursor mostra nome, tipo e área. Em 3 zonas de amostra, a cor confere com a classe calculada à mão. |
| RF02 | Selecionar dia e janela por controle deslizante e escolher o período ativo na lista fechada. | Must | T | Ao mover o controle ou trocar o período, mapa e ranking atualizam dentro do limite de RNF09. O horário exibido corresponde à janela mostrada, e o período ativo aparece escrito acima do ranking. |
| RF03 | Mostrar o ranking das zonas mais quentes e mais frias do período ativo, com a classificação quente, neutra ou fria. | Must | C, V | A lista mostra as 3 zonas de maior densidade média e as 3 de menor. O valor aparece só quando publicável (passo 5); zonas com média abaixo de k aparecem como "abaixo do limiar", sem valor, no fim da lista. Com período "janela atual", a primeira posição coincide com a zona mais intensa do mapa. Os valores exibidos batem com o cálculo manual. |
| RF04 | Exibir zonas abaixo do limiar de contagem como classe própria, "abaixo do limiar (menos de k)", sem número. | Must | V | Nenhuma tela mostra contagem entre 0 e k−1. A zona aparece com hachura própria, diferente de "sem dado" (cinza liso), e com texto explicativo. |
| RF05 | Rotular os dados como simulados e a planta como fictícia em todas as telas. | Must | S | Com `fonte = "simulado"`, o selo "DADOS SIMULADOS · PLANTA FICTÍCIA" é marca-d'água dentro do SVG do mapa e se repete no cabeçalho de cada painel (ranking, frases, curva, comparação). Slides e vídeo de reserva trazem a legenda "dados simulados, planta fictícia" em todo quadro com tela do sistema. |
| RF06 | Aceitar como entrada somente registros no contrato `{zona_id, inicio_janela, duracao_min, contagem, fonte}`, com os tipos e domínios de RNF01. | Must | S | O validador rejeita, com mensagem de erro, um lote com campo extra (por exemplo `device_id`), com zona inexistente ou que viole RNF01, RNF03 ou RNF07. Um lote válido aparece no mapa. |
| RF07 | Gerar os dados da demo por simulador, para 7 dias, com picos por período e com uma zona quente e uma ala fria plantadas. | Must | C, V, T | Com semente fixa, cumpre RNF10. A curva total do dia tem pico de almoço e pico noturno. O algoritmo classifica corretamente as duas zonas plantadas (teste em `node --test`). |
| RF08 | Gerar frases de leitura automática por regras fixas, com os números que as justificam. | Should | C, V, T | Mínimo: F1, F2b e F3b (passo 8), uma por pergunta do enunciado. F2, F3, F4 e F5 entram conforme RF09, RF11 e RF10 ficam prontos. Os números conferem com o ranking. Com fonte simulada, nenhuma frase aparece sem o prefixo "[Simulação]". |
| RF09 | Identificar zona fria persistente (fria em pelo menos 70% dos dias do conjunto; 5 de 7). | Should | V | A ala fria plantada é listada como fria persistente, com o número de dias. |
| RF10 | Comparar dois períodos A e B, cada um um par (dia, faixa da lista fechada), e mostrar a variação por zona em mapa de diferença. | Should | T | Ao escolher A e B, o mapa passa para a escala divergente e lista as zonas com variação relevante e as que saíram ou caíram do limiar (passo 6). Zonas quase vazias não são destacadas. |
| RF11 | Ao clicar em uma zona, mostrar a curva de ocupação do dia e o horário de pico. | Should | T | O painel abre em até 1 segundo com a curva das janelas não suprimidas. Nenhuma janela com c < k tem valor plotado; ela aparece como faixa hachurada "abaixo do limiar". O pico coincide com o máximo da série suavizada. |
| RF12 | Reproduzir o dia de forma acelerada. | Should | T | O botão percorre as 48 janelas em 20 a 30 segundos, sem travar, com opção de pausar. Durante a reprodução, a tela mostra "Reprodução de dia simulado — não é tempo real". |
| RF13 | Mostrar a tela "Que dados usamos e como foram gerados". | Should | S | A tela lista os cinco campos do contrato, o que o sistema não coleta e os parâmetros do simulador (semente, perfis de chegada, atratividades). |
| RF14 | Sinalizar zonas acima de um limite de densidade configurável por duas janelas seguidas. | Could | C, T | No cenário com lotação injetada, a zona ganha contorno de alerta na janela correta. Sem lotação injetada, nenhum alerta aparece. |
| RF15 | Alternar entre a visão por zona e uma visão suavizada. | Could | C | O botão alterna as visões. A suavizada exibe o aviso "suavização visual; o dado é por zona". |
| RF16 | Expor os dados por uma API HTTP. | Could | S | `GET /contagens?dia=...` devolve apenas a saída publicada (classe, ou valor com c ≥ k), nunca o arquivo bruto. `POST /contagens` com campo extra devolve erro 422. |
| RF17 | Exportar o ranking do período em CSV. | Could | C, V, T | O arquivo traz período e `fonte`, é gerado só a partir de `publicarPeriodo()` e não contém valor abaixo do limiar. Com fonte simulada, a primeira linha é "DADOS SIMULADOS". |

**Fora do escopo (Won't):**
- Por violar a premissa de privacidade: identificação de visitantes, visitantes únicos, trajetórias, fluxo origem-destino, perfil demográfico, Wi-Fi ou BLE, consulta por faixa livre de horário ou por polígono arbitrário.
- Por não caber no prazo (roadmap): ingestão em tempo real de sensores, vários pisos, previsão de pico, anomalia por histórico, recomendações por modelo de linguagem, login e perfis, aplicativo móvel, cadastro de planta pela interface.

---

## 5. Requisitos não funcionais

Parâmetros padrão de privacidade: **k = 5** (contagem mínima exibida), **janela de 15 minutos**, **área mínima de zona de 50 m²** [Suposição]. Todos ficam em `config.json` e precisam de validação jurídica antes de produção.

| ID | Categoria | Requisito | Prior. | Critério de aceite |
|---|---|---|---|---|
| RNF01 | Privacidade | O contrato de entrada é fechado em campos, tipos e domínios. `zona_id`: existe em `zonas.json` e casa com `^Z\d{2}$`. `inicio_janela`: `AAAA-MM-DDTHH:MM`, hora local, sem segundos e sem sufixo. `duracao_min`: inteiro. `contagem`: inteiro, de 0 a 4 × `area_m2` [Suposição de teto], arredondado pelo simulador ou adaptador antes de gravar. `fonte`: enumeração `simulado` ou `sensor`. Não existe campo nem rota para imagem, vídeo ou identificador de pessoa, aparelho ou sessão. | Must | Testes pytest enviam lotes com `device_id`, com `imagem`, com `fonte = "cam-AA:BB:CC"`, com `contagem = 0.07` e com `contagem = -1`, e esperam rejeição em todos. |
| RNF02 | Privacidade | Nenhuma tela, exportação ou resposta de API mostra contagem ou média de contagem entre 0 e k−1; esses casos saem como "abaixo do limiar". Só é possível consultar a janela atual ou um período da lista fechada (cada um com 8 janelas ou mais). Um período só tem número publicado quando todas as suas janelas têm c ≥ k; nos demais, sai só a classe. Assim, a diferença entre saídas não revela janela suprimida. | Must | Toda saída vem das quatro funções de publicação (seção 7.1). Um teste em `node --test` percorre as quatro, e o CSV gerado, para todo o conjunto da demo e verifica: (a) com c[z,t] < k, `publicarJanela()` devolve só `classe` e `fonte`, sem campo numérico; nos demais, `densidade × area_m2 ≥ k`; (b) nenhum período com janela suprimida tem número; (c) nenhum ponto de curva corresponde a janela com c < k; (d) uma tentativa de reconstruir cada janela suprimida por diferença (n · média do período − soma das janelas publicadas) não encontra média publicada para usar; (e) `ui.js` e `index.html` não leem `contagem`. Se RF16 existir, um teste pytest faz a mesma verificação na resposta da API. |
| RNF03 | Privacidade | A janela é única no conjunto (igual a `duracao_min` de `config.json`, 15 minutos), alinhada (minutos 00, 15, 30, 45) e dentro do horário de funcionamento configurado (10h às 22h). O par (`zona_id`, `inicio_janela`) é único. | Must | O validador rejeita o lote com `duracao_min` diferente do configurado, `inicio_janela` desalinhado ou fora do horário e registro duplicado; avisa janelas faltantes. Há um teste pytest para cada caso. |
| RNF04 | Privacidade | As zonas são fixas, não se sobrepõem, têm ao menos 50 m² e nenhuma corresponde a um único posto de trabalho. Só entram zonas de circulação pública. São proibidas zonas de sanitário, fraldário, amamentação, ambulatório, espaço religioso, área de serviço, doca, área administrativa e qualquer área de acesso restrito a funcionários. Não há consulta por polígono arbitrário. [Validar com jurídico] | Must | O validador de `zonas.json` aceita apenas os seis tipos da seção 7.1, exige `area_m2 ≥ 50` e `vizinhos` existentes e simétricos; a planta passa por revisão manual. |
| RNF05 | Privacidade | O simulador entrega somente contagens. Posições e identificadores de agentes não saem do processo. | Must | O único arquivo de saída do simulador é `contagens.json`, e ele passa no validador de RNF01. |
| RNF06 | Privacidade | A retenção na granularidade de 15 minutos é configurável (`retencao_dias`, padrão 90 [Suposição]). | Could | Uma rotina apaga registros mais antigos que o prazo. Na demo, basta o parâmetro documentado. O prazo adequado precisa de validação jurídica. |
| RNF07 | Transparência | O campo `fonte` é obrigatório, e um conjunto de dados tem uma única fonte. Dado simulado nunca é apresentado como real. | Must | Registro sem `fonte` e lote com fontes misturadas são rejeitados (pytest). Com fonte simulada, o selo de RF05 aparece. |
| RNF08 | Transparência | A escala de cores é a mesma para todos os períodos de um conjunto de dados. | Must | Os valores da legenda não mudam ao mover o controle de tempo. |
| RNF09 | Desempenho | A página carrega em até 3 segundos e troca de janela ou de período em até 1 segundo, com 16 zonas e 7 dias. [Estimativa de meta] | Must | Medição com as ferramentas do navegador, no notebook da demo. |
| RNF10 | Reprodutibilidade | O simulador gera a mesma saída para a mesma semente, em até 60 segundos para 7 dias. [Estimativa de meta] | Must | Duas execuções com a mesma semente produzem arquivos idênticos. |
| RNF11 | Usabilidade | Uma pessoa que nunca viu o sistema identifica a zona mais cheia e a mais vazia de um sábado (período "dia inteiro") em até 60 segundos e 3 cliques. [Estimativa de meta] | Must | Teste com 3 pessoas de fora do grupo; aprovado se pelo menos 2 das 3 acertam as duas zonas no limite, sem ajuda. |
| RNF12 | Usabilidade | A escala é sequencial, legível por pessoas com daltonismo e acompanhada de legenda com unidade. "Abaixo do limiar" não usa a cor de "sem dado". | Must | Verificação com o simulador de daltonismo do navegador. |
| RNF13 | Operação | A demo roda sem internet, a partir de arquivos locais. | Must | Demo executada com a rede desligada. |
| RNF14 | Custo | A demo usa apenas software livre e gratuito. Custo de sensores em produção não é afirmado; se citado, é marcado como estimativa a orçar. | Must | Nenhuma dependência paga no repositório; nenhum valor de hardware nos slides sem a marcação. |

**Notas jurídicas [Validar com jurídico]:**
- O grupo entende que contagem agregada por zona e janela tende a não permitir identificar um indivíduo. Isso é hipótese de projeto, não conclusão.
- Contagens muito baixas em zonas pequenas podem, em tese, permitir inferência sobre alguém conhecido. Por isso existem RNF02, RNF03 e RNF04. O limiar reduz o risco, mas não o elimina.
- A visão computacional na borda capta imagem por um instante antes de descartá-la. Essa captação pode ser considerada tratamento de dado pessoal, mesmo sem armazenamento, e pode exigir aviso ao público e relatório de impacto.
- No pitch, a formulação é: "desenhado para não coletar dado pessoal; a conformidade precisa de validação jurídica".

**Tensão conhecida.** O limiar esconde justamente os valores baixos, que são os que identificam zonas frias. A solução adotada: a zona aparece como "abaixo do limiar", o que já responde "aqui está vazio". O ranking usa a média do período internamente para ordenar e classificar, mas não a exibe quando ela é menor que k nem quando o período contém janela suprimida.

---

## 6. Escopo do MVP

### 6.1 O que entra na demo

Três peças, sem servidor de aplicação e sem banco de dados (só o servidor de arquivos local do Python):

1. **Simulador e validador** (Python), que geram e conferem `contagens.json`.
2. **Arquivo de zonas** (`zonas.json`) e planta fictícia em SVG: um piso, 16 zonas.
3. **Página estática** (HTML, JavaScript e SVG), que lê os arquivos, calcula densidade, classe e ranking e desenha o mapa.

Funções: RF01 a RF07 e os RNF Must. Em seguida, nesta ordem fixa: RF08 (versão mínima), RF09, RF10, RF11, RF12. O RF13 é texto estático e fica com a pessoa de produto, em paralelo.

### 6.2 Real, simulado e roadmap

| Item | Situação |
|---|---|
| Algoritmo (densidade, classes, classificação, comparação) | Real, funcionando. |
| Interface com mapa, controle de tempo e ranking | Real, funcionando. |
| Contrato fechado, validador e testes de privacidade | Real, com testes que rodam diante da banca. |
| Supressão por limiar | Na demo, só na apresentação: o arquivo bruto fica acessível no navegador. Em produção, tem de ser aplicada no servidor ou no sensor, antes de o dado chegar ao cliente. Roadmap obrigatório. |
| Planta e áreas das zonas | Fictícias, desenhadas pelo grupo. |
| Contagens | Simuladas, com selo visível. |
| "Ao vivo" | É a reprodução acelerada do dia simulado, rotulada como tal. |
| Contadores, sensores de presença, visão na borda | Roadmap. Opções de fonte, sem protótipo. |
| Tempo real, vários pisos, previsão, alertas por mensagem, login, validação jurídica | Roadmap. |

---

## 7. Linguagem e método

### 7.1 Linguagem e stack

Critério: o menor número de peças que um grupo de estudantes entrega funcionando no prazo. São duas linguagens, cada uma em um lado: **Python gera e valida os dados; JavaScript calcula e desenha no navegador.** Não há servidor de aplicação, banco de dados nem etapa de compilação.

| Camada | Escolha | Justificativa | Alternativa |
|---|---|---|---|
| Simulador | Python 3.11+ com NumPy (`default_rng` com semente) | Linguagem que estudantes costumam dominar. NumPy sorteia Poisson e exponencial de forma reprodutível. O grafo de 16 zonas dispensa biblioteca: busca em largura em 15 linhas. | NetworkX para o caminho mais curto; Mesa (framework de agentes), com mais recursos e mais tempo de aprendizado. |
| Agregação por janela | pandas (`groupby` por zona e janela), dentro do simulador | Uma linha transforma amostras por minuto em média por janela de 15 minutos. | Dicionários em Python puro, se o pandas der problema de instalação. |
| Validador do contrato | Pydantic, com `extra="forbid"`, tipos estritos e enumerações | O contrato fechado (RNF01) vira declaração de poucas linhas, e a rejeição de campo extra é demonstrável ao vivo. | Validação manual com `set(registro.keys())`. |
| Algoritmo do mapa | JavaScript puro em `algoritmo.js`, sem acesso ao DOM | São somas, divisões e ordenações sobre cerca de 5.400 registros. Calcular no navegador torna a troca de período instantânea. Sem DOM, o mesmo arquivo roda no navegador e no Node para teste. | Pré-calcular em Python com pandas, o que simplifica o frontend e engessa a comparação. |
| Testes | pytest para `validar.py` e simulador (RNF01, RNF03, RNF05, RNF07); `node --test` (embutido no Node 18+, sem instalar pacotes) para `algoritmo.js` (RNF02, RF07). Um script `testar.py` roda os dois. | Os requisitos de privacidade viram testes que rodam com um comando, cada um na linguagem do código que exercita. | Sem Node: página `testes.html` que roda as asserções no navegador. |
| Formato de dados | Três arquivos JSON: `zonas.json`, `contagens.json` e `config.json` | O navegador lê direto, o grupo lê a olho nu, e o arquivo é o contrato entre as pessoas. `config.json` é lido pelo Python e pela página. | CSV (mais compacto, exige leitor no frontend); SQLite, se a API entrar. |
| API | Nenhuma no MVP | É mais uma peça para quebrar na demo e não responde a nenhuma das três perguntas. | FastAPI com Uvicorn, reaproveitando o modelo Pydantic (RF16), só depois de todos os Should. |
| Interface | HTML, CSS e JavaScript sem framework (`index.html`, `ui.js`); planta em SVG, com um `<path>` por zona e `id` igual a `zona_id` | Pintar o mapa é trocar o atributo `fill`. Não há instalação de pacotes nem build. | Streamlit com Plotly, tudo em Python: mais rápido de montar, com menos controle sobre a planta e a animação. |
| Carga dos scripts | Scripts clássicos (`<script src>`, sem `type="module"`); o algoritmo fica em um objeto global e é exportado para o Node com `if (typeof module !== 'undefined') module.exports = ...` | No Windows, o `http.server` pode servir `.js` com tipo errado, e o navegador recusa módulos nesse caso. Testar no notebook da demo no bloco 1. | Módulos ES com outro servidor local. |
| Cores | Tabela fixa no código: 5 cores sequenciais (amarelo claro a vermelho escuro) e 5 divergentes (azul a vermelho), do tipo ColorBrewer | Sem dependência e legível para daltônicos. | `d3-scale-chromatic` em arquivo local. |
| Gráfico da zona (RF11) | Chart.js em arquivo local | Um gráfico de linha em cerca de 15 linhas, com suporte a lacunas. | Linha desenhada em SVG à mão. |
| Visão suavizada (RF15) | `<canvas>` sobre o SVG, em grade de 1 metro | Não exige biblioteca. | `simpleheat` ou `heatmap.js`. |
| Servir a página | `python -m http.server` | Já vem com o Python e permite ao navegador ler os JSON. | Extensão Live Server do editor. |
| Desenho da planta | Inkscape ou Figma, exportando SVG com um `id` por zona | Gratuitos; o `id` liga o desenho ao dado. | Retângulos escritos à mão no SVG, suficientes para 16 zonas. |
| Versionamento | Git e GitHub, ramo principal sempre demonstrável | Padrão conhecido. | Pasta compartilhada, só em último caso. |

**Fluxo em uma linha:** `simulador.py → contagens.json → validar.py (contrato) → página estática → algoritmo.js → funções de publicação → ui.js → mapa SVG`.

**Regra de arquitetura: camada de publicação.** `algoritmo.js` expõe quatro funções, e só elas produzem valores para fora:

| Função | Devolve |
|---|---|
| `publicarJanela(z, t)` | Classe e densidade de uma zona em uma janela; sem número se c < k; `sem_dado` se não há registro. |
| `publicarPeriodo(z, P)` | Classificação (quente, neutra, fria, abaixo do limiar) e, só quando publicável, a densidade média. |
| `publicarComparacao(z, A, B)` | Classe de variação e, só quando A e B têm número, o percentual. |
| `publicarCurva(z, dia)` | Pontos da curva só para janelas com c ≥ k, lacunas para as demais, e o horário de pico. |

Mapa, ranking, frases, alerta, visão suavizada, CSV e API consomem apenas o que essas funções devolvem. `ui.js` nunca lê a contagem bruta.

**Contrato de dados (congelado nas duas primeiras horas):**

```json
// zonas.json
{ "id": "Z07", "nome": "Praça de alimentação", "tipo": "alimentacao",
  "area_m2": 600, "centroide": [42.0, 18.5], "vizinhos": ["Z05", "Z08"] }

// contagens.json
{ "zona_id": "Z07", "inicio_janela": "2026-11-07T12:00", "duracao_min": 15,
  "contagem": 212, "fonte": "simulado" }
```

Tipos e domínios dos campos: ver RNF01. Tipos de zona aceitos: `entrada`, `corredor`, `ancora`, `lojas`, `alimentacao`, `convivencia`; qualquer outro é rejeitado (RNF04). Volume da demo: 7 dias × 48 janelas × 16 zonas = 5.376 registros.

### 7.2 Método do algoritmo

**Entrada.** `c[z,t]` é a ocupação média da zona `z` na janela `t`: a média do número de pessoas presentes, amostrado uma vez por minuto, arredondada para inteiro antes de gravar. `A[z]` é a área de circulação da zona, em m².

```
c[z,t] = arredondar( (1/n) · Σ N_z(t_k) ),   k = 1..n,   n = 15 amostras por janela
```

O arredondamento evita que um valor fracionário (por exemplo 1/15) revele uma pessoa por um minuto, resolução mais fina que a janela. Em produção, sensores de presença e visão na borda entregam `N_z` diretamente. Contadores de entrada e saída entregam `N_z` como entradas acumuladas menos saídas acumuladas. Essa conversão fica no adaptador da fonte e não altera o algoritmo.

**Passo 1 — Supressão por limiar.**

```
se não há registro para (z,t):  classe = SEM_DADO
se c[z,t] < k:                  classe = ABAIXO_DO_LIMIAR  (sem número)        k = 5
```

Zero e valores de 1 a 4 caem na mesma classe, que assim não revela se havia alguém.

**Passo 2 — Densidade por área.** Contagem bruta favorece zonas grandes; a comparação justa é por área.

```
d[z,t] = c[z,t] / A[z]          (pessoas por m²; exibida como pessoas por 100 m²)
```

**Passo 3 — Limites fixos por quantis.** A distribuição de densidades é muito assimétrica (corredores de passagem contra a praça de alimentação), e classes de largura igual deixariam o mapa quase todo na classe 1. Os limites são os quantis de todas as densidades não suprimidas do conjunto, calculados uma única vez. Com isso, a mesma cor significa a mesma densidade em qualquer horário (RNF08).

```
L1, L2, L3, L4 = P20, P40, P60, P80 de { d[z,t] : c[z,t] ≥ k }, sobre todo o conjunto
```

Se houver menos de 5 valores não suprimidos (caso dos 20 registros escritos à mão do início), usam-se os limites padrão de `config.json`, com aviso no console.

**Passo 4 — Escala de cores.** A legenda mostra os limites em pessoas por 100 m², com a última faixa aberta ("≥ L4").

```
classe = 1 + quantidade de limites L_i menores ou iguais a d[z,t]        cor = PALETA[classe]
```

| Classe | Densidade | Leitura |
|---|---|---|
| Sem dado | registro ausente para (z,t) | Cinza liso, sem hachura |
| Abaixo do limiar | suprimida | Menos de k pessoas; hachura cinza |
| 1 | abaixo de L1 | Muito baixa |
| 2 | L1 a L2 | Baixa |
| 3 | L2 a L3 | Média |
| 4 | L3 a L4 | Alta |
| 5 | L4 ou mais | Muito alta |

**Passo 5 — Zona quente e zona fria no período.** `P` é o período ativo de RF02, em um dia.

```
c̄[z] = média de c[z,t] para t em P          (valor interno)
m[z] = c̄[z] / A[z]                          (valor interno)
quente  se m[z] ≥ P80 das zonas
fria    se m[z] ≤ P20 das zonas  ou  c̄[z] < k
neutra  nos demais casos

publicável(z, P)  se toda janela t em P tem c[z,t] ≥ k
publicarPeriodo(z, P):
    se c̄[z] < k:            {classificação: fria, classe: abaixo_do_limiar}
    se publicável(z, P):     {classificação, densidade_media: m[z] com 1 casa por 100 m²}
    senão:                   {classificação}                    (sem número)

m_piso = Σ c̄[z] / Σ A[z], sobre as zonas com número publicado; exibido no cabeçalho do ranking
```

O ranking ordena por `m[z]` as zonas com número; as zonas sem número vêm depois, agrupadas por classificação e em ordem alfabética, para que a posição não revele o valor. Uma zona é **fria no dia D** quando classificada como fria com P = dia inteiro de D, e é **fria persistente** quando isso ocorre em pelo menos 70% dos dias do conjunto (RF09). Com 16 zonas, o resultado são 3 ou 4 quentes e 3 ou 4 frias.

**Passo 6 — Comparação entre períodos A (referência) e B.** A e B são pares (dia, faixa).

```
se A e B têm número publicado para z:
    Δ[z] = m_B[z] − m_A[z]
    r[z] = Δ[z] / max( m_A[z], ε )                    ε = 0,005 pessoa/m²
    relevante  se abs(r[z]) ≥ 0,20  e  abs(Δ[z]) ≥ 0,01 pessoa/m²
senão se c̄_A[z] < k  e  c̄_B[z] ≥ k:   "saiu do limiar"          (sem percentual)
senão se c̄_A[z] ≥ k  e  c̄_B[z] < k:   "caiu abaixo do limiar"   (sem percentual)
senão:                                 "sem comparação"
```

A condição dupla evita anunciar "aumento de 100%" em uma zona que foi de 1 para 2 pessoas. Escala divergente: queda forte (r ≤ −50%), queda (−50% < r ≤ −20%), estável (−20% < r < 20%, ou qualquer zona não relevante), aumento (20% ≤ r < 50%), aumento forte (r ≥ 50%). "Saiu do limiar" e "caiu abaixo do limiar" têm destaque próprio no mapa de diferença; é assim que o cenário de campanha na ala fria aparece. "Sem comparação" usa a hachura de limiar.

**Passo 7 — Curva e horário de pico (RF11).** A curva é construída só com saídas de `publicarJanela()`. Janela suprimida vira lacuna hachurada "abaixo do limiar", sem ponto. A média móvel centrada usa apenas os vizinhos existentes e não suprimidos, dividindo pelo número de termos (nas bordas do dia, 2 valores). O mapa mostra o valor da janela, sem suavizar.

```
V(t)    = { t−1, t, t+1 } que existem no dia e têm c[z,·] ≥ k
s[z,t]  = média de d[z,u] para u em V(t)        (só se c[z,t] ≥ k)
t*[z]   = argmax de s[z,t] entre as janelas publicadas do dia
```

**Passo 8 — Frases automáticas (RF08).** Modelos fixos de texto, sem modelo de linguagem, preenchidos só com saídas das funções de publicação. Com `fonte = "simulado"`, cada frase é prefixada com "[Simulação]", a recomendação aparece como "Exemplo de ação: ..." e o painel repete o selo. Quando o número não é publicável, a frase sai sem ele.

| Regra | Condição | Exemplo |
|---|---|---|
| F1 (C) | Zona mais quente do período | "[Simulação] No almoço, a Praça de alimentação é a zona mais cheia, com densidade 3,1 vezes a média do piso." (m[z] / m_piso) |
| F2b (V) | Zona mais fria do período (passo 5); usada enquanto RF09 não existe | "[Simulação] No almoço, a Ala Norte é a zona de menor circulação (abaixo do limiar)." |
| F3b (T) | Horário de pico do piso no dia: janela de maior ocupação total; só o horário é exibido | "[Simulação] O piso atinge o pico por volta das 12h45." |
| F2 (V) | Zona fria persistente (RF09) | "[Simulação] A Ala Norte esteve entre as zonas mais frias em 6 dos 7 dias. Exemplo de ação: avaliar sinalização, ação de marketing ou revisão do mix." |
| F3 (T) | Pico t* na mesma janela, com tolerância de ±1 janela, em pelo menos 4 dias (RF11) | "[Simulação] A Praça de alimentação atinge o pico por volta das 12h45 em 5 dias. Exemplo de ação: reforçar a limpeza a partir das 12h." |
| F4 (C) | Corredor classificado como quente | "[Simulação] O Corredor Central ficou entre as zonas mais movimentadas em 6 dos 7 dias. Exemplo de ação: avaliar quiosque ou mídia no local." |
| F5 (T) | Variação relevante entre A e B (RF10) | "[Simulação] O fluxo na Área de convivência subiu 27% no sábado em relação à terça-feira." |

**Passo 9 — Alerta de lotação (RF14, opcional).** Calculado sobre a densidade devolvida por `publicarJanela()`.

```
alerta  se d[z,t] ≥ d_alerta  e  d[z,t−1] ≥ d_alerta        d_alerta = 0,8 pessoa/m²
```

O valor é de demonstração [Suposição]. Não é norma de segurança e deve ser definido com a equipe de operações e segurança.

**Passo 10 — Visão suavizada (RF15, opcional).** Para cada ponto `x` da grade de 1 metro dentro da área de circulação:

```
w_z(x) = exp( −‖x − centroide_z‖² / (2σ²) )        σ = 8 m
d(x)   = Σ w_z(x) · d[z,t]  /  Σ w_z(x),   só sobre zonas com c[z,t] ≥ k
cor(x) = PALETA[ classe de d(x) pelos limites do passo 3 ]
```

Zonas com c[z,t] < k ficam fora da soma e são cobertas pela hachura "abaixo do limiar" por cima do canvas. Este passo é só visual. O dado tem resolução de zona, e a tela diz isso. A visão padrão é a coroplética (uma cor por zona).

**Parâmetros em um só lugar (`config.json`, lido pelo Python e pela página)**

| Parâmetro | Padrão | Significado |
|---|---|---|
| `duracao_min` | 15 | Janela de agregação, única no conjunto |
| `horario` | 10:00 a 22:00 | Funcionamento; janelas fora dele são rejeitadas |
| `k` | 5 | Contagem mínima exibida [Validar com jurídico] |
| `area_min_m2` | 50 | Área mínima de zona |
| `retencao_dias` | 90 | Retenção na granularidade de janela |
| Faixas de período | manhã 10–12, almoço 12–14, tarde 14–18, noite 18–22, dia inteiro | Lista fechada de períodos (mínimo de 8 janelas) |
| Quantis das classes | 20, 40, 60, 80 | Limites da escala de cores; limites padrão para conjunto pequeno |
| Percentis de classificação | 80 e 20 | Cortes de zona quente e fria |
| Persistência | 70% dos dias | Zona fria persistente |
| Variação relevante | 20% e 0,01 pessoa/m² | Cortes da comparação |
| `ε` | 0,005 pessoa/m² | Evita divisão por quase zero |
| `d_alerta` | 0,8 pessoa/m² | Demonstração |
| `σ` | 8 m | Suavização espacial opcional |

Todos os valores padrão desta tabela são [Suposição] do grupo, a calibrar com dados reais.

**Pseudocódigo**

```
carregar config, zonas, contagens
para cada registro r:
    c[r.zona, r.janela] = r.contagem
    d[r.zona, r.janela] = r.contagem / area[r.zona]
LIMITES = quantis(d onde c >= K, [20, 40, 60, 80])  ou  config.limites_padrao se houver menos de 5 valores

função publicarJanela(z, t):
    se c[z,t] indefinido: devolver {classe: "sem_dado", fonte}
    se c[z,t] < K:        devolver {classe: "abaixo_do_limiar", fonte}
    devolver {densidade: d[z,t], classe: 1 + contar(LIMITES <= d[z,t]), fonte}

função interno(P):                               # não exportada
    para cada zona z:
        cm[z] = media(c[z,t] para t em P);  m[z] = cm[z] / area[z]
        pub[z] = todo t em P tem c[z,t] >= K
    devolver cm, m, pub

função publicarPeriodo(z, P):
    cm, m, pub = interno(P)
    classif = "quente" se m[z] >= percentil(m, 80); "fria" se m[z] <= percentil(m, 20) ou cm[z] < K; senão "neutra"
    se cm[z] < K: devolver {classificacao: "fria", classe: "abaixo_do_limiar", fonte}
    se pub[z]:    devolver {classificacao: classif, densidade_media: arredondar(m[z]), fonte}
    devolver {classificacao: classif, fonte}

função publicarComparacao(z, A, B):
    a = publicarPeriodo(z, A);  b = publicarPeriodo(z, B)
    se a e b têm densidade_media:
        delta = b.densidade_media - a.densidade_media
        r = delta / max(a.densidade_media, EPS)
        se abs(r) < 0.20 ou abs(delta) < 0.01: devolver {variacao: "estavel"}
        devolver {variacao: faixa(r), percentual: r}
    se a é abaixo_do_limiar e b não é: devolver {variacao: "saiu_do_limiar"}
    se b é abaixo_do_limiar e a não é: devolver {variacao: "caiu_abaixo_do_limiar"}
    devolver {variacao: "sem_comparacao"}

função publicarCurva(z, dia):
    para cada janela t do dia:
        p = publicarJanela(z, t)
        se p tem densidade: ponto[t] = media(densidades publicadas em t-1, t, t+1)
        senão:              ponto[t] = lacuna
    devolver pontos, pico = argmax(ponto)

# ui.js: desenhar(t) pinta cada zona com PALETA[publicarJanela(z, t).classe];
# ranking, frases e CSV chamam publicarPeriodo; nada em ui.js lê c ou d.
```

**Onde o limiar é aplicado.** No MVP, `contagens.json` guarda a contagem agregada bruta e o limiar é aplicado na saída, pela camada de publicação. Isso permite classificar zonas frias pela média do período. O arquivo bruto fica acessível ao navegador, o que é aceitável com dados simulados e não seria em produção: lá, a supressão passa para um servidor ou, na opção mais protetiva, para o próprio sensor, ao custo de perder precisão nas zonas frias. [Validar com jurídico]

### 7.3 Método do simulador

O simulador existe para produzir contagens plausíveis no mesmo contrato que um sensor real usaria. É tratado como um sensor: os agentes existem só dentro dele, e o sistema recebe o número (RNF05).

**Modelo.** A planta é um grafo: cada zona é um nó, com tipo, área e atratividade, e zonas vizinhas são ligadas por arestas. O tempo avança em passos de 1 minuto, das 10h às 22h.

**Chegadas com picos por período.** A cada minuto, o número de agentes que entram é sorteado de uma Poisson com taxa variável: uma base, dois picos gaussianos (almoço e noite) e um fator do dia da semana.

```
λ(h, dia) = λ0 · f(dia) · [ b + a1·g(h; 12,5; 1,0) + a2·g(h; 19,0; 1,5) ]
g(h; μ; σ) = exp( −(h − μ)² / (2σ²) )            (h em horas)
chegadas no minuto ~ Poisson( λ(h, dia) )
```

**Destinos.** Cada agente sorteia `1 + Poisson(1,5)` destinos, limitados a 4. A probabilidade de escolher a zona `z` é proporcional à atratividade da zona multiplicada pelo fator do tipo naquele horário.

```
p_z(h) ∝ a_z · m_tipo(h)
```

`m_tipo` vale 0 para `entrada` e `corredor` (nunca são destino, só são atravessados); 3 para `alimentacao` das 11h30 às 14h e das 18h30 às 21h; e 1 nos demais casos.

**Movimento e permanência.** O agente segue o caminho mais curto no grafo (busca em largura), gastando 1 minuto por zona atravessada. Com isso, os corredores entre a entrada e a praça de alimentação enchem sozinhos, sem regra específica. No destino, permanece por um tempo sorteado de uma exponencial com média por tipo, limitado a 90 minutos. Depois do último destino, sai pela entrada mais próxima e é descartado.

**História plantada.** A planta traz uma ala fria (atratividade 0,2) e uma zona quente (praça de alimentação). O teste de validação é o algoritmo reencontrar as duas sem saber quais são (RF07). Cenários injetáveis: campanha na ala fria em um dia (para RF10; a ala passa de "abaixo do limiar" para acima dele na faixa da campanha) e lotação em uma entrada (para RF14).

**Parâmetros (todos são suposições do grupo, não medições de nenhum shopping)**

| Parâmetro | Valor | Significado |
|---|---|---|
| `λ0` | 8 agentes por minuto | Escala geral; ajustar até a praça chegar à classe 5 no almoço |
| `b`, `a1`, `a2` | 0,3; 1,0; 0,8 | Base, pico do almoço, pico da noite |
| `f(dia)` | 1,0 de segunda a quinta; 1,2 sexta; 1,6 sábado; 1,4 domingo | Fator do dia |
| Permanência média | entrada 1 min; corredor 1 min; lojas 8 min; convivência 12 min; âncora 15 min; alimentação 35 min | Média da exponencial por tipo (teto de 90 min) |
| `a_z` | 1,0 padrão; 0,2 ala fria; 2,0 loja âncora | Atratividade |
| Semente | 42 | Mesmo resultado a cada execução |

**Pseudocódigo**

```
rng = gerador(semente)
para cada dia em 7 dias:
    agentes = []
    para cada minuto de 10:00 a 22:00:
        n = rng.poisson(lambda(hora, dia))
        repetir n vezes: agentes.adicionar(novo_agente(entrada sorteada, destinos por p_z))
        para cada agente:
            se em permanência: descontar 1 minuto
            senão se chegou ao destino: permanência = min(90, rng.exponencial(media[tipo])); próximo destino
            senão: mover para a próxima zona do caminho mais curto
            se concluiu e está na entrada: remover
        amostra[zona, minuto] = quantidade de agentes na zona
contagens = arredondar(média de amostra por (zona, janela de 15 min))      # inteiro
gravar apenas {zona_id, inicio_janela, 15, contagem, "simulado"}
```

**Verificações de plausibilidade.** (1) A curva de ocupação total tem dois picos. (2) A cada minuto, entradas acumuladas no dia = saídas acumuladas no dia + agentes presentes. (3) A praça de alimentação lidera no almoço. (4) Ao menos 2 zonas ficam abaixo do limiar em parte do dia, para demonstrar a supressão. (5) A mesma semente gera o mesmo arquivo. (6) Sábado tem mais movimento que terça-feira. (7) No sábado às 13h aparecem ao menos 4 das 5 classes. (8) No almoço de sábado, as 3 zonas mais quentes têm número publicável. (9) No dia da campanha, a ala fria tem média ≥ k na faixa da campanha.

**Gerador paramétrico (feito primeiro, e plano B).** Nas primeiras horas, a pessoa de dados entrega um gerador direto, sem agentes, para destravar o restante do grupo:

```
c[z,t] ~ Poisson( A[z] · base[z] · g_tipo(h) · f(dia) )
```

O contrato de saída é o mesmo (contagem inteira), e o resto do sistema não percebe a troca. Se o simulador de agentes não passar nas verificações até 40% do prazo, o gerador fica em definitivo e o grupo passa a dizer "gerador de dados sintéticos" no pitch, em vez de "simulador de agentes".

### 7.4 Método de trabalho

**Princípios**

1. **Contrato primeiro.** Nas duas primeiras horas, o grupo congela `zonas.json`, `config.json` e o formato de `contagens.json`. O frontend começa com 20 registros escritos à mão e não espera o simulador.
2. **Ponta a ponta antes de qualquer melhoria.** A primeira meta é um mapa feio, funcionando do gerador até a tela.
3. **Ramo principal sempre demonstrável.** Sincronização de 10 minutos a cada 4 horas: o que funciona, o que trava, o que se corta. A integração acontece nesse momento, não no final.
4. **Uma coisa por vez.** A ordem dos Should é fixa (RF08, RF09, RF10, RF11, RF12). Itens Could só começam com todos os Must e Should prontos.
5. **Pronto** significa: funciona no notebook da apresentação, com o conjunto de dados da demo.
6. **Sono no plano.** O revezamento de descanso é definido no bloco de alinhamento.

**Papéis (suposição de 4 pessoas)**

| Papel | Responsabilidade | Entrega principal |
|---|---|---|
| Dados | Gerador paramétrico, simulador de agentes, verificações de plausibilidade, cenários | `simulador.py` e `contagens.json` |
| Algoritmo e privacidade | Densidade, classes, camada de publicação, ranking, comparação, frases, validador e testes (Python e Node) | `algoritmo.js`, `validar.py`, testes pytest e `node --test`, `testar.py` |
| Frontend | Planta em SVG, mapa, controle de tempo, seletor de período, painéis, selo | `index.html`, `ui.js` |
| Produto e pitch | Planta fictícia e `zonas.json`, RF13, teste com pessoas de fora, roteiro, slides, vídeo de reserva, controle do relógio e dos cortes | Pitch ensaiado e vídeo gravado |

Com 5 pessoas, a quinta divide o frontend. Com 3, produto e pitch são acumulados por quem cuida de dados, que fica mais livre depois que o arquivo de contagens está pronto.

**Pontos de corte de escopo**

| Momento (36 h) | Condição | Decisão |
|---|---|---|
| 8% (hora 3) | Gerador paramétrico não entregou 1 dia de dados no contrato | A pessoa de algoritmo ajuda até sair. O simulador de agentes espera. |
| 25% (hora 9) | Mapa por zona não funciona de ponta a ponta para uma janela | Todo o grupo para e ajuda. Nada mais avança. |
| 40% (hora 14) | Simulador de agentes não passa nas verificações | Ficar com o gerador paramétrico em definitivo. |
| 55% (hora 20) | Algum Must não funciona | Suspender todo Should até funcionar. |
| 70% (hora 25) | RF10 (comparação) não concluído | Cortar RF10, RF11 e RF12. A comparação vira duas capturas lado a lado no pitch, ambas com o selo. |
| 80% (hora 29) | Congelamento | O que não funciona sai da interface. Nenhuma função nova. |
| 88% (hora 32) | Vídeo de reserva não gravado | Gravar antes de qualquer outro ajuste. |

---

## 8. Histórias de usuário do MVP

**H1 — Onde concentra.** Como coordenador de operações, quero ver na planta quais zonas estão mais cheias em um horário, para dimensionar limpeza e segurança.
- Ao escolher sábado, 13h, o mapa mostra as zonas em 5 classes, com legenda em pessoas por 100 m².
- As 3 zonas mais cheias aparecem no ranking e, com período "janela atual", a primeira é a mais intensa do mapa.

**H2 — Onde está vazio.** Como gerente comercial, quero ver quais zonas têm menor circulação, para decidir onde agir com quiosques, eventos ou revisão do mix.
- As 3 zonas mais vazias do período aparecem na lista.
- Zonas abaixo do limiar aparecem como "abaixo do limiar", sem número.
- A ala fria plantada aparece entre as frias em pelo menos 5 dos 7 dias (70%, RF09).

**H3 — Como muda no tempo.** Como coordenador de operações, quero mover um controle de horário e ver o mapa mudar, para entender os picos do dia.
- Ao mover o controle, mapa e ranking atualizam dentro do limite de RNF09.
- A praça de alimentação aparece na classe mais alta entre 12h e 14h e não aparece nela às 10h.

**H4 — Confiança no dado.** Como membro da banca ou encarregado de dados, quero comprovar que o dado é simulado e que o contrato de entrada rejeita qualquer campo além dos cinco previstos, para confiar na proposta.
- O selo "DADOS SIMULADOS · PLANTA FICTÍCIA" está no mapa e em cada painel.
- `python testar.py` roda o pytest (RNF01, RNF03, RNF07) e o `node --test` (RNF02), e os dois passam.
- Um lote com campo extra é rejeitado diante do avaliador.

**H5 — Leitura pronta (Should).** Como gerente comercial, quero frases que resumam o período, para levar a conclusão a uma reunião sem interpretar o mapa.
- Aparecem pelo menos 3 frases, uma por pergunta do enunciado (F1, F2b, F3b).
- Cada número de frase confere com o ranking, e toda frase traz o prefixo "[Simulação]".

**H6 — Comparar períodos (Should).** Como analista de marketing, quero comparar sábado com terça-feira, ou o dia da campanha com o dia anterior, para medir a diferença.
- O mapa mostra aumento e queda em cores diferentes, com legenda em percentual.
- A lista traz só as variações relevantes e as zonas que saíram ou caíram do limiar; zonas quase vazias não são destacadas.

**H7 — Curva da zona (Should).** Como coordenador de operações, quero ver a curva de uma zona ao longo do dia e o horário de pico, para ajustar a escala de limpeza.
- Clicar na zona abre o gráfico com o pico destacado e com lacunas nas janelas abaixo do limiar.

---

## 9. Riscos e mitigação

| Tipo | Risco | Mitigação |
|---|---|---|
| Técnico | O simulador de agentes consome o prazo. | Gerador paramétrico primeiro; ponto de corte em 40%. |
| Técnico | Os `id` do SVG não batem com os do JSON. | `zonas.json` é a única fonte dos identificadores; a página lista as zonas sem correspondência ao carregar. |
| Técnico | O desenho da planta atrasa o frontend. | Começar com retângulos escritos à mão e trocar pela planta desenhada depois. |
| Técnico | A demo falha ao vivo. | Demo sem internet, semente fixa, dados gerados antes, scripts clássicos testados no notebook da demo, vídeo de reserva. |
| Dados | A banca vê os dados como inventados, ou como reais. | Selo no mapa e em cada painel, prefixo "[Simulação]" nas frases, tela de parâmetros (RF13) e a explicação de que o algoritmo independe da fonte. Recomendações aparecem só como "Exemplo de ação". |
| Dados | O mapa fica uniforme, sem contraste. | Atratividades bem diferentes, história plantada, classes por quantis e a verificação de plausibilidade 7. |
| Dados | O limiar esconde as zonas frias ou os números do ranking. | Classe "abaixo do limiar", classificação sem número e a verificação de plausibilidade 8. |
| Dados | A visão suavizada sugere precisão de metro. | Visão por zona como padrão; suavizada opcional, com aviso. |
| Dados | Reidentificação por contagem pequena ou por diferença entre consultas. | RNF01 a RNF04: contagem inteira, lista fechada de períodos, número de período só sem janela suprimida, zonas só de circulação pública. Em produção, avaliar também arredondamento das contagens publicadas. [Validar com jurídico] |
| Prazo | O grupo investe em itens Could antes de fechar os Must. | Ordem fixa de prioridade; a pessoa de produto controla os pontos de corte. |
| Prazo | Integração deixada para o fim. | Ponta a ponta até 25% do prazo; integração a cada 4 horas. |
| Jurídico | A afirmação "a LGPD não se aplica" é contestada. | O grupo não faz essa afirmação. Diz que o desenho reduz o risco e que a conclusão depende de validação jurídica. |
| Jurídico | Visão na borda envolve captação de imagem. | Apresentar como uma das três opções de fonte, condicionada a avaliação jurídica própria. Contadores e sensores de presença sem imagem são, na hipótese do grupo, a opção de menor risco. [Validar com jurídico] |
| Produto | "Mapa de calor é comum." | O diferencial está na leitura: ranking, zona fria persistente, comparação com filtro de relevância e frases prontas, com contrato de entrada sem campo para dado pessoal e regras de supressão verificadas por teste. |

---

## 10. Métricas de sucesso

### 10.1 Do produto (para um piloto; metas são estimativas a calibrar)

- Tempo para a administração responder "qual é a zona mais vazia às terças-feiras": menos de 1 minuto.
- Número de decisões registradas por mês que citam o mapa (escala, quiosque, campanha).
- Mudança em zonas frias após uma ação, medida pela comparação de períodos: variação percentual ou saída do limiar.
- Saídas com valor abaixo do limiar: zero. Campos de dado pessoal no contrato: zero.
- Diferença entre contagem do sensor e contagem manual em amostra de validação. [Estimativa de meta: até 10%]

### 10.2 Da demo, com os critérios supostos da banca (S3)

| Critério suposto | Entrega que responde | Medida |
|---|---|---|
| Aderência ao problema | RF01, RF02, RF03 | As três perguntas do enunciado respondidas na tela em menos de 2 minutos. [Estimativa de meta] |
| Demo funcionando | Caminho de ponta a ponta | 100% dos Must com critério de aceite cumprido; 3 ensaios seguidos sem erro. |
| Inovação | Regras de privacidade testadas; RF08, RF09, RF10 | A banca vê a rejeição de um lote com campo extra e, se prontos, uma frase automática e uma comparação (ou as duas capturas lado a lado). |
| Viabilidade | Contrato independente da fonte | Explicação de como trocar o simulador por sensores sem mudar o sistema. |
| Apresentação | Pitch ensaiado | Pitch dentro do tempo em 3 ensaios cronometrados. |

Validação técnica: o algoritmo reencontra a zona quente e a ala fria plantadas (RF07), e o teste com 3 pessoas de fora do grupo cumpre o RNF11.

---

## 11. Plano por blocos de tempo

Horas para a suposição de 36 horas (S1). O percentual vale para qualquer duração.

| Bloco | % | Horas | Dados | Algoritmo e privacidade | Frontend | Produto e pitch |
|---|---|---|---|---|---|---|
| 0. Alinhamento | 0–6% | 0–2 | Todos: contrato de dados, `config.json`, lista de zonas, papéis, revezamento de sono, perguntas à organização | | | |
| 1. Esqueleto | 6–25% | 2–9 | Gerador paramétrico (1 dia até a hora 3), depois simulador de agentes | Densidade, classes, `publicarJanela()`, `validar.py`; `node --test` rodando | Página que lê JSON no notebook da demo; planta SVG pintada; controle de tempo | Planta e `zonas.json`; confere os `id`; começa o roteiro |
| **Marco 1** | 25% | 9 | **Mapa por zona de ponta a ponta, para uma janela** | | | |
| 2. Núcleo | 25–55% | 9–20 | Simulador de agentes validado até a hora 14 (senão, gerador paramétrico em definitivo); das horas 14 a 20, 7 dias de dados, história plantada e cenários injetáveis | `publicarPeriodo()`, ranking, classificação; testes RNF01 a RNF03 e RNF07 | Ranking, seletor de período, legenda, classe de limiar, selo, verificação de daltonismo | Teste com 3 pessoas de fora (RNF11); RF13 |
| **Marco 2** | 55% | 20 | **Todos os Must prontos (RF01 a RF07 e RNF Must)** | | | |
| 3. Leitura | 55–80% | 20–29 | Cenário de campanha; apoio ao frontend | Frases, fria persistente, comparação, curva e pico | Painel de frases, mapa de diferença, painel da zona, reprodução | Slides e roteiro final; descanso em turnos |
| **Congelamento** | 80% | 29 | **Nenhuma função nova a partir daqui** | | | |
| 4. Polimento | 80–94% | 29–34 | Roteiro de dados da demo (dias e horários exatos) | Conferência manual dos números | Ajuste visual e legenda | Vídeo de reserva até a hora 32; 3 ensaios cronometrados |
| 5. Reserva | 94–100% | 34–36 | Todos: folga, ambiente da demo, perguntas difíceis. Nenhum código novo. | | | |

Com 24 horas: aplicar os percentuais e levar só RF08 (F1, F2b, F3b) dos Should. Com 48 horas: usar o tempo extra nos Could, na ordem RF15, RF14, RF17, RF16.

---

## 12. Roteiro de pitch (5 minutos, suposição)

1. **Problema (40 s).** "A administração decide escala de equipe, posição de quiosque e campanha sem saber, com dados, onde as pessoas estão. O desafio traz três perguntas: onde concentra, onde está vazio e como muda no tempo."
2. **Demonstração (2 min), uma cena por pergunta.**
   - Onde concentra: sábado às 13h, com a praça de alimentação e os corredores de acesso em destaque, e o ranking ao lado.
   - Onde está vazio: a ala fria, a classe "abaixo do limiar" e a frase automática sobre a zona fria persistente. Sem RF09: mostrar a ala fria no ranking de dois dias diferentes.
   - Como muda no tempo: reprodução do dia e, se RF10 estiver pronto, sábado contra terça-feira no mapa de diferença. Sem RF12: mover o controle à mão por 10h, 13h, 16h e 19h. Sem RF10: duas capturas lado a lado.
   - Dizer em voz alta que os dados são simulados e a planta é fictícia, e apontar o selo.
3. **Diferencial (50 s).** "O mapa é o meio. A entrega é a leitura: o sistema diz qual zona é fria de forma persistente e o que mudou entre dois períodos, filtrando variações irrelevantes. E foi desenhado para não receber dado pessoal: a entrada é um número por zona a cada 15 minutos." Enviar ao validador um lote com `device_id` e mostrar a rejeição.
4. **Viabilidade e custo (50 s).** "O que é real: algoritmo, interface e testes. O que é simulado: os dados e a planta. O sistema independe da fonte: em produção, o número vem de contadores de entrada, sensores de presença ou câmera na borda que descarta a imagem; esta última opção capta imagem e depende de avaliação jurídica própria. A demo roda em um notebook, com software livre." Não citar valor de hardware sem orçamento; se perguntarem, dizer que o custo depende do número de zonas e ainda precisa ser levantado.
5. **Próximos passos (40 s).** Piloto em uma ala com uma fonte real, supressão por limiar aplicada no servidor ou no sensor, validação jurídica do desenho de privacidade, calibração do limiar e do limite de alerta com a operação, vários pisos e previsão de pico com histórico.

**Perguntas prováveis e respostas preparadas**
- "Os dados são reais?" Não. São simulados, e isso está escrito na tela. O contrato é o mesmo de um sensor real.
- "Isso atende à LGPD?" O sistema foi desenhado para não coletar dado pessoal e não exibe contagens baixas. Na demo, a supressão é feita na tela; em produção, ela tem de acontecer antes de o dado sair do servidor. A conformidade precisa de validação jurídica, principalmente se a fonte for visão computacional.
- "Contagem pequena não identifica alguém?" Pode, em tese. Por isso existem o limiar de 5, a janela de 15 minutos, a lista fechada de períodos e as zonas só de circulação pública, com área mínima.
- "Por que não Wi-Fi?" Porque o identificador do aparelho traz risco de reidentificação. O grupo descartou a opção.
- "O que isso tem que um mapa de calor comum não tem?" A leitura pronta, a comparação com filtro de relevância e regras de privacidade verificadas por teste automatizado.

---

## 13. Três decisões que o grupo precisa tomar agora

1. **Página estática em JavaScript com SVG, ou tudo em Python com Streamlit?**
   Recomendação: página estática, lendo JSON gerado em Python, sem API, com o algoritmo em um arquivo sem DOM testado pelo Node. Dá controle sobre a planta e a animação e não tem servidor de aplicação para cair. Trocar por Streamlit com Plotly só se ninguém do grupo tiver prática com JavaScript, e decidir isso no bloco 0, não no meio do evento.

2. **Simulador de agentes ou gerador direto de contagens?**
   Recomendação: os dois, nesta ordem. O gerador paramétrico sai nas três primeiras horas e destrava o grupo. O simulador de agentes vem em seguida, porque enche os corredores por consequência e sustenta melhor o pitch. Se ele não passar nas verificações até 40% do prazo, o gerador fica, sem discussão.

3. **Quais valores de limiar e janela, e onde aplicar o limiar?**
   Recomendação: k = 5, janela de 15 minutos e lista fechada de períodos, com o limiar aplicado na saída pela camada de publicação e a classe "abaixo do limiar" visível no mapa. Declarar no pitch que os valores são configuráveis, que na demo a supressão é só de apresentação e que tudo depende de validação jurídica.

Antes de tudo isso, confirmar com a organização a duração, o formato do pitch e os critérios da banca (perguntas 1, 2 e 5 da seção 2.3), porque as respostas mudam o plano da seção 11 e a tabela da seção 10.2.
