# Relatório Técnico — Sistema de Gestão de Comandas

> **RASCUNHO para revisão.** Este texto foi preparado como ponto de partida. Reescreva-o com as suas palavras,
> confira cada afirmação contra o código e remova o que você não souber explicar. O edital da A3 prevê nota zero
> se o trabalho for identificado como integralmente produzido por IA; o que importa é que você domine o conteúdo.
> Os trechos marcados com **[AJUSTAR]** dependem de informações do grupo.

**Unidade Curricular:** Banco de Dados — 2026/2 · **Avaliação:** A3
**Integrantes:** [AJUSTAR: nomes e RA] · **Professor:** [AJUSTAR]
**Entrega:** 28/11/2026 · **Apresentação:** 01/12/2026

---

## 1. Introdução e contexto

Bares e restaurantes registram o consumo dos clientes em **comandas**: uma conta aberta quando o cliente chega,
acrescida de itens ao longo do atendimento e encerrada com o pagamento. Quando esse controle é feito em papel ou em
planilhas, surgem erros de soma, perda de comandas, dificuldade de cobrar valores em aberto ("fiado") e ausência de
relatórios confiáveis sobre vendas.

Este projeto propõe um banco de dados relacional e uma API REST para um sistema de gestão de comandas fictício, além de um
protótipo web que simula o uso da API. O objetivo é aplicar os conceitos da UC de Banco de Dados: modelagem conceitual
e lógica, normalização, SQL (DDL, DML e DQL), integridade referencial, transações e acesso ao banco por meio de uma aplicação.

**Escopo.** Cadastro de usuários (administradores e atendentes), clientes, categorias e produtos; abertura de comandas;
lançamento de itens; pagamentos (inclusive divididos entre formas de pagamento); comandas em fiado; cancelamento;
e relatórios gerenciais (vendas por período, produtos mais vendidos, faturamento por categoria e por mês, recebimentos por forma de
pagamento, desempenho dos atendentes e saldo devedor).

**Fora do escopo.** Upload de imagens (o produto guarda apenas o caminho da imagem), emissão fiscal, controle de estoque e
integração com meios de pagamento reais.

**Tecnologias.** MySQL/MariaDB (InnoDB), PHP 8.1+ (PDO), JavaScript puro, HTML e CSS, executados no XAMPP.

## 2. Requisitos e regras de negócio

### 2.1 Requisitos funcionais

| Código | Requisito |
|---|---|
| RF01 | Autenticar usuários por e-mail e senha e distinguir os perfis administrador e atendente. |
| RF02 | Manter (criar, consultar, alterar, excluir) usuários, categorias, produtos, clientes, comandas, itens e pagamentos. |
| RF03 | Abrir uma comanda para um cliente, associada ao atendente que a abriu. |
| RF04 | Lançar, alterar e remover itens de uma comanda aberta. |
| RF05 | Registrar um ou mais pagamentos por comanda e fechá-la quando o total for coberto. |
| RF06 | Marcar uma comanda como fiado e acompanhar o saldo devedor. |
| RF07 | Cancelar uma comanda que não tenha pagamentos. |
| RF08 | Emitir relatórios consolidados, inclusive por período. |

### 2.2 Requisitos não funcionais

- Integridade dos dados garantida pelo banco (chaves, `CHECK`, `ENUM`, `UNIQUE`), e não apenas pela aplicação.
- Senhas armazenadas com hash (bcrypt); autenticação da API por token JWT.
- Acesso ao banco somente por *prepared statements* (prevenção de SQL Injection).
- Código organizado, comentado e executável em um XAMPP padrão.

### 2.3 Regras de negócio

| Código | Regra | Onde é garantida |
|---|---|---|
| RN01 | O total da comanda é a soma de `quantidade × valor_unitario` dos itens; não é digitado. | View `vw_resumo_comandas` |
| RN02 | O item guarda o preço praticado no momento da venda. | Coluna `itens_comanda.valor_unitario` |
| RN03 | Só é possível alterar itens de comanda **aberta**. | API (409) |
| RN04 | O pagamento não pode exceder o saldo da comanda. | API (422) |
| RN05 | Ao cobrir o total, a comanda é fechada automaticamente. | API (transação) |
| RN06 | Comanda com pagamentos não pode ser cancelada nem excluída. | API (409) e `ON DELETE RESTRICT` |
| RN07 | Produto com histórico de vendas não pode ser excluído; usa-se `ativo = 0`. Produto inativo não é vendido. | `ON DELETE RESTRICT` e API |
| RN08 | O atendente da comanda é o usuário autenticado. | API |
| RN09 | Atendentes não gerenciam usuários, categorias e produtos, nem excluem registros. | API (403) |
| RN10 | Não é possível excluir o último administrador, nem a própria conta. | API (409) |
| RN11 | Usuário inativo não consegue entrar no sistema. | API (401) |

## 3. Modelagem

### 3.1 Modelo conceitual (DER)

Figura 1 — `dev/db/DER_conceitual.png` (código-fonte em `DER_conceitual.mmd`). **[AJUSTAR: gerar novamente o PNG a
partir do `.mmd` atualizado, por exemplo em https://mermaid.live, antes de inserir na versão final.]**

Entidades: **USUARIO**, **CLIENTE**, **CATEGORIA**, **PRODUTO**, **COMANDA**, **ITEM_COMANDA** e **PAGAMENTO**.

| Relacionamento | Cardinalidade | Leitura |
|---|---|---|
| CATEGORIA — PRODUTO | 1 : N | Uma categoria classifica vários produtos; cada produto pertence a uma categoria. |
| CLIENTE — COMANDA | 1 : N | Um cliente pode ter várias comandas (ou nenhuma); cada comanda é de um cliente. |
| USUARIO — COMANDA | 1 : N | Um atendente abre várias comandas; cada comanda tem um atendente responsável. |
| COMANDA — ITEM_COMANDA | 1 : N (mínimo 1 item ao ser usada) | Uma comanda contém vários itens. |
| PRODUTO — ITEM_COMANDA | 1 : N | Um produto pode aparecer em muitos itens. |
| COMANDA — PAGAMENTO | 1 : N | Uma comanda pode ser quitada por vários pagamentos (pagamento dividido). |
| USUARIO — PAGAMENTO | 1 : N | O usuário que recebeu o pagamento fica registrado. |

A relação **N:N entre COMANDA e PRODUTO** (uma comanda tem vários produtos e um produto está em várias comandas)
é resolvida pela entidade associativa **ITEM_COMANDA**, que ainda carrega atributos próprios da relação:
`quantidade` e `valor_unitario`.

### 3.2 Modelo lógico

Figura 2 — `dev/db/DER_logico.png` (código-fonte em `DER_logico.mmd`).

```
usuarios      (id PK, nome, email UK, senha_hash, perfil, status_conta, criado_em)
categorias    (id PK, nome UK)
produtos      (id PK, categoria_id FK→categorias, nome, valor, imagem, ativo, criado_em)
clientes      (id PK, nome, telefone, criado_em)
comandas      (id PK, cliente_id FK→clientes, usuario_id FK→usuarios, status, aberta_em, fechada_em)
itens_comanda (id PK, comanda_id FK→comandas, produto_id FK→produtos, quantidade, valor_unitario)
pagamentos    (id PK, comanda_id FK→comandas, usuario_id FK→usuarios, metodo, valor, pago_em)
```

### 3.3 Escolhas de modelagem justificadas

- **Chave primária substituta (`id` inteiro autoincremental)** em todas as tabelas: simples, estável e eficiente
  para chaves estrangeiras. Em `itens_comanda` também se poderia usar a chave composta (`comanda_id`, `produto_id`), mas isso
  impediria lançar o mesmo produto duas vezes com preços diferentes; a chave substituta é mais flexível.
- **`status` como `ENUM`** (`aberta`, `fechada`, `fiado`, `cancelada`): domínio pequeno e fechado, validado pelo banco.
  O mesmo vale para `perfil`, `status_conta` e `metodo`. A desvantagem é que incluir um valor exige um `ALTER TABLE`.
- **Regras de valor em `CHECK`**: `produtos.valor >= 0`, `itens_comanda.quantidade > 0`, `pagamentos.valor > 0`.
- **Comportamento das chaves estrangeiras (`ON DELETE`)**:
  `RESTRICT` onde apagar o pai destruiria histórico (produto, categoria, cliente, usuário, comanda com pagamentos);
  `CASCADE` apenas de `comandas` para `itens_comanda`, porque os itens não têm sentido sem a comanda.
  `ON UPDATE CASCADE` em todas, para propagar eventual mudança de chave.
- **Total da comanda não é coluna**: é calculado pela view `vw_resumo_comandas`, evitando que um total armazenado fique
  inconsistente com os itens.
- **`fechada_em` aceita `NULL`**: comandas abertas ainda não têm data de encerramento.
- **Inativação em vez de exclusão** (`produtos.ativo`, `usuarios.status_conta`): preserva o histórico de vendas.

## 4. Dicionário de dados

**usuarios** — quem usa o sistema.

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK, auto incremento | Identificador |
| nome | VARCHAR(100) | NOT NULL | Nome do usuário |
| email | VARCHAR(255) | NOT NULL, UNIQUE | Login |
| senha_hash | VARCHAR(255) | NOT NULL | Hash bcrypt da senha |
| perfil | ENUM('admin','atendente') | NOT NULL, padrão `atendente` | Permissões |
| status_conta | ENUM('ativo','inativo') | NOT NULL, padrão `ativo` | Inativo não faz login |
| criado_em | DATETIME | NOT NULL, padrão agora | Cadastro |

**categorias**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK | Identificador |
| nome | VARCHAR(50) | NOT NULL, UNIQUE | Nome da categoria |

**produtos**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK | Identificador |
| categoria_id | INT UNSIGNED | FK → categorias, NOT NULL | Categoria |
| nome | VARCHAR(100) | NOT NULL | Nome do produto |
| valor | DECIMAL(10,2) | NOT NULL, CHECK ≥ 0 | Preço atual |
| imagem | VARCHAR(255) | NOT NULL, padrão `assets/default.png` | Caminho da imagem |
| ativo | TINYINT(1) | NOT NULL, padrão 1 | 1 = disponível para venda |
| criado_em | DATETIME | NOT NULL | Cadastro |

**clientes**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK | Identificador |
| nome | VARCHAR(100) | NOT NULL | Nome |
| telefone | VARCHAR(20) | NULL | Contato (opcional) |
| criado_em | DATETIME | NOT NULL | Cadastro |

**comandas**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK | Identificador |
| cliente_id | INT UNSIGNED | FK → clientes, NOT NULL | Cliente |
| usuario_id | INT UNSIGNED | FK → usuarios, NOT NULL | Atendente que abriu |
| status | ENUM('aberta','fechada','fiado','cancelada') | NOT NULL, padrão `aberta` | Situação |
| aberta_em | DATETIME | NOT NULL, padrão agora | Abertura |
| fechada_em | DATETIME | NULL | Encerramento (fechada ou cancelada) |

**itens_comanda**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK | Identificador |
| comanda_id | INT UNSIGNED | FK → comandas (CASCADE), NOT NULL | Comanda |
| produto_id | INT UNSIGNED | FK → produtos (RESTRICT), NOT NULL | Produto vendido |
| quantidade | INT UNSIGNED | NOT NULL, CHECK > 0 | Quantidade |
| valor_unitario | DECIMAL(10,2) | NOT NULL, CHECK ≥ 0 | Preço praticado na venda |

**pagamentos**

| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK | Identificador |
| comanda_id | INT UNSIGNED | FK → comandas (RESTRICT), NOT NULL | Comanda paga |
| usuario_id | INT UNSIGNED | FK → usuarios (RESTRICT), NOT NULL | Quem recebeu |
| metodo | ENUM('pix','dinheiro','cartao_credito','cartao_debito') | NOT NULL | Forma de pagamento |
| valor | DECIMAL(10,2) | NOT NULL, CHECK > 0 | Valor pago |
| pago_em | DATETIME | NOT NULL, padrão agora | Data do pagamento |

**View `vw_resumo_comandas`** — para cada comanda: `comanda_id`, `status`, `aberta_em`, `total` (soma dos itens) e `pago` (soma dos pagamentos).

**Índices.** Além das chaves primárias e únicas: `idx_comandas_status_data (status, aberta_em)` (filtros por situação e período),
`idx_produtos_nome (nome)` (busca por nome) e `idx_pagamentos_pago_em (pago_em)` (relatórios por período).
Não foi criado índice em `pagamentos(comanda_id)` nem em `itens_comanda(comanda_id)`: o InnoDB já cria automaticamente um índice
para cada chave estrangeira, e um segundo índice seria redundante.

## 5. Normalização

**1ª Forma Normal (1FN).** Todos os atributos são atômicos e não há grupos repetitivos. Os vários itens de uma comanda não
estão em uma coluna "lista de produtos", e sim em linhas da tabela `itens_comanda`. O telefone é um único valor por cliente.

**2ª Forma Normal (2FN).** Todas as tabelas têm chave primária simples (`id`); logo, não há dependência parcial de
parte de uma chave composta. Mesmo em `itens_comanda`, a chave é o `id`; `quantidade` e `valor_unitario` dependem do item como um todo.

**3ª Forma Normal (3FN).** Não há dependências transitivas entre atributos não chave. O nome da categoria está só em
`categorias` (e não repetido em `produtos`); nome e telefone do cliente estão só em `clientes`; nome do atendente só em
`usuarios`. Cada fato é armazenado em um único lugar e consultado por meio de `JOIN`.

**Desnormalização intencional: `itens_comanda.valor_unitario`.** O preço do produto já existe em `produtos.valor`, então, em
rigor, o valor unitário seria redundante. Porém, o preço de um produto muda com o tempo; se o item consultasse sempre
`produtos.valor`, um reajuste alteraria retroativamente o total de comandas já fechadas e falsearia os relatórios de faturamento.
Trata-se de um dado **histórico** (o preço praticado naquele momento), não de uma cópia do preço atual; por isso
é um atributo legítimo do item. No script, o `UPDATE produtos SET valor = 6.00 WHERE id = 1` demonstra isso: as comandas
antigas continuam com R$ 5,50. Do mesmo modo, o total da comanda **não** é armazenado: é derivado dos itens pela view, para evitar inconsistência.

## 6. Script SQL (`dev/db/sistema_gestao_a3.sql`)

O script é executável do início ao fim e pode ser rodado várias vezes: ele cria o banco (se não existir) e recria suas tabelas.

### 6.1 DDL

- `CREATE DATABASE` / `CREATE TABLE` das sete tabelas, com tipos adequados (`DECIMAL` para dinheiro, `DATETIME` para datas, `ENUM`
  para domínios fechados), `PRIMARY KEY`, `UNIQUE`, `NOT NULL`, `DEFAULT` e `CHECK`.
- `ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY` para as relações, com `ON DELETE` e `ON UPDATE` explícitos.
- `ALTER TABLE ... ADD INDEX` para os índices de apoio e `CREATE VIEW vw_resumo_comandas`.

### 6.2 DML

- `INSERT`: carga fictícia de 7 categorias, 19 produtos, 8 clientes, 4 usuários, 15 comandas (de setembro a novembro de 2026),
  30 itens e 13 pagamentos. Há comandas em todas as situações (aberta, fechada, fiado, cancelada) e pagamentos divididos.
- `UPDATE`: reajuste de preço de um produto, inativação de um produto e correção do telefone de um cliente.
- `DELETE`: exclusão de um cliente duplicado cadastrado por engano.

### 6.3 DQL (consultas)

| Consulta | Objetivo | Técnicas |
|---|---|---|
| Q1 | Comandas com cliente, atendente e total | `JOIN` de 4 tabelas, `GROUP BY`, `SUM` |
| Q2 | Comandas da 1ª quinzena de outubro/2026 | filtro por período (`BETWEEN`) |
| Q3 | Produtos mais vendidos | `JOIN` de 4 tabelas, `ORDER BY`, `LIMIT` |
| Q4 | Faturamento por categoria | `JOIN`, agregação |
| Q5 | Recebimentos por forma de pagamento | `COUNT`, `SUM` |
| Q6 | Desempenho dos atendentes (inclui quem não vendeu) | `LEFT JOIN` e uso da view |
| Q7 | Saldo devedor das comandas em fiado | view + `JOIN` |
| Q8 | Produtos nunca vendidos | `LEFT JOIN ... IS NULL` |
| Q9 | Clientes que gastaram acima da média | `HAVING` com subconsulta |
| Q10 | Faturamento por mês | `GROUP BY` + `HAVING` |
| Q11 | Ranking de clientes | subconsulta na cláusula `FROM`, subconsulta escalar e `RANK()` |
| Q12 | Situação financeira por status | consulta sobre a view |

## 7. API REST

Código em `dev/api/v1/`. Uma única classe genérica (`Resource`) implementa o ciclo `GET` / `POST` / `PUT` / `DELETE`,
validação, paginação, filtros e ordenação; cada tabela é uma subclasse que declara apenas seus campos, filtros, permissões e
regras de negócio. Os relatórios ficam em `Relatorios.php`.

### 7.1 Rotas

| Rota | Métodos | Observação |
|---|---|---|
| `/auth/login` | POST | devolve o token JWT |
| `/auth/me` | GET | usuário do token |
| `/` | GET | documentação (campos, filtros, permissões e relatórios) |
| `/usuarios`, `/categorias`, `/produtos`, `/clientes`, `/comandas`, `/itens_comanda`, `/pagamentos` | GET, POST | listar (paginado, filtros, ordenação) e criar |
| `/{recurso}/{id}` | GET, PUT, DELETE | consultar, substituir e excluir |
| `/relatorios/{nome}` | GET | 7 relatórios somente leitura |

### 7.2 Permissões

| Recurso | Listar/ver | Criar | Alterar | Excluir |
|---|---|---|---|---|
| usuarios | admin | admin | admin | admin |
| categorias, produtos | todos | admin | admin | admin |
| clientes | todos | todos | todos | admin |
| comandas | todos | todos | todos | admin |
| itens_comanda | todos | todos | todos | todos |
| pagamentos | todos | todos | admin | admin |
| relatorios | todos | — | — | — |

### 7.3 Códigos HTTP

200 (sucesso), 201 (criado), 204 (excluído, sem corpo), 400 (JSON inválido), 401 (sem token/token inválido/credenciais erradas),
403 (sem permissão), 404 (não encontrado), 405 (método não permitido), 409 (regra de negócio ou vínculo), 422 (validação) e 500 (erro interno,
sem detalhes ao cliente). Os erros de integridade do MySQL são traduzidos: 1062 → 409, 1451 → 409, 1452 → 422, violação de `CHECK` → 422.

### 7.4 Autenticação com JWT

`POST /auth/login` confere a senha com `password_verify` (hash bcrypt) e devolve um token JWT assinado (HS256) com o `id` do usuário e
validade de 8 horas. As demais rotas exigem `Authorization: Bearer <token>`. A cada requisição o usuário é reconsultado no banco, de modo
que uma conta inativada perde o acesso imediatamente. O segredo de assinatura vem da variável de ambiente `A3_JWT_SECRET` ou de um arquivo
local gerado automaticamente; o CORS é restrito a `localhost` e o `.htaccess` bloqueia o acesso web a `config.php` e a `src/`.

### 7.5 Transações

Cada `POST`, `PUT` e `DELETE` roda em uma transação (`BEGIN`/`COMMIT`/`ROLLBACK`). Por exemplo, ao registrar um pagamento
que cobre o saldo, a inserção do pagamento e o fechamento da comanda ocorrem juntos ou não ocorrem.

## 8. Protótipo e arquitetura do código

O protótipo (`dev/prototipo/`) é uma página única em HTML, CSS e JavaScript puro, sem bibliotecas:

| Arquivo | Responsabilidade |
|---|---|
| `index.html` | estrutura das telas |
| `css/estilo.css` | aparência e responsividade |
| `js/api.js` | `fetch`, token, erros, painel de requisição/resposta, escape de HTML |
| `js/crud.js` | motor de CRUD genérico, guiado por `GET /` |
| `js/comandas.js` | tela de comandas (itens, pagamentos, fiado, cancelamento) |
| `js/relatorios.js` | tela de relatórios |
| `js/app.js` | login, abas e permissões |

Destaque: o formulário, os filtros, as colunas ordenáveis e os botões de cada tabela **são gerados a partir da documentação
devolvida pela própria API**, de modo que a API é a fonte única de verdade. O front-end esconde botões não permitidos ao
perfil, mas a autorização efetiva é sempre feita na API. O texto da API é escapado antes de ser inserido na página (proteção contra XSS).

Organização do repositório: `dev/api/v1` (API), `dev/db` (script e diagramas), `dev/prototipo` (interface), `dev/tests`
(testes), `dev/postman` (collection opcional), `dev/legado` (sistema anterior, apenas histórico) e `docs` (documentação).

## 9. Testes

**Testes automatizados da API** (`dev/tests/teste_api.php`, PHP CLI com cURL): 74 verificações — login, `GET`/`POST`/`PUT`/`DELETE`
das sete tabelas, as cinco regras de negócio principais, validações, relatórios e a limpeza dos dados criados. Resultado da última
execução: **74 ok, 0 falhas**.

| Regra de negócio | Esperado | Obtido |
|---|---|---|
| Pagamento acima do saldo | 422 | 422 |
| Item em comanda fechada | 409 | 409 |
| Excluir produto/categoria/comanda com vínculos | 409 | 409 |
| Atendente em rota de administrador | 403 | 403 |
| Requisição sem token | 401 | 401 |

**Testes no navegador** (Chrome, roteiro automatizado): login e logout, permissões por perfil (atendente sem a aba de usuários e sem botões
proibidos), CRUD de categorias, ordenação, busca, paginação, validações de formulário, abertura de comanda, itens, pagamento dividido
com fechamento automático, fiado, cancelamento, mensagens 409 e 422, retorno ao login com token inválido (401), tentativa de XSS e
ausência de rolagem horizontal em tela de 390 px. **[AJUSTAR: repetir manualmente os principais fluxos antes da apresentação.]**

## 10. Conclusão

O projeto entregou um banco relacional normalizado até a 3FN (com uma desnormalização justificada), um script SQL completo,
uma API REST com CRUD para todas as tabelas, regras de negócio na camada correta e um protótipo que exercita a API.
Aprendizados: a importância de deixar o banco garantir a integridade (restrições, `ON DELETE`), a diferença entre dado derivado
(total) e dado histórico (`valor_unitario`), o papel das transações e a separação de responsabilidades entre banco, API e interface.

**Limitações e trabalhos futuros:** limite de tentativas de login e revogação de tokens; controle de estoque; upload de imagens;
paginação por cursor em tabelas muito grandes; relatórios exportáveis.

## 11. Referências

**[AJUSTAR: incluir as referências realmente consultadas, no padrão ABNT.]** Sugestões de fontes: documentação oficial do MySQL
(https://dev.mysql.com/doc/) e do PHP (https://www.php.net/manual/pt_BR/); RFC 7519 (JSON Web Token); RFC 9110 (HTTP Semantics);
material da UC de Banco de Dados; ELMASRI, R.; NAVATHE, S. *Sistemas de Banco de Dados*; SILBERSCHATZ, A.; KORTH, H.; SUDARSHAN, S.
*Sistema de Banco de Dados*.
