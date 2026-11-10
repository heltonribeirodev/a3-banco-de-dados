# Gestão de Comandas — Projeto A3 (UC Banco de Dados, 2026/2)

Banco de dados, API REST e protótipo do sistema de gestão de comandas de bar/restaurante.
O sistema anterior (`sistema_gestao`, com `home.php`, `admin.php` etc.) **não foi alterado**.

## Como executar (XAMPP)

1. Inicie **Apache** e **MySQL** no painel do XAMPP.
2. No phpMyAdmin, aba **Importar**, selecione `dev/db/sistema_gestao_a3.sql`.
   O script cria o banco `sistema_gestao_a3` (pode ser executado novamente; ele recria apenas esse banco).
3. A API fica em `http://localhost/gestao-comandas-a3/dev/api/v1/`.
   Credenciais do banco em `dev/api/v1/config.php` (padrão do XAMPP: usuário `root`, sem senha).
4. Protótipo do CRUD: `http://localhost/gestao-comandas-a3/dev/prototipo/`.
5. Postman: importe `dev/postman/A3_API_Gestao_Comandas.postman_collection.json` (e, opcionalmente, o arquivo `A3_Local_XAMPP.postman_environment.json`) e execute a collection no **Runner**.

Requisitos: PHP 8.1 ou superior, MySQL 8 ou MariaDB 10.4 ou superior.
Se o Apache não aplicar o `.htaccess` (mod_rewrite), use `.../api/v1/index.php/produtos` no lugar de `.../api/v1/produtos`.

Usuários de teste (senha de ambos: `Senha@123`): `admin@a3.local` (administrador) e `ana@a3.local` (atendente).

## Modelo de dados

Sete tabelas: `usuarios`, `categorias`, `produtos`, `clientes`, `comandas`, `itens_comanda` e `pagamentos`.
Diagramas em `dev/db/` (`DER_conceitual.png`, `DER_logico.png`; o código-fonte Mermaid está nos arquivos `.mmd`).

Decisões de modelagem:

- `itens_comanda` resolve a relação N:N entre comandas e produtos e guarda `valor_unitario`, preservando o preço praticado na venda.
- Produto com histórico de venda não é excluído (`ON DELETE RESTRICT`); usa-se o campo `ativo`.
- Itens pertencem à comanda (`ON DELETE CASCADE`). Comanda com pagamentos não é excluída.
- Uma comanda pode ter vários pagamentos (pagamento dividido).
- Domínios fechados em `ENUM` e regras de valor em `CHECK`.

## API REST

Autenticação por token JWT: `POST /auth/login` retorna o token, enviado nas demais chamadas em `Authorization: Bearer <token>`.

| Recurso | GET (lista e id) | POST | PUT | DELETE | Observação |
|---|---|---|---|---|---|
| `/usuarios` | admin | admin | admin | admin | senha com hash bcrypt; não exclui a si mesmo nem o último admin |
| `/categorias` | todos | admin | admin | admin | |
| `/produtos` | todos | admin | admin | admin | filtros `categoria_id`, `ativo`, `q` |
| `/clientes` | todos | todos | todos | admin | |
| `/comandas` | todos | todos | todos | admin | atendente = usuário do token; total calculado pelos itens |
| `/itens_comanda` | todos | todos | todos | todos | só em comanda aberta; preço padrão do produto |
| `/pagamentos` | todos | todos | admin | admin | fecha a comanda ao cobrir o total |

Convenções: respostas em JSON; listagens paginadas (`?pagina=&limite=`), filtros e ordenação (`?ordem=&direcao=`).
Códigos HTTP: 200, 201, 204, 400 (JSON inválido), 401 (sem token), 403 (sem permissão), 404, 409 (conflito/regra de negócio), 422 (validação).
A rota `GET /` devolve a documentação dos campos de cada recurso.

## Organização do código

```
dev/api/v1/
  index.php            ponto de entrada e roteamento
  config.php           banco e JWT (aceita variáveis de ambiente)
  src/                 Database, Http (Request/Response), Jwt, Auth, Resource (CRUD genérico)
  src/Resources/       uma classe por tabela, com campos, permissões e regras de negócio
dev/db/                script SQL e DER
dev/postman/           collection e environment
dev/prototipo/         interface simples para simular o CRUD
```

## Limitações conhecidas

- A interface anterior do sistema (`home.php`, `admin.php`, `produtos.php`) ainda usa o banco antigo e seus próprios endpoints. O protótipo em `dev/prototipo/` é a interface que consome a nova API.
- `CORS` está liberado e o segredo do JWT é de desenvolvimento: ajuste ambos antes de qualquer uso em produção.
