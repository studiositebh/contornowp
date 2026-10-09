# Relatório de validação do token EVO

> Status: **todas as permissões de LEITURA comprovadas** (09/10/2026 18:46 UTC, chave GUID `4146...A39A`), depois de o cliente liberar Prospects e Vendas - Consulta. Faltam: os testes controlados de escrita (prospect e venda) e **2 correções no nosso plugin**.
> Última atualização: 09/10/2026.

## RESULTADO

| Item | Resultado |
|---|---|
| **TOKEN EVO** | **APROVADO para leitura**; escrita pendente de teste controlado |
| **Multifilial** | **SIM**: 72 filiais visíveis; filiais 1 e 8 com dados próprios e diferentes |
| **Planos** | **OK**: 200 nas duas filiais. ⚠️ Formato em "envelope" diferente da documentação, e o plugin precisa ser ajustado (defeito nosso) |
| **Atividades** | **OK** |
| **Grade por unidade** | **OK** (filial 8: 26 aulas na semana; filial 1: sem aulas cadastradas na EVO) |
| **Consulta de cliente** | **OK** |
| **Consulta de prospect** | **OK** (era 403 às 18:37, passou a 200 às 18:46) |
| **Criação/edição de prospect** | TESTE CONTROLADO NECESSÁRIO |
| **Criação de venda** | TESTE CONTROLADO NECESSÁRIO. A leitura `sales/by-session-id` agora dá 200; ⚠️ o plugin lê o campo errado (defeito nosso, crítico) |
| **EVO Pay** | NÃO HOMOLOGADO. O gateway real é Vindi (tipo 9), `tokenizeBackend: false`, com `gerarFormToken` (§7) |
| **Permissões excessivas identificadas** | NÃO AVALIADO (fora do escopo desta rodada) |

## 1. Ambiente encontrado

| Item | Valor | Onde |
|---|---|---|
| Integração | plugin `contorno-evo-sync` 1.1.0 | `wp-content/plugins/contorno-evo-sync/` |
| Cliente HTTP | `Contorno_Evo_Client` | `includes/class-client.php` |
| Base URL | `https://evo-integracao-api.w12app.com.br` | `CONTORNO_EVO_DEFAULT_BASE_URL` (`contorno-evo-sync.php`) |
| Autenticação | `Authorization: Basic base64(DNS:TOKEN)` | `class-client.php` (`request()`) |
| Headers enviados | `Accept: application/json`, `culture: pt-BR`, `Authorization`, e `Content-Type: application/json` quando há corpo | idem |
| Documentação oficial | swagger `…/swagger/v1/swagger.json` (esquema `Basic`: "DNS of your gym as the User and the Secret Key as the password") e api.abcevo.com | coletado em 30/09/2026 |
| Limites | 40 req/min por IP, 10.000/h por chave, 20.000/h por DNS; liberado das 0h às 5h | api.abcevo.com |

### Onde ficam as credenciais (nomes REAIS no projeto)

| Nome | Tipo | Uso |
|---|---|---|
| `CONTORNO_EVO_USERNAME` | constante em `wp-config.php` (opcional) | DNS; tem precedência sobre o banco |
| `CONTORNO_EVO_TOKEN` | constante em `wp-config.php` (opcional) | token |
| `CONTORNO_EVO_BASE_URL` | constante em `wp-config.php` (opcional) | base URL (lista fechada de hosts) |
| option `contorno_evo_settings['dns']` | banco | DNS |
| option `contorno_evo_token` | banco, cifrado (libsodium, chave derivada dos salts do `wp-config.php` do servidor) | token |
| `EVO_DNS`, `EVO_TOKEN`, `EVO_BASE` | variáveis de ambiente, **somente** nos scripts de auditoria (`scripts/audit-evo-token.php`, `scripts/diagnose-evo-auth.php`) | execução local |

O plugin **não** lê nenhuma variável de ambiente, e não existem `EVO_API_KEY`, `EVO_PASSWORD`, `EVO_BRANCH` nem `EVO_CONFIG` no projeto.

### Credenciais usadas no teste (mascaradas)

| Item | Valor |
|---|---|
| DNS | `cont...orpo` (15 caracteres) |
| Token até 02/10 | `sk-s...eswA` (**167 caracteres**, prefixo `sk-`): **sempre HTTP 500** |
| Token em 09/10 | `4146...A39A` (**36 caracteres, formato GUID**, `A-Z 0-9 -`): **funciona** |
| Fonte | 30/09 e 02/10: variáveis de ambiente (`SecureString`). 09/10: digitado no prompt oculto do próprio script |

**Conclusão sobre o HTTP 500:** a causa era **a chave `sk-…`**. Com o mesmo DNS, a mesma base, os mesmos headers e o mesmo script, a chave GUID funciona na primeira chamada, inclusive **sem** `idBranch`. O defeito estava naquela chave do lado da EVO, não no nosso código nem na falta de `idBranch`.

⚠️ **Pendência de rastreabilidade:** é preciso confirmar com o cliente que a chave GUID é a chave criada para o site, com as permissões pedidas, e não uma chave pré-existente com outro perfil. As permissões validadas abaixo valem **para esta chave**.

---

## Diagnóstico do HTTP 500

### Fase 1: onde está o "JSON malformado"

**Nenhuma variável de ambiente é interpretada como JSON.** Não existe `json_decode`/`JSON.parse` sobre `EVO_DNS`, `EVO_TOKEN` nem `EVO_BASE`: os scripts leem os valores com `getenv()` e os usam como texto puro.

A linha `Probes de escrita: SIM (JSON malformado)` era apenas o **rótulo** do script. Ela avisava que os probes de POST/PATCH (etapa 9 da auditoria) enviariam de propósito o corpo `{`, para não criar nada. Esse corpo:

- só existe nas 6 últimas chamadas (POST/PATCH);
- não tem relação com as ~22 chamadas **GET** anteriores, que também deram 500 e não levam corpo nenhum.

O rótulo foi reescrito para não induzir a essa leitura: `SIM (corpo "{" invalido de proposito; nenhuma variavel e JSON)`.

| Pergunta | Resposta |
|---|---|
| Variável interpretada como JSON | nenhuma |
| Código que faz parse | nenhum sobre variáveis; `json_decode` só roda sobre a **resposta** da EVO |
| Hipótese 3 ("500 provocado pelo JSON das variáveis") | **descartada** |

### Fase 2: nosso backend ou a EVO?

**Não existe camada nossa entre o script e a EVO.** O `scripts/audit-evo-token.php` rodou **fora do WordPress** (`Fonte: variaveis de ambiente`): não passou por endpoint REST, proxy nem pelo `Contorno_Evo_Client`. Ele faz `curl` direto para `https://evo-integracao-api.w12app.com.br`.

| Etapa | O que aconteceu |
|---|---|
| A. frontend/script → nosso backend | **não existe**; o script é o cliente |
| B. processamento interno | montagem do header `Basic base64(DNS:TOKEN)` e da query string; nenhum parser |
| C. requisição real para a EVO | `GET https://evo-integracao-api.w12app.com.br/api/v1/configuration` com `Accept`, `culture: pt-BR` e `Authorization: Basic …` |
| D. resposta real da EVO | **HTTP 500**, em 110 a 300 ms, **corpo vazio** (nenhuma mensagem capturada) |

| | HTTP |
|---|---|
| **NOSSO ENDPOINT** | não participou |
| **EVO DIRETAMENTE** | **500** (o `curl` registrou o código recebido do host da EVO; `net_error` vazio) |

Hipóteses 1 e 5 (erro no nosso backend, ou num parser/normalizador nosso) ficam **descartadas para este teste**.

### Contraprova já obtida

A mesma rota, a mesma base e o mesmo script, com credencial **falsa** (`fake` / `fakefake…`):

```
GET /api/v1/configuration/api-usage     HTTP 401  {"status":401,"error":"Unauthorized"}
GET /api/v1/configuration/group-branches HTTP 401  {"status":401,"error":"Unauthorized"}
```

A EVO responde normalmente a uma credencial inválida (401 com JSON). Com a credencial **real** ela devolve **500 sem corpo em todas as rotas**, inclusive em rotas que não dependem de permissão específica, como `/api/v1/configuration/api-usage`. Um token falso com o mesmo prefixo `sk-` também recebe 401 (teste local de 30/09), então o prefixo sozinho não derruba a EVO.

**Conclusão parcial:** a EVO chegou a ser chamada, e o erro nasce **na EVO, ao processar esta credencial específica**, antes de qualquer lógica de endpoint. Uma chave sem permissão para a rota receberia 403, não 500.

### Hipóteses restantes (hipótese 4: credencial/formato)

| # | Hipótese | Como o diagnóstico separa |
|---|---|---|
| H1 | Token com espaço, aspas, quebra de linha ou `:` (o `:` quebraria o Basic `usuario:senha`) | `diagnose-evo-auth.php` mede o formato sem exibir o valor |
| H2 | DNS diferente do DNS dono da chave (ex.: DNS de filial × DNS da instância ADM Geral) | C3: DNS falso + token real. Se der 500, a EVO reconheceu o token antes de validar o DNS |
| H3 | Formato novo da chave (`sk-…`, 167 caracteres; a doc não publica formato) quebrando o autenticador da EVO | R1/R2 com a chave exata. Se 500 persistir com o formato limpo, é defeito do lado da EVO |
| H4 | Header `culture: pt-BR` | R2 envia só `Accept` + `Authorization` |
| H5 | Chave recém-criada, ainda não propagada, ou pausada por custo (`API_COST_LIMIT_REACHED` viria como 401) | repetir mais tarde; abrir chamado na EVO com os horários |

### Fase 3: captura do erro real

`scripts/diagnose-evo-auth.php` (somente GET, cerca de 8 chamadas) registra por chamada: método, URL sem segredo, headers enviados com `Authorization` **mascarado**, status, `Content-Type`, headers de resposta (`set-cookie` omitido), IP remoto, tempo e os primeiros 1.500 caracteres do corpo, com dígitos longos mascarados. Não há exceção nem stack trace nossos, porque não existe camada nossa no caminho.

Variantes:

| Rótulo | Credencial | Esperado se a hipótese se confirmar |
|---|---|---|
| C1 | sem `Authorization` | 401 (controle) |
| C2 | DNS real + token aleatório | 401 (controle) |
| C3 | DNS falso + token real | 500 → a EVO reconhece o token e quebra (H3); 401 → a EVO exige o par |
| R1 | DNS + token, igual ao plugin | reproduz o 500 |
| R2 | idem, sem `culture` | 200 → H4 |
| R3 | trim/aspas (só roda se o formato tiver sujeira) | 200 → H1 |
| R4 | DNS em minúsculas (só roda se houver maiúsculas) | 200 → divergência de DNS |
| R5 | `/api/v1/configuration/group-branches` | segundo endpoint seguro |

**Execução com credencial falsa (30/09/2026, validação do próprio script):** todas as variantes deram 401 `application/json`. O script está funcional e a detecção de formato acusou corretamente espaço final e aspas num token de teste.

**Execução com a credencial real (30/09/2026 17:49:57–17:50:05 UTC, `%TEMP%\evo-diag.json`):**

Formato das variáveis (Fase 4). Nenhuma sujeira:

| | EVO_DNS | EVO_TOKEN |
|---|---|---|
| tamanho / após trim | 15 / 15 | 167 / 167 |
| espaço no início/fim, CR/LF, espaço interno | não | não |
| aspas / `:` / JSON / não-ASCII | não / não / não / não | não / não / não / não |
| caracteres | `a-z` | `a-z A-Z 0-9 - _` |
| formato | DNS em minúsculas | prefixo `sk-`, 1 segmento, **não é GUID** |

Chamadas diretas à EVO (`104.18.36.101`, Cloudflare GRU):

| Variante | HTTP | Content-Type | Corpo | `request-context` (App Insights da EVO) | `cf-ray` |
|---|---:|---|---|---|---|
| C1 sem Authorization | **401** | application/json | `{"status":401,"error":"Unauthorized"}` + `www-authenticate: Basic realm="Evo-integracao"` | presente | a43511327b438138-GRU |
| C2 DNS real + token aleatório | **401** | application/json | idem | presente | a435113c1b61e1ea-GRU |
| C3 DNS falso + token real | **401** | application/json | idem | presente | a435114629f89d59-GRU |
| R1 DNS + token (igual ao plugin) | **500** | — | **vazio** (`content-length: 0`) | **ausente** | a43511501db9d985-GRU |
| R2 idem, sem `culture` | **500** | — | vazio | ausente | a435115a2a63d986-GRU |
| R5 group-branches | **500** | — | vazio | ausente | a43511642d07d985-GRU |

R3 e R4 não rodaram porque não havia sujeira nem maiúsculas para corrigir.

### Leitura dos resultados

| # | Hipótese | Resultado |
|---|---|---|
| H1 | token/DNS com espaço, aspas, quebra ou `:` | **descartada**: formato limpo |
| H2 | DNS errado para a chave | **descartada**: DNS errado gera 401 (C3), não 500 |
| H3 | o prefixo `sk-` sozinho derruba o autenticador | **descartada**: token real com DNS falso dá 401 limpo |
| H4 | header `culture` | **descartada**: R2 sem `culture` também dá 500 |
| H5 | chave pausada por custo | improvável: seria 401 `API_COST_LIMIT_REACHED` |
| **H6** | chave ADM Geral só funciona em chamada **com `idBranch`**. A doc diz: "chave criada em uma instância de ADM Geral com acesso **somente** a chamadas que possuem o parâmetro `idbranch`". Todas as chamadas até aqui foram **sem** `idBranch` | **NÃO SUFICIENTE** (18:12 UTC): com `idBranch=1` e `=8` a EVO também devolve 500 vazio. Ver "Teste H6" |

**O que está provado:**

1. **A credencial é reconhecida pela EVO.** Só o par exato DNS + token produz 500. Trocar qualquer um dos dois devolve 401. Se o token fosse inválido, a EVO responderia 401 como em C2/C3.
2. **O 500 nasce na EVO, depois de aceitar a credencial.** O `request-context: appId=cid-v1:…` (Application Insights da aplicação EVO) aparece em todos os 401 e **some** nos 500, que vêm sem `Content-Type` e com corpo vazio. A falha ocorre num estágio do servidor deles em que a resposta nem passa pelo pipeline normal (exceção não tratada logo após a autenticação), e não num endpoint específico. Não é página de erro do Cloudflare: essas têm HTML e códigos 52x.
3. Não é erro nosso, não é formato de variável, não é header e não é permissão (sem permissão seria 403).

### Correção aplicada

Nenhuma no código de integração. Não há defeito local comprovado. Mudanças feitas só nos scripts de auditoria:

- rótulo `Probes de escrita` reescrito;
- `body_len` registrado em cada evidência da auditoria;
- novo `scripts/diagnose-evo-auth.php`, agora com a fase H6 (mesmas rotas com `idBranch`, IDs via `EVO_DIAG_BRANCHES`, padrão `1,8`). Respostas 200 ficam registradas só como quantidade e nomes de campos.

### Próximos passos

1. **Rodar de novo `scripts/diagnose-evo-auth.php`** (≈ 16 GET). Resultado da fase H6:
   - **200 com `idBranch`** → causa raiz confirmada: chave ADM Geral chamada sem `idBranch`. Aí há impacto no plugin: `branches()` e `test()` chamam `/group-branches`, `/configuration` e `/membership` **sem** `idBranch`, então o "Testar conexão" e a descoberta de filiais quebram com esta chave. As filiais teriam de vir do cadastro do site (`evo_branch_id`), e toda chamada passaria a levar `idBranch`. A auditoria de permissões fica liberada, usando `EVO_AUDIT_BRANCHES`;
   - **403 com `idBranch`** → token válido, falta permissão: começa a análise de permissões;
   - **500 também com `idBranch`** → defeito do lado da EVO. Abrir chamado com os `cf-ray` acima e os horários UTC, informando que a chave `sk-s...eswA` do DNS `cont...orpo` recebe 500 sem corpo em qualquer GET, enquanto credenciais erradas recebem 401.
2. Até lá, **nenhum teste de permissão** e nenhum POST/PATCH, nem mesmo com corpo malformado.

---

## Teste H6 — ADM Geral com idBranch

**Status: EXECUTADO em 30/09/2026 18:12:16 UTC (`%TEMP%\evo-diag.json`, modo `h6`). H6 = NÃO SUFICIENTE / DESCARTADA como causa do 500.** Foram 4 chamadas; a regra de parada da fase 6 foi aplicada.

### IDs de filial encontrados e origem

| Fonte | O que contém | Situação |
|---|---|---|
| `wp-content/plugins/contorno-core/data/dataset.json` → `units[].fields.evo_branch_id` / `ctns[].fields.evo_branch_id` | **66 unidades** com ID (1–11, 13–67; **não existe 12**) + 2 CTNs (50 Buritis, 57 Castelo). Gerado em 19/09/2026 a partir do site React anterior | versionado no repositório |
| Mesmo arquivo, URLs de checkout da **própria EVO** (`checkout.contornodocorpo.com.br/contornodocorpo/<ID>/site/...`) | O `<ID>` da URL bate com `evo_branch_id` em **todas** as unidades que têm URL (0 divergências) | **confirmação independente**: é a EVO quem monta essa URL |
| Campo `evo_branch_id` do post (`contorno-core/includes/meta/registry.php`) + meta `_contorno_evo_sync_branch` (`contorno-evo-sync`, `class-mapping.php`) | cópia no banco de produção, editável no painel | **não consultado**: não há acesso ao banco daqui |

IDs usados no teste:

| idBranch | Unidade | Cidade | Evidência |
|---:|---|---|---|
| **1** | São Lucas (`sao-lucas`) | Belo Horizonte | `evo_branch_id: "1"` + checkout EVO `.../contornodocorpo/1/...` |
| **8** | Betim (`betim`) | Betim | `evo_branch_id: "8"` + checkout EVO `.../contornodocorpo/8/...` |

### Endpoints da rodada (somente GET; `scripts/diagnose-evo-auth.php`, modo `h6`)

| Etapa | Endpoint | Permissão |
|---|---|---|
| H6.0 contraprova | `/api/v1/configuration` **sem** idBranch; token aleatório **com** idBranch | — |
| H6.1 portão | `/api/v1/configuration?idBranch=1` e `=8` | Configuração |
| **Parada (fase 6)** | se as duas filiais derem 500, a rodada **encerra aqui** | — |
| H6.2 (por filial) | `/api/v1/configuration/group-branches?idBranch=`, `/api/v2/configuration/gateway?idBranch=` (**`tokenizeBackend`**, `gatewayType`, `showCardType`, `validationEnabled`, **nomes** das chaves de `gatewayData`) | Configuração |
| | `/api/v3/membership?idBranch=&take=50` + 1 item por `idMembership` | Contratos |
| | `/api/v1/activities?idBranch=`, `/api/v1/activities/schedule?idBranch=&date=hoje&showFullWeek=true` | Atividade |
| | `/api/v1/members/basic?idBranch=&email=<inexistente>` + `take=1` (só nomes de campos) | Cliente s/ sensíveis |
| | `/api/v1/prospects?idBranch=&email=<inexistente>` + `take=1` (só nomes de campos) | Prospects - Consulta |
| H6.2 (global) | `/api/v1/sales/by-session-id?sessionId=<inexistente>`, `/api/v2/states` (sem `idBranch` no swagger) | Vendas / Configuração |

Sem POST/PATCH. `POST /api/v1/prospects` e `POST /api/v2/sales` seguem **PENDENTE DE TESTE CONTROLADO**. `gateway-form-token` não é chamado porque pode emitir token.

### Resultado

| | `/api/v1/configuration` | `/api/v1/configuration/group-branches` |
|---|---|---|
| **Sem idBranch** (30/09 17:50 UTC) | **500**, corpo vazio (cf-ray `a43511501db9d985-GRU`) | **500**, corpo vazio (cf-ray `a43511642d07d985-GRU`) |
| **Com idBranch=1** | **500**, corpo vazio | não executado (parada) |
| **Com idBranch=8** | **500**, corpo vazio | não executado (parada) |

Execução completa da rodada (30/09/2026, UTC):

| Hora | Chamada | idBranch | HTTP | Content-Type | Corpo | `request-context` | cf-ray |
|---|---|---:|---:|---|---|---|---|
| 18:12:16 | H6.0a `GET /api/v1/configuration`, credencial real | — | **500** | — | vazio (0 bytes) | ausente | `a43531e38d156273-GRU` |
| 18:12:17 | H6.0b `GET /api/v1/configuration?idBranch=1`, **token aleatório** | 1 | **401** | application/json | `{"status":401,"error":"Unauthorized"}` | presente | `a43531ed5f7fd457-GRU` |
| 18:12:19 | H6.1 `GET /api/v1/configuration?idBranch=1`, credencial real | 1 | **500** | — | vazio (0 bytes) | ausente | `a43531f76b44bba0-GRU` |
| 18:12:20 | H6.1 `GET /api/v1/configuration?idBranch=8`, credencial real | 8 | **500** | — | vazio (0 bytes) | ausente | `a43532016fce255e-GRU` |

**Reteste em 02/10/2026 (mesma chave `sk-s...eswA`, mesmo DNS), resultado idêntico:**

| Hora (UTC) | Chamada | idBranch | HTTP | Corpo | `request-context` | cf-ray |
|---|---|---:|---:|---|---|---|
| 21:41:32 | `GET /api/v1/configuration`, credencial real | — | **500** | vazio | ausente | `a446df345dbc9f3d-GRU` |
| 21:41:34 | `GET /api/v1/configuration?idBranch=1`, token aleatório | 1 | **401** | `{"status":401,"error":"Unauthorized"}` | presente | `a446df3d7f7bf260-GRU` |
| 21:41:35 | `GET /api/v1/configuration?idBranch=1`, credencial real | 1 | **500** | vazio | ausente | `a446df4779f886c5-GRU` |
| 21:41:37 | `GET /api/v1/configuration?idBranch=8`, credencial real | 8 | **500** | vazio | ausente | `a446df5189ef5326-GRU` |

O problema persiste dois dias depois. Não é instabilidade momentânea.

Nenhum `traceparent` nem `x-request-id` veio na resposta. O único identificador rastreável é o `cf-ray`.

**Conclusão H6: NÃO SUFICIENTE.** Passar `idBranch` de filiais reais (1 e 8, confirmadas pelo checkout da própria EVO) não muda o comportamento. O comportamento é idêntico ao sem `idBranch`: 500 vazio, sem o `request-context` da aplicação EVO. Os demais endpoints não foram chamados, conforme a fase 6.

O mapa abaixo continua válido como preparação: **se** a EVO corrigir e a chave passar a exigir `idBranch`, os dois pontos de desenho se aplicam. Por ora, nada no plugin foi alterado.

### Dados para o chamado na EVO

| Campo | Valor |
|---|---|
| Base | `https://evo-integracao-api.w12app.com.br` (IP 104.18.36.101, Cloudflare POP GRU) |
| DNS | `cont...orpo` (15 caracteres, minúsculas) |
| Chave | `sk-s...eswA` (167 caracteres, formato `sk-…`), criada pelo cliente em **ADM Geral / multifilial** |
| Autenticação | `Authorization: Basic base64(DNS:chave)`, conforme a documentação |
| Endpoint | `GET /api/v1/configuration` (também `/configuration/group-branches`, `/configuration/api-usage`, `/api/v3/membership`, `/api/v2/states` e mais 22 rotas: todas 500 em 30/09 17:36 UTC) |
| idBranch | sem; `1`; `8` |
| Resposta | **HTTP 500**, `content-length: 0`, sem `Content-Type`, sem `request-context` |
| Horários (UTC) e cf-ray | 17:50:02 `a43511501db9d985-GRU` · 17:50:03 `a435115a2a63d986-GRU` (sem header `culture`) · 17:50:05 `a43511642d07d985-GRU` (group-branches) · 18:12:16 `a43531e38d156273-GRU` · 18:12:19 `a43531f76b44bba0-GRU` (idBranch=1) · 18:12:20 `a43532016fce255e-GRU` (idBranch=8) |
| Contraprova | sem Authorization → 401; DNS real + chave aleatória → 401 (também com idBranch=1); DNS falso + chave real → 401. Todos com JSON `{"status":401,"error":"Unauthorized"}` e `request-context` presente |
| Pergunta à EVO | "O par DNS + chave é reconhecido (só ele não dá 401), mas qualquer GET devolve 500 vazio antes de chegar à aplicação. A chave ADM Geral no formato `sk-` está ativa/provisionada? Há alguma configuração pendente na instância ADM Geral?" |

O chamado precisa normalmente ser aberto pelo **titular da conta EVO** (o cliente), então os dados acima são para ele encaminhar. Nenhum dado pessoal nem a chave completa estão incluídos.

### Mapa das chamadas EVO do plugin (preparação da fase 3, sem alterar código)

Classificação de `idBranch` pelo swagger oficial. "Exige" só poderá ser afirmado depois do teste.

| Método (`class-client.php`) | Endpoint | Envia idBranch hoje? | Swagger | Quem chama |
|---|---|---|---|---|
| `test()` | `GET /api/v3/membership?take=1` | **não** | aceita | botão "Testar conexão", `wp contorno evo test` |
| `branches()` | `GET /configuration/group-branches` → fallback `GET /configuration` | **não** | aceita | descoberta de filiais (admin, CLI) |
| `memberships(null)` | `GET /api/v3/membership` (global paginado) | **não** (modo `auto`/`global` do sync) | aceita | `class-sync.php:114` |
| `memberships($branch)` | `GET /api/v3/membership?idBranch=` | sim | aceita | sync por filial (`class-sync.php:140`) |
| `membership($id,$branch)` | `GET /api/v3/membership?idMembership=&idBranch=` | sim (quando conhecido) | aceita | preço na hora da venda |
| `gateway($branch)` | `GET /api/v2/configuration/gateway` | sim se `id_branch>0` | aceita | EVO Pay |
| `members_basic($filters)` | `GET /api/v1/members/basic` | **não, de propósito** | aceita | checkout: identificar cliente |
| `prospects($filters)` | `GET /api/v1/prospects` | **não, de propósito** | aceita | checkout: evitar prospect duplicado |
| `states()` | `GET /api/v2/states` | não | **sem parâmetro** | endereço |
| `activities($b)` | `GET /api/v1/activities?idBranch=` | sim | aceita | grade |
| `activities_schedule($b)` | `GET /api/v1/activities/schedule?idBranch=` | sim | aceita | grade |
| `sale_by_session()` | `GET /api/v1/sales/by-session-id` | não | **sem parâmetro** | reconciliação pós-venda |
| `sale($id)` | `GET /api/v2/sales/{id}` | não | **sem parâmetro** | comprovante |
| `create_prospect()` | `POST /api/v1/prospects` | no corpo (`idBranch`) | corpo | checkout |
| `create_sale()` | `POST /api/v2/sales` | no corpo (`idBranch`) | corpo | checkout |

Se a H6 for confirmada, há **dois pontos de desenho**:

1. **Descoberta de filiais e "Testar conexão"** (`branches()`, `test()`, sync global) quebram com a chave ADM Geral. As filiais teriam de vir do cadastro local (`evo_branch_id`, com a URL de checkout como conferência), e o teste de conexão passaria a usar uma filial.
2. **Deduplicação de pessoa na rede** (`class-checkout.php:317`): o checkout busca cliente/prospect **sem `idBranch` de propósito**, para achar a pessoa cadastrada em qualquer uma das 66 unidades. Com a exigência de `idBranch`, isso vira 66 consultas × até 3 filtros, sendo que o limite é 40 req/min. É uma **decisão de produto** (buscar só na unidade da matrícula? consultar a EVO sobre busca de rede?), não um ajuste mecânico.

Além disso, `sales/by-session-id`, `sales/{id}` e `states` **não têm** `idBranch` no swagger. Se a chave ADM Geral quebrar nelas, a reconciliação anti-cobrança-dupla do checkout fica sem caminho. A rodada H6 testa `by-session-id` e `states` exatamente por isso.

---

## Rodadas de 09/10/2026 — chave GUID (somente GET)

| Rodada | Evidência | Chamadas | Situação |
|---|---|---:|---|
| 18:37:31 UTC | `%TEMP%\evo-diag-20261009-1837.json` | 24 | Prospects e `sales/by-session-id` → **403** |
| *(cliente libera as permissões)* | | | |
| **18:46:05 UTC** | `%TEMP%\evo-diag-20261009-1846.json` | 26 | **tudo 200**, exceto `members/basic` sem filtro (404 esperado) |

Rodada das 18:46:

| Chamada | idBranch | HTTP | Resultado | cf-ray |
|---|---:|---:|---|---|
| `GET /api/v1/configuration` (sem idBranch) | — | **200** | 72 filiais | `a47f8bd3afea1cfb-GRU` |
| `GET /api/v1/configuration`, token aleatório (controle) | 1 | 401 | `{"status":401,"error":"Unauthorized"}` | `a47f8bdb2e4c64c4-GRU` |
| `GET /api/v1/configuration` | 1 / 8 | **200** | `SÃO LUCAS` / `BETIM` | `a47f8be55da87254-GRU` · `a47f8bef7edbf1f7-GRU` |
| `GET /api/v1/configuration/group-branches` | 1 / 8 | **200** | 8 grupos de filiais | `a47f8bf96819afcb-GRU` |
| `GET /api/v2/configuration/gateway` | 1 / 8 | **200** | `gatewayType 9`, `tokenizeBackend false` | `a47f8c038a9f0b84-GRU` |
| `GET /api/v3/membership?take=50` | 1 | **200** | envelope `qtde: 447`, página com 50 planos, todos `idBranch: 1` | `a47f8c0d6952d824-GRU` |
| `GET /api/v3/membership?take=50` | 8 | **200** | envelope `qtde: 194`, página com 50 planos, todos `idBranch: 8` | `a47f8c71fd40f254-GRU` |
| `GET /api/v3/membership?idMembership=39` / `=46` | 1 / 8 | **200** | 1 plano cada (consulta de item) | `a47f8c178c8d4568-GRU` · `a47f8c7bdb2a51ed-GRU` |
| `GET /api/v1/activities` | 1 / 8 | **200** | 0 / 8 atividades | `a47f8c219ce5f1d9-GRU` · `a47f8c85efa9da79-GRU` |
| `GET /api/v1/activities/schedule?showFullWeek=true` | 1 / 8 | **200** | 0 / **26 sessões** | `a47f8c2bbcb861e0-GRU` · `a47f8c8ffb755d2d-GRU` |
| `GET /api/v1/members/basic?email=<inexistente>` | 1 / 8 | **200** | `[]` | `a47f8c35da997a2f-GRU` · `a47f8c99fb4a1f6a-GRU` |
| `GET /api/v1/members/basic` (sem filtro) | 1 / 8 | 404 | a rota exige filtro; não é falta de permissão | `a47f8c3fdbfdf252-GRU` |
| `GET /api/v1/prospects?email=<inexistente>` | 1 / 8 | **200** | `[]` (antes: 403) | `a47f8c49c9e91da1-GRU` · `a47f8cae1d0e619d-GRU` |
| `GET /api/v1/prospects?take=1` | 1 / 8 | **200** | 1 registro; só os **nomes** dos campos foram registrados | `a47f8c53dbed181d-GRU` · `a47f8cb8082cb214-GRU` |
| `GET /api/v1/sales/by-session-id?sessionId=<inexistente>` | — | **200** | objeto `{idVenda, idCliente, clienteContratos}` (antes: 403) | `a47f8cc22c431fb4-GRU` |
| `GET /api/v2/states` | — | **200** | 27 UFs | `a47f8ccc389ef1c7-GRU` |

### Planos: o que veio de fato

- **Formato:** a EVO devolve `{ qtde, lista, list, ids, informacoesIndicados, idUltimaConciliacao }`. Os planos ficam em **`list`** (`lista` vem vazio); `qtde` é o total da consulta. O swagger documenta um array simples de planos.
- **Volume:** 447 planos cadastrados na filial 1 e 194 na filial 8 (muitos históricos e inativos). Na primeira página de 50: filial 1 → 3 ativos, todos com venda online; filial 8 → 4 ativos, 3 com venda online.
- **Campos disponíveis:** todos os necessários ao site estão presentes: `idMembership`, `idBranch`, `nameMembership`/`displayName`, `value`, `durationType`/`duration`, `membershipType` ("Recorrência mensal", "Contrato comum"…), `maxAmountInstallments`, promoção (`typePromotionalPeriod`, `valuePromotionalPeriod`, `monthsPromotionalPeriod`), `inactive`, `externalSaleAvailable`, `urlSale`, `additionalService`, `description`, `differentials`, `accessBranches`, regras de cancelamento/suspensão.
- **Multifilial real:** nenhum `idMembership` se repete entre as filiais 1 e 8, e o preço do mesmo nome de plano difere (ex.: "PREMIUM" R$ 199,90 em São Lucas × R$ 169,90 em Betim).

### Defeitos do NOSSO plugin revelados pelas respostas reais (não é problema do cliente)

| # | Onde | O que o código espera | O que a EVO devolve | Efeito | Gravidade |
|---|---|---|---|---|---|
| D1 | `Contorno_Evo_Client::memberships()`, `membership()`, `test()` | array de planos | envelope com os planos em `list` | `array_filter($data, 'is_array')` pega `lista`/`list` como se fossem planos; o sync não grava plano nenhum e a paginação para na 1ª página | **alta**: o sync de planos não funciona |
| D2 | `Contorno_Evo_Client::sale_by_session()` (`class-client.php:437`) | `idSale` | **`idVenda`** (e `idCliente`, `clienteContratos`) | o id da venda sai sempre 0. Depois de um timeout no `POST /sales`, uma venda **criada** seria tratada como "não existe", e a pessoa poderia pagar de novo | **crítica**: risco de cobrança duplicada |

Os dois são correções nossas, de leitura de resposta. Nenhum depende do cliente nem da EVO.

**Status (09/10/2026): CORRIGIDOS no código, ainda sem commit e sem deploy.**

| | Correção |
|---|---|
| D1 | `Contorno_Evo_Client::rows()` lê o envelope (planos em `list`; `lista` como alternativa; `qtde` como total) e continua aceitando o array documentado. Usado em `memberships()`, `membership()` e `test()`. A paginação para quando alcança `qtde`, sem chamada extra |
| D2 | `Contorno_Evo_Client::sale_id()` aceita `idVenda`, `idSale`, `idSaleResult`, inteiro ou string numérica. Usado em `sale_by_session()` e na leitura da resposta de `POST /sales` (`Contorno_Evo_Checkout_Rest::succeed()`) |

**Testes** (`scripts/test-evo-native-checkout.sh`, com fixtures no formato **real** observado em 09/10):

- código novo: **160 OK, 0 falhas**, mais 30 verificações estáticas OK. Foram 19 testes novos na seção "Formato real da EVO".
- mesmos cenários contra o **código antigo**: **7 falhas**, entre elas "sync com envelope traz… → **2 planos**" (as chaves do envelope lidas como planos) e "repetir o pagamento NÃO gera segunda venda → **2 POST /sales**" (a cobrança dupla do D2). Isso prova que os testes detectam os dois defeitos.

---

## 4. Matriz das permissões

| Permissão solicitada | Finalidade | Endpoint validado | Método | HTTP | Unidade | Resultado |
|---|---|---|---|---:|---|---|
| Configuração - Consulta | filiais e configuração | `/api/v1/configuration`, `/configuration/group-branches`, `/api/v2/configuration/gateway`, `/api/v2/states` | GET | 200 | 1, 8 e global | **PASS** |
| Contratos - Consulta | planos | `/api/v3/membership?idBranch=` (lista e item) | GET | 200 | 1, 8 | **PASS** (formato em envelope → ajuste D1) |
| Atividade - Consulta | atividades e grade | `/api/v1/activities`, `/api/v1/activities/schedule` | GET | 200 | 8 (dados), 1 (vazio) | **PASS**: grade por unidade com data, horário, sala e vagas |
| Prospects - Consulta | buscar prospect | `/api/v1/prospects?idBranch=&email=` | GET | 200 | 1, 8 | **PASS** (403 → 200 após liberação) |
| Prospects - Edição | criar/atualizar prospect | `POST /api/v1/prospects` | POST | — | — | **PENDENTE DE TESTE CONTROLADO** |
| Cliente - Consulta s/ sensíveis | identificar cliente | `/api/v1/members/basic?idBranch=&email=` | GET | 200 | 1, 8 | **PASS** |
| Vendas - Edição | criar venda | `POST /api/v2/sales` | POST | — | — | **PENDENTE DE TESTE CONTROLADO** |
| Vendas - Consulta (adicional) | reconciliação anti-cobrança-dupla | `/api/v1/sales/by-session-id` | GET | 200 | — | **PASS** (403 → 200 após liberação; leitura com defeito D2) |

| Endpoint | Existe no código | Existe na documentação | Token autorizou | Chamada real funcionou |
|---|---|---|---|---|
| `GET /api/v1/configuration` | sim | sim | sim | sim |
| `GET /api/v1/configuration/group-branches` | sim | sim | sim | sim |
| `GET /api/v2/configuration/gateway` | sim | sim | sim | sim |
| `GET /api/v2/states` | sim | sim | sim | sim |
| `GET /api/v3/membership` | sim | sim | sim | sim (formato ≠ swagger) |
| `GET /api/v1/activities` | sim | sim | sim | sim |
| `GET /api/v1/activities/schedule` | sim | sim | sim | sim |
| `GET /api/v1/members/basic` | sim | sim | sim | sim |
| `GET /api/v1/prospects` | sim | sim | sim | sim |
| `GET /api/v1/sales/by-session-id` | sim | sim | sim | sim (campo ≠ código) |
| `POST /api/v1/prospects` | sim | sim | não testado | — |
| `POST /api/v2/sales` | sim | sim | não testado | — |

## 5. Testes por unidade

| | Filial 1 — São Lucas (BH) | Filial 8 — Betim |
|---|---|---|
| Configuração | 200 | 200 |
| Gateway | tipo 9, `tokenizeBackend: false` | idem |
| Planos | 447 cadastrados, 3 ativos na 1ª página | 194 cadastrados, 4 ativos na 1ª página |
| Atividades / grade | 0 / 0 | 8 / 26 sessões |
| Cliente (busca) | 200 | 200 |
| Prospect (busca) | 200 | 200 |

## 6. Evidências das chamadas

- Chave `sk-…`: 30/09 17:36, 17:49, 18:12 e 02/10 21:41 UTC, sempre 500 (seção "Teste H6").
- Chave GUID: 09/10 18:37 UTC (`evo-diag-20261009-1837.json`, com 403 em prospects/vendas) e **18:46 UTC (`evo-diag-20261009-1846.json`, tudo liberado)**.

## 7. EVO Pay

Auditoria de código: o número do cartão e o CVV **não** chegam ao backend; nada de cartão em log, banco ou analytics.

Gateway real (`GET /api/v2/configuration/gateway`, filiais 1 e 8): `gatewayType 9`, `tokenizeBackend: false`, `showCardType: false`, `validationEnabled: false`. Chaves de `gatewayData`: `keyRecorrencia`, `publicKey`, `keyVindi`, `versaoFrameworkVindi`, `idVindi`, `origemFranquia`, `keyRecorrenciaFranquia`, `publicKeyFranquia`, `gerarFormToken`, `antifraude`, `idW12` (valores não registrados).

O cartão é tokenizado no navegador (coerente com o desenho), mas via **Vindi / form token** (`GET /api/v1/configuration/gateway-form-token`), não pelo componente `<evo-cartao>` previsto no código. **NÃO HOMOLOGADO**: redesenhar a captura de cartão. Continua pendente o código `payment` de cartão.

## 8. Permissões excessivas

Não avaliado (fora do escopo das rodadas de 09/10). Os GETs de controle estão em `scripts/audit-evo-token.php`.

## 9. Bloqueios encontrados

1. ~~Chave `sk-…` com HTTP 500~~: resolvido com a chave GUID.
2. ~~Prospects - Consulta 403~~: resolvido pelo cliente em 09/10.
3. ~~`sales/by-session-id` 403~~: resolvido pelo cliente em 09/10.
4. ~~**D1**: o plugin não lê o envelope de planos~~: corrigido no código em 09/10 (sem deploy).
5. ~~**D2**: o plugin lê `idSale` em vez de `idVenda` na reconciliação~~: corrigido no código em 09/10 (sem deploy).
6. EVO Pay: a captura de cartão não corresponde ao gateway Vindi.

## 10. Ajustes necessários

**Cliente:** nada pendente de permissão. Resta só confirmar que a chave GUID `4146...A39A` é a chave do site e que **Vendas - Edição** e **Prospects - Edição** estão marcadas nela. Isso será comprovado no teste controlado.

**Nós** (aguardando aprovação; nada alterado ainda):
1. ~~Corrigir **D2**~~: feito (código + testes), falta commit/deploy.
2. ~~Corrigir **D1**~~: feito (código + testes), falta commit/deploy. Depois do deploy, rodar um sync em modo `--dry-run` para conferir os planos reais.
3. Teste controlado de `POST /api/v1/prospects`: 1 prospect de teste identificado como tal, na filial combinada com o cliente.
4. Teste controlado de `POST /api/v2/sales`: só depois de D2 e do cartão Vindi, com venda de valor mínimo/cancelável combinada com o cliente.
5. Redesenhar o cartão sobre `gateway-form-token` / Vindi.
6. Gravar a chave GUID no plugin em produção.

## 11. Conclusão

**O token está aprovado para todas as consultas de que o site precisa**, em modo multifilial: configuração, planos, atividades e grade por unidade, cliente, prospect e reconciliação de vendas. Não há mais nada a pedir ao cliente em termos de permissão de leitura.

**Ainda não liberamos o fluxo completo de matrícula** por motivos do nosso lado: D1 e D2 já estão corrigidos no código (falta publicar), e falta a captura de cartão Vindi. Os dois testes controlados de escrita vêm depois disso.
