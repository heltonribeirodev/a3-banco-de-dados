# Gestão de Comandas — Projeto A3

Projeto da UC de Banco de Dados (2026/2). É um sistema para controlar as comandas de um bar: abrir a comanda,
lançar os itens, receber o pagamento (até dividido em mais de uma forma) e tirar relatórios de vendas.

O trabalho tem três partes: o **banco de dados** (MySQL), uma **API** em PHP e um **protótipo** em HTML/JavaScript
que usa a API para mostrar o CRUD funcionando.

## Como rodar

1. Ligue o **Apache** e o **MySQL** no XAMPP.
2. No phpMyAdmin, importe o arquivo `dev/db/sistema_gestao_a3.sql`. Ele cria o banco `sistema_gestao_a3`
   e pode ser importado de novo quando quiser, porque recria tudo do zero.
3. Abra o protótipo: `http://localhost/gestao-comandas-a3/dev/prototipo/`
4. A API fica em `http://localhost/gestao-comandas-a3/dev/api/v1/`. A conexão com o banco está em
   `dev/api/v1/config.php` (usuário `root`, sem senha, que é o padrão do XAMPP).

Precisa de PHP 8.1 ou mais novo. Se as rotas da API derem 404, use `.../api/v1/index.php/produtos`
no lugar de `.../api/v1/produtos` (acontece quando o `mod_rewrite` está desligado).

## Usuários de teste

A senha de todos é `Senha@123`.

| E-mail | Perfil |
|---|---|
| `admin@a3.local` | administrador |
| `ana@a3.local` | atendente |
| `pedro@a3.local` | atendente |
| `lucas@a3.local` | atendente inativo (o login é recusado de propósito) |

## Pastas

```
dev/
  api/v1/       a API (index.php, config.php, src/)
  assets/       imagens dos produtos
  db/           script SQL e os diagramas (DER)
  prototipo/    a interface (index.html, css/, js/)
docs/           relatório técnico, roteiro dos slides e perguntas da banca
```

## Banco de dados

São sete tabelas: `usuarios`, `categorias`, `produtos`, `clientes`, `comandas`, `itens_comanda` e `pagamentos`.
Também tem uma view, `vw_resumo_comandas`, que mostra o total, o valor pago e o saldo de cada comanda.

Algumas decisões que tomamos:

- Uma comanda tem vários produtos e um produto está em várias comandas. Essa relação N:N virou a tabela `itens_comanda`.
- O item guarda o `valor_unitario`, que é o preço na hora da venda. Assim, se o preço do produto mudar depois,
  as comandas antigas não mudam.
- Produto que já foi vendido não pode ser apagado (`ON DELETE RESTRICT`). Para tirar do cardápio, usamos o campo `ativo`.
- Os itens são apagados junto com a comanda (`ON DELETE CASCADE`), mas comanda com pagamento não pode ser excluída.
- O total da comanda não fica salvo em nenhuma coluna. Ele é calculado pela view, somando os itens.

O script `.sql` está dividido em DDL (criar as tabelas), DML (dados de exemplo, com `UPDATE` e `DELETE`)
e DQL (12 consultas com `JOIN`, por período, `GROUP BY`/`HAVING`, subconsulta e uso da view).

Ele ainda tem três **procedures com transação**, que fazem as operações principais direto no banco:

- `sp_adicionar_item`: coloca um item numa comanda aberta.
- `sp_registrar_pagamento`: registra o pagamento e fecha a comanda se o saldo chegar a zero.
- `sp_cancelar_comanda`: cancela a comanda, desde que ela não tenha pagamentos.

Se alguma regra falhar, a transação é desfeita (`ROLLBACK`). Exemplo: `CALL sp_registrar_pagamento(10, 2, 'pix', 10.00);`

## Protótipo

A interface foi feita só com HTML, CSS e JavaScript, sem biblioteca nenhuma. Ela tem login, as telas de CRUD
(categorias, produtos, clientes, usuários e pagamentos), a tela de comandas e a tela de relatórios. Embaixo da tela
fica um painel que mostra a requisição enviada à API e a resposta, o que ajuda a acompanhar o que acontece.

- `js/api.js`: faz as chamadas à API e trata os erros.
- `js/crud.js`: uma tela de CRUD que serve para várias tabelas, montada a partir do `GET /` da API.
- `js/comandas.js`: tela de comandas (itens, pagamentos, fiado e cancelamento).
- `js/relatorios.js`: tela de relatórios.
- `js/app.js`: login e abas.

O atendente não vê a aba de usuários nem os botões que ele não pode usar. Mesmo assim, quem barra de verdade
é a API, porque o botão escondido não impede ninguém de chamar a rota direto.

## Configurações (opcional)

Dá para trocar a conexão com o banco por variáveis de ambiente (`A3_DB_HOST`, `A3_DB_PORT`, `A3_DB_NAME`,
`A3_DB_USER`, `A3_DB_PASS`), o segredo do JWT (`A3_JWT_SECRET`) e as origens aceitas pelo CORS (`A3_CORS_ORIGINS`).
Sem o `A3_JWT_SECRET`, a API gera um segredo aleatório e guarda no arquivo `dev/api/v1/.jwt_secret`.

## O que ainda não tem

- Limite de tentativas de login.
- Upload de imagem (o produto guarda só o caminho da imagem).
- Controle de estoque.
