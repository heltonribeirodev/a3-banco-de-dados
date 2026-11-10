# Gestão de Comandas — Projeto A3 (UC Banco de Dados, 2026/2)

Banco de dados, API REST e protótipo do sistema de gestão de comandas de bar/restaurante.
Banco ativo: `sistema_gestao_a3`. Tudo o que pertence ao sistema antigo (banco `sistema_gestao`,
`home.php`, `admin.php` etc.) está **descontinuado** e foi movido para `dev/legado/`.

## Estrutura do repositório

```
dev/
  api/v1/            API REST (PHP): index.php, config.php, src/, src/Resources/
  assets/            default.png (imagem padrão), logo.png e uploads/ (imagens dos produtos)
  db/                sistema_gestao_a3.sql (DDL + DML + DQL) e DER (.mmd e .png)
  prototipo/         interface oficial (index.html, css/estilo.css, js/*.js)
  tests/             teste_api.php (testes automatizados da API)
  legado/            sistema antigo, mantido apenas como histórico
  README_A3.md       este arquivo
docs/                relatório técnico, roteiro da apresentação, perguntas da banca
```

## Como executar (XAMPP / Windows)

Requisitos: PHP 8.1+, MySQL 8 ou MariaDB 10.4+ (o XAMPP já traz), extensão `curl` do PHP para os testes.

1. Inicie **Apache** e **MySQL** no painel do XAMPP.
2. Importe o banco: phpMyAdmin → **Importar** → `dev/db/sistema_gestao_a3.sql`.
   Ou, pelo terminal: `C:\xampp\mysql\bin\mysql.exe -uroot < dev\db\sistema_gestao_a3.sql`.
   O script pode ser executado várias vezes: ele recria apenas o banco `sistema_gestao_a3`.
3. API: `http://localhost/gestao-comandas-a3/dev/api/v1/`
   Credenciais do banco em `dev/api/v1/config.php` (padrão do XAMPP: `root`, sem senha).
   Se o `mod_rewrite` não estiver ativo, use `.../api/v1/index.php/produtos` no lugar de `.../api/v1/produtos`.
4. Protótipo: `http://localhost/gestao-comandas-a3/dev/prototipo/`

Usuários de teste (senha de todos: `Senha@123`): `admin@a3.local` (administrador), `ana@a3.local` e
`pedro@a3.local` (atendentes) e `lucas@a3.local` (atendente **inativo**: o login é recusado).

### Configuração por variáveis de ambiente (opcional)

| Variável | Função | Padrão |
|---|---|---|
| `A3_DB_HOST`, `A3_DB_PORT`, `A3_DB_NAME`, `A3_DB_USER`, `A3_DB_PASS` | conexão com o banco | `localhost`, `3306`, `sistema_gestao_a3`, `root`, vazio |
| `A3_JWT_SECRET` | segredo que assina os tokens JWT | se ausente, é gerado um segredo aleatório em `dev/api/v1/.jwt_secret` (ignorado pelo Git) |
| `A3_CORS_ORIGINS` | origens autorizadas a chamar a API pelo navegador, separadas por vírgula | `http://localhost,http://127.0.0.1` |

O `.htaccess` da API bloqueia o acesso web a `config.php`, `.jwt_secret` e `src/` (resposta 403).

## Modelo de dados

Sete tabelas: `usuarios`, `categorias`, `produtos`, `clientes`, `comandas`, `itens_comanda` e `pagamentos`,
mais a view `vw_resumo_comandas` (total, pago e saldo de cada comanda).
Diagramas em `dev/db/` (`DER_conceitual` e `DER_logico`; o código-fonte Mermaid está nos arquivos `.mmd`).

Decisões de modelagem:

- `itens_comanda` resolve a relação N:N entre comandas e produtos e guarda `valor_unitario`, preservando o preço praticado na venda.
- Produto com histórico de venda não é excluído (`ON DELETE RESTRICT`); usa-se o campo `ativo`.
- Itens pertencem à comanda (`ON DELETE CASCADE`). Comanda com pagamentos não é excluída.
- Uma comanda pode ter vários pagamentos (pagamento dividido).
- Domínios fechados em `ENUM` e regras de valor em `CHECK`.
- O InnoDB já cria um índice para cada chave estrangeira; por isso não há índice extra em `pagamentos(comanda_id)`.
  Foram criados apenas índices que as chaves não cobrem (`comandas(status, aberta_em)`, `produtos(nome)`, `pagamentos(pago_em)`).

O script `.sql` traz as consultas DQL Q1 a Q12 (JOINs, período, GROUP BY/HAVING, subconsultas e uso da view).

## API REST

Autenticação por token JWT: `POST /auth/login` retorna o token, enviado nas demais chamadas em
`Authorization: Bearer <token>` (validade de 8 horas). `GET /auth/me` devolve o usuário do token.
`GET /` (público) devolve a documentação: campos, filtros, colunas ordenáveis e permissões de cada recurso e os relatórios.

### Recursos (CRUD)

| Recurso | GET (lista e id) | POST | PUT | DELETE | Observação |
|---|---|---|---|---|---|
| `/usuarios` | admin | admin | admin | admin | senha com hash bcrypt; não exclui a si mesmo nem o último admin |
| `/categorias` | todos | admin | admin | admin | |
| `/produtos` | todos | admin | admin | admin | filtros `categoria_id`, `ativo`, `q` |
| `/clientes` | todos | todos | todos | admin | `telefone` aceita `""` no PUT para limpar o valor |
| `/comandas` | todos | todos | todos | admin | atendente = usuário do token; total calculado pelos itens; filtros `status`, `cliente_id`, `usuario_id`, `data_inicio`, `data_fim` |
| `/itens_comanda` | todos | todos | todos | todos | só em comanda aberta; preço padrão do produto |
| `/pagamentos` | todos | todos | admin | admin | fecha a comanda ao cobrir o total |

"todos" = qualquer usuário autenticado. O `PUT` substitui o registro: envie os campos obrigatórios.

Convenções: JSON; listagens paginadas (`?pagina=&limite=`, máximo 100), filtros e ordenação (`?ordem=&direcao=asc|desc`).
`data_fim` informada só com a data (`2026-10-15`) vale até o fim do dia.

### Relatórios (somente leitura, token obrigatório, qualquer perfil)

`GET /relatorios/{nome}` devolve `{ "dados": [...], "meta": {...} }`.

| Relatório | Parâmetros opcionais | Consulta do script |
|---|---|---|
| `comandas-periodo` | `data_inicio`, `data_fim` (AAAA-MM-DD) | Q1/Q2 |
| `produtos-mais-vendidos` | `data_inicio`, `data_fim`, `limite` (padrão 10) | Q3 |
| `faturamento-categoria` | `data_inicio`, `data_fim` | Q4 |
| `pagamentos-metodo` | `data_inicio`, `data_fim` | Q5 |
| `desempenho-atendentes` | — | Q6 |
| `fiado-saldo-devedor` | — | Q7 |
| `faturamento-mensal` | `minimo` (valor mínimo, filtro HAVING) | Q10 |

### Códigos HTTP

| Código | Significado |
|---|---|
| 200 / 201 / 204 | sucesso / criado / excluído (sem corpo) |
| 400 | corpo da requisição não é um JSON válido |
| 401 | token ausente, inválido ou expirado; e-mail/senha incorretos no login |
| 403 | perfil sem permissão (ex.: atendente em rota de administrador) |
| 404 | recurso, registro ou relatório inexistente |
| 405 | método não permitido na rota |
| 409 | regra de negócio ou vínculo (comanda fechada, exclusão com vínculos, e-mail duplicado) |
| 422 | validação (campo obrigatório, valor negativo, pagamento acima do saldo, data inválida) |
| 500 | erro interno (os detalhes ficam só no log do servidor) |

Erros têm o formato `{ "erro": "mensagem", "detalhes": { "campo": "motivo" } }`.

### Regras de negócio principais

- Itens só podem ser alterados em comanda **aberta** (senão 409).
- Pagamento só em comanda aberta ou fiado, e nunca acima do saldo (422). Ao cobrir o total, a comanda é fechada automaticamente.
- Comanda com pagamentos não pode ser cancelada nem excluída (409).
- Produto, categoria ou cliente com vínculos não podem ser excluídos (409). Produto inativo não pode ser vendido.

## Protótipo

`dev/prototipo/` é a interface oficial, em HTML + CSS + JavaScript puro (sem bibliotecas):

| Arquivo | Função |
|---|---|
| `index.html` | estrutura das telas |
| `css/estilo.css` | estilos (responsivo) |
| `js/api.js` | `fetch`, token, tratamento de erros, painel de requisição/resposta, escape de HTML |
| `js/crud.js` | motor genérico de CRUD guiado por `GET /` (categorias, produtos, clientes, usuários, pagamentos) |
| `js/comandas.js` | tela de comandas: filtros, itens, pagamentos, fiado e cancelamento |
| `js/relatorios.js` | tela de relatórios |
| `js/app.js` | login, sessão, abas e permissões |

O token fica em `sessionStorage` (some ao fechar a aba). A tela esconde o que o perfil não pode fazer, mas a
autorização de verdade é sempre da API.

## Testes

Com o Apache e o MySQL ligados e o banco importado: `C:\xampp\php\php.exe dev\tests\teste_api.php`.
O script cria e remove os próprios dados de teste (veja `dev/tests/teste_api.php`).

## Limitações conhecidas

- O login não limita tentativas (sem proteção contra força bruta) e o token não pode ser revogado antes de expirar.
- Imagem do produto é apenas um caminho em texto; não há upload.
- O sistema antigo em `dev/legado/` não é mais mantido e seus caminhos relativos não funcionam mais.
