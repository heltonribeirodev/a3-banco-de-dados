# Roteiro da apresentação — 15 minutos (01/12/2026)

> **RASCUNHO.** Adapte o texto à sua fala. Cada slide deve ter pouco texto na tela (títulos, tópicos curtos, diagramas);
> o que está em "Falar" é para você dizer, não para colar no slide. O arquivo apresentado deve ser **o mesmo enviado em 28/11**.
> Combine com o grupo quem fala em cada parte (minha parte: banco de dados, API e front-end).

**Tempo total:** 15 min (12 min de slides + demonstração dentro dos slides 9 a 11 + 3 min de margem para perguntas).

---

### Slide 1 — Capa (0:30)
**Na tela:** título "Gestão de Comandas", UC Banco de Dados, A3 2026/2, integrantes.
**Falar:** apresentar o grupo e o tema em uma frase: "um sistema para controlar as comandas de um bar, do cardápio ao pagamento".

### Slide 2 — O problema (1:00)
**Na tela:** 3 tópicos — comandas em papel/planilha; erros de soma e "fiado" sem controle; falta de relatórios.
**Falar:** o dia a dia do bar; por que um banco relacional resolve (integridade, consultas, histórico). Delimitar o escopo: sem estoque e sem upload de imagens.

### Slide 3 — Requisitos e regras de negócio (1:00)
**Na tela:** 5 regras principais — total calculado; pagamento ≤ saldo; item só em comanda aberta; comanda com pagamento não se cancela; atendente × administrador.
**Falar:** "as regras são o que dá sentido ao modelo". Dizer onde cada uma é garantida (banco ou API).

### Slide 4 — Modelo conceitual (1:00)
**Na tela:** DER conceitual (7 entidades).
**Falar:** percorrer as cardinalidades (cliente 1:N comanda; categoria 1:N produto...). Destacar o N:N entre comanda e produto, resolvido por `ITEM_COMANDA`.

### Slide 5 — Modelo lógico e escolhas (1:00)
**Na tela:** DER lógico (PK, FK) + 3 tópicos: `ENUM`/`CHECK`; `ON DELETE RESTRICT` × `CASCADE`; inativar em vez de excluir.
**Falar:** por que `RESTRICT` protege o histórico e por que só os itens usam `CASCADE`.

### Slide 6 — Normalização (1:00)
**Na tela:** 1FN, 2FN, 3FN (uma linha cada) e destaque "`valor_unitario` = desnormalização intencional".
**Falar:** o exemplo do reajuste: o preço do produto muda, mas as comandas antigas não podem mudar. Total não é coluna: é derivado pela view.

### Slide 7 — Script SQL: DDL e DML (1:00)
**Na tela:** trecho de `CREATE TABLE` + `ALTER TABLE ... FOREIGN KEY` e contagem da carga (19 produtos, 15 comandas...).
**Falar:** o script roda várias vezes; `UPDATE` e `DELETE` de exemplo no próprio script.

### Slide 8 — Consultas DQL (1:00)
**Na tela:** 2 consultas — relatório por período com `JOIN` e faturamento por mês com `GROUP BY` + `HAVING`; mencionar view e ranking com subconsulta.
**Falar:** explicar um `JOIN` na prática ("une a comanda ao cliente e ao atendente"), e a diferença entre `WHERE` e `HAVING`.

### Slide 9 — Arquitetura e API REST (1:30)
**Na tela:** diagrama Navegador → API PHP → MySQL; tabela verbo × ação (GET, POST, PUT, DELETE); códigos 200/201/204/401/403/404/409/422.
**Falar:** uma classe genérica faz o CRUD; cada tabela declara só campos e regras. JWT: login devolve token, que vai no cabeçalho. Permissões por perfil.

### Slide 10 — Demonstração ao vivo (3:30)
**Na tela:** (alternar para o navegador). Roteiro abaixo.

### Slide 11 — Segurança e qualidade (0:45)
**Na tela:** bcrypt; JWT; prepared statements; CORS restrito; escape de HTML; `config.php` bloqueado.
**Falar:** uma frase por item. Mencionar a limitação conhecida (sem limite de tentativas de login).

### Slide 12 — Testes (0:45)
**Na tela:** tabela "teste × esperado × obtido" das 5 regras + "74 verificações automatizadas, 0 falhas".
**Falar:** o script cria e remove os próprios dados; rodar no terminal só se houver tempo.

### Slide 13 — Aprendizados e trabalhos futuros (0:45)
**Na tela:** 3 aprendizados + 3 melhorias (estoque, limite de login, upload de imagem).
**Falar:** o que o grupo aprendeu (integridade no banco, dado histórico × derivado, transações).

### Slide 14 — Encerramento (0:15)
**Na tela:** "Obrigado — perguntas?" + repositório.

---

## Roteiro da demonstração (slide 10, ~3:30)

**Antes de começar (faça uns 10 minutos antes):**
1. Apache e MySQL ligados no XAMPP. Reimporte `dev/db/sistema_gestao_a3.sql` para voltar ao estado inicial.
2. Abra `http://localhost/gestao-comandas-a3/dev/prototipo/` e deixe o painel de requisição/resposta visível.
3. Tenha uma aba com o phpMyAdmin no banco `sistema_gestao_a3` (para mostrar que o dado realmente mudou).

**Passo a passo:**

1. **Login como atendente** (`ana@a3.local` / `Senha@123`). Mostre que **não há a aba "usuarios"** e que em *produtos* não há botões de criar/editar. *Fale:* "a tela esconde, mas quem barra de verdade é a API".
2. **Abrir uma comanda:** aba *comandas* → *+ Abrir comanda* → cliente → abrir.
3. **Adicionar itens:** *+ Adicionar item* (ex.: 2 Cervejas Long Neck e 1 Porção de Fritas). Mostre o total sendo calculado. Aponte no painel o `POST /itens_comanda` e a resposta.
4. **Editar e remover um item** (mostrar `PUT` e `DELETE` no painel).
5. **Pagamento dividido:** *+ Registrar pagamento* → Pix de parte do valor → depois o restante em dinheiro. A comanda passa para **fechada** sozinha.
6. **Regra 422:** em outra comanda, tente pagar um valor maior que o saldo → mensagem "422 — valor excede o saldo". *Fale:* "a regra mora na API".
7. **Regra 409:** com a comanda fechada, mostre que os botões de item sumiram; abra a comanda #5 (fiado, com pagamento) e clique em *Cancelar comanda* → 409.
8. **Sair e entrar como administrador** (`admin@a3.local`). Mostre a aba *usuarios*.
9. **CRUD genérico:** aba *produtos* → busca "cerveja", ordenar por valor, *Novo registro* (mostrar validação de campo obrigatório), criar, editar, excluir. Tente excluir um produto com vendas → 409 ("não pode, tem vínculos").
10. **Relatórios:** faturamento mensal; produtos mais vendidos com período; saldo devedor do fiado. *Fale:* "são os SELECT com JOIN do script".
11. **(Opcional)** No phpMyAdmin, mostre a tabela `itens_comanda` e `valor_unitario`.

**Plano B se algo falhar:** recarregue a página (o token fica na aba); se a API não responder, confira o Apache/MySQL; mantenha prints
de cada tela em um slide de reserva e a collection do Postman aberta.
