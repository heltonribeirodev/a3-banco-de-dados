# Perguntas prováveis da banca e exercícios de alteração ao vivo

> **RASCUNHO para estudo.** Leia as respostas, teste cada uma no código/banco e reformule com as suas palavras.
> Respostas decoradas desmontam na primeira pergunta de acompanhamento: o objetivo é entender o "porquê".

## Parte 1 — 25 perguntas

### Modelagem e SQL

**1. O que é uma chave primária? E uma chave estrangeira?**
A PK identifica cada linha de forma única e não nula (aqui, `id` autoincremental). A FK é uma coluna que referencia a PK de outra tabela e garante que só existam valores que existem na tabela de origem (ex.: `comandas.cliente_id` → `clientes.id`).

**2. Por que usar `id` autoincremental em vez de uma chave natural (como e-mail)?**
Chave natural pode mudar e é maior. O `id` é estável, pequeno e eficiente para as FKs. O e-mail continua garantido como único por `UNIQUE`.

**3. Qual a cardinalidade entre comanda e produto, e como foi resolvida?**
N:N (uma comanda tem vários produtos; um produto está em várias comandas). Resolvida pela tabela associativa `itens_comanda`, com FK para as duas e atributos próprios (`quantidade`, `valor_unitario`).

**4. Qual a diferença entre `ON DELETE RESTRICT` e `ON DELETE CASCADE`? Onde usaram cada um?**
`RESTRICT` impede apagar o registro pai se houver filhos; `CASCADE` apaga os filhos junto. Usamos `RESTRICT` quase em tudo para proteger o histórico (produto vendido, cliente com comandas, comanda com pagamentos) e `CASCADE` só de `comandas` para `itens_comanda`, pois item não existe sem comanda.

**5. O que acontece se tentar excluir um produto que já foi vendido?**
O banco recusa (erro 1451, `RESTRICT`); a API traduz para HTTP 409. A saída prevista é inativar o produto (`ativo = 0`).

**6. Por que `valor_unitario` está em `itens_comanda` se o preço já está em `produtos`? Isso fere a normalização?**
É uma desnormalização intencional: guarda o preço **praticado na venda** (dado histórico). Sem ele, um reajuste mudaria o total de comandas antigas. Não é cópia redundante do preço atual, e sim outro fato.

**7. Em que forma normal está o modelo e por quê?**
3FN: atributos atômicos (1FN), sem dependência parcial, pois as chaves são simples (2FN), e sem dependência transitiva (3FN) — por exemplo, o nome da categoria só existe em `categorias`.

**8. Por que o total da comanda não é uma coluna?**
Porque é um dado derivado dos itens; armazená-lo criaria risco de ficar inconsistente. Ele é calculado pela view `vw_resumo_comandas`.

**9. O que é uma view e por que usaram uma?**
Uma consulta salva que se comporta como tabela virtual. Evita repetir o cálculo de total e pago em várias consultas e na API. Ela não guarda dados: executa a consulta a cada uso.

**10. Qual a diferença entre `INNER JOIN` e `LEFT JOIN`? Mostre um uso no projeto.**
`INNER JOIN` devolve só as linhas com correspondência nas duas tabelas; `LEFT JOIN` mantém todas as da esquerda, com `NULL` quando não há correspondência. Na consulta Q6 (desempenho dos atendentes) o `LEFT JOIN` mostra também o atendente que não abriu nenhuma comanda; na Q8 (`LEFT JOIN ... WHERE i.id IS NULL`) acham-se os produtos nunca vendidos.

**11. Qual a diferença entre `WHERE` e `HAVING`?**
`WHERE` filtra linhas **antes** do agrupamento; `HAVING` filtra os **grupos** depois de `GROUP BY`, podendo usar funções de agregação. Na Q10 o `HAVING SUM(total) > 100` mantém só os meses com faturamento acima de R$ 100.

**12. O que é uma subconsulta? Onde há uma no projeto?**
Uma consulta dentro de outra. Na Q9 há subconsulta no `HAVING` (média das comandas); na Q11, uma na cláusula `FROM` (gasto por cliente) e outra escalar (total geral para o percentual).

**13. Para que servem os índices? Por que não criaram índice em `pagamentos(comanda_id)`?**
Aceleram buscas, filtros e junções, ao custo de espaço e de escritas mais lentas. O InnoDB já cria automaticamente um índice para cada chave estrangeira, então outro seria redundante. Criamos índices só onde as FKs não cobrem: `(status, aberta_em)`, `produtos(nome)` e `pagamentos(pago_em)`.

**14. O que é uma transação e onde é usada?**
Um conjunto de operações que ocorre por inteiro ou não ocorre (atomicidade; ACID). Todo `POST`/`PUT`/`DELETE` da API roda em `BEGIN ... COMMIT` com `ROLLBACK` em caso de erro — por exemplo, inserir o pagamento e fechar a comanda.

**15. Para que servem `CHECK` e `ENUM`? Há desvantagens?**
Validam o dado dentro do banco (valor ≥ 0; status só dos valores permitidos), independentemente da aplicação. Desvantagem do `ENUM`: acrescentar valor exige `ALTER TABLE`. `CHECK` só é aplicado de fato no MySQL 8.0.16+ e no MariaDB 10.2+.

### API, segurança e REST

**16. O que é REST?**
Estilo de arquitetura em que recursos (`/produtos`) são identificados por URLs e manipulados pelos verbos HTTP, de forma sem estado (cada requisição carrega tudo que precisa, inclusive o token) e com códigos de status significativos.

**17. Qual a diferença entre PUT e PATCH? Qual o projeto usa?**
`PUT` substitui o recurso inteiro (por isso exige os campos obrigatórios); `PATCH` altera parcialmente. A API implementa `PUT` (o enunciado pede GET, POST, PUT e DELETE).

**18. Explique os códigos 200, 201, 204, 400, 401, 403, 404, 409 e 422.**
200 sucesso; 201 criado; 204 sucesso sem corpo (exclusão); 400 requisição malformada (JSON inválido); 401 não autenticado; 403 autenticado, mas sem permissão; 404 não encontrado; 409 conflito com o estado atual (comanda fechada, vínculo, e-mail duplicado); 422 dado semanticamente inválido (valor negativo, pagamento acima do saldo).

**19. Qual a diferença entre 401 e 403?**
401: não sabemos quem você é (token ausente/inválido/expirado). 403: sabemos quem é, mas o perfil não pode fazer isso (atendente em rota de administrador).

**20. Como funciona o JWT no projeto?**
O login valida e-mail/senha e devolve um token com três partes (cabeçalho, carga com o `id` do usuário e data de expiração, e assinatura HMAC-SHA256 com um segredo do servidor). O cliente o envia em `Authorization: Bearer`. O servidor recalcula a assinatura; se bater e não tiver expirado, confia no conteúdo. O token é assinado, **não criptografado**: não se põe dado sensível nele.

**21. Por que guardar a senha com hash bcrypt? Por que não criptografar?**
Hash é de mão única: ninguém, nem o administrador do banco, recupera a senha original. O bcrypt é lento de propósito e usa *salt* embutido, dificultando força bruta e tabelas pré-calculadas. Conferência: `password_verify`.

**22. Como o projeto evita SQL Injection?**
Com *prepared statements* (PDO): o SQL e os valores seguem separados, e os valores nunca são concatenados na consulta. Nomes de coluna usados em ordenação vêm de uma lista permitida (`ordenaveis`).

**23. Como o projeto evita XSS no protótipo?**
Todo texto vindo da API ou do usuário passa por uma função de escape (`Util.esc`) antes de entrar no HTML, de modo que `<script>` é exibido como texto. Testamos cadastrando uma categoria com `<img onerror=...>`.

**24. Se o front-end esconde o botão, isso basta para a segurança?**
Não. Esconder é conveniência de interface; qualquer um pode chamar a API diretamente. A autorização real é feita na API (retorna 403), e há testes automatizados disso.

**25. O que é CORS e como foi configurado?**
Mecanismo do navegador que controla se uma página de uma origem pode chamar a API de outra. A API só devolve `Access-Control-Allow-Origin` para as origens listadas (padrão: `localhost`). O protótipo está na mesma origem, então nem precisaria de CORS.

---

## Parte 2 — 6 exercícios de alteração ao vivo

Prepare-se para fazer cada um **sem consultar o roteiro**. Depois de cada alteração, teste e, se quiser, reverta com `git checkout -- <arquivo>`.

### Exercício 1 — Adicionar um campo (`email` em clientes)
1. Banco: `ALTER TABLE clientes ADD COLUMN email VARCHAR(255) NULL AFTER telefone;`
2. API: em `dev/api/v1/src/Resources/ClientesResource.php`, acrescente no array `$campos`:
   `'email' => ['tipo' => 'email', 'max' => 255, 'anulavel' => true],`
3. Teste: `GET /` mostra o campo novo; recarregue o protótipo → aba *clientes* já exibe a coluna e o campo no formulário (o front lê `GET /`; **não precisa mexer no JavaScript**).
4. Explique: por que o front se adaptou sozinho? (formulário gerado a partir da documentação da API).

### Exercício 2 — Nova consulta/relatório (clientes que nunca abriram comanda)
1. SQL (teste no phpMyAdmin):
   ```sql
   SELECT cl.id, cl.nome FROM clientes cl
   LEFT JOIN comandas c ON c.cliente_id = cl.id
   WHERE c.id IS NULL;
   ```
2. API, em `dev/api/v1/src/Relatorios.php`: (a) adicione em `LISTA`:
   `'clientes-sem-comanda' => ['Clientes que nunca abriram comanda', []],`
   (b) adicione a linha no `match`: `'clientes-sem-comanda' => self::clientesSemComanda(),`
   (c) crie o método `private static function clientesSemComanda(): array { return ["SELECT ... (o SQL acima)", [], ['id']]; }`
3. Teste: `GET /relatorios/clientes-sem-comanda` com token; no protótipo aparece automaticamente na lista da aba *relatorios*.
4. Explique: `LEFT JOIN` + `IS NULL` (anti-junção).

### Exercício 3 — Nova regra de negócio (não abrir comanda para quem tem fiado em aberto)
1. Em `ComandasResource.php`, no início de `antesCriar(array &$dados)`, acrescente:
   ```php
   $st = $this->pdo->prepare("SELECT COUNT(*) FROM comandas WHERE cliente_id = ? AND status = 'fiado'");
   $st->execute([$dados['cliente_id']]);
   if ((int)$st->fetchColumn() > 0) {
       throw new ApiException(409, 'O cliente possui comanda em fiado em aberto.');
   }
   ```
2. Teste: abrir comanda para o cliente 5 (Ricardo Lima, fiado) → 409; para o cliente 1 → 201. A mensagem aparece no protótipo.
3. Explique: por que 409 e não 422? (o dado é válido; o conflito é com o estado do sistema).

### Exercício 4 — Novo filtro (produtos com valor mínimo) e nova ordenação
1. Em `ProdutosResource.php`, em `$filtros`, acrescente: `'valor_min' => ['t.valor', '>=']`.
2. Teste: `GET /produtos?valor_min=10`. No protótipo (aba *produtos*) surge o campo "valor_min" automaticamente.
3. Para ordenar por outra coluna (ex.: `telefone` em clientes), acrescente-a em `$ordenaveis` de `ClientesResource.php`; a coluna passa a ser clicável.
4. Explique: a lista `ordenaveis` impede ordenar por coluna arbitrária (segurança/SQL Injection).

### Exercício 5 — Novo valor de `ENUM` (forma de pagamento "vale_refeicao")
1. Banco:
   ```sql
   ALTER TABLE pagamentos
     MODIFY metodo ENUM('pix','dinheiro','cartao_credito','cartao_debito','vale_refeicao') NOT NULL;
   ```
2. API: em `PagamentosResource.php`, acrescente `'vale_refeicao'` na lista `valores` do campo `metodo`.
3. Front: em `index.html`, no `<select id="pagtoMetodo">`, acrescente `<option value="vale_refeicao">Vale-refeição</option>` (a tela genérica de *pagamentos* já pega o valor novo sozinha).
4. Explique: este exercício passa pelas três camadas (banco, API, interface) — por que o banco também precisa mudar? (o `ENUM` valida no banco).

### Exercício 6 — Mostrar o atendente na lista de comandas
1. API, em `ComandasResource.php`: no método `origem()` acrescente `JOIN usuarios u ON u.id = t.usuario_id`, e em `colunas()` acrescente `, u.nome AS atendente_nome`.
2. Front, em `dev/prototipo/js/comandas.js`, em `desenharLista`: acrescente `<th>Atendente</th>` no cabeçalho e `'<td>' + Util.esc(c.atendente_nome) + '</td>'` na linha, na mesma posição.
3. Teste: `GET /comandas` traz `atendente_nome`; a tabela mostra a coluna nova.
4. Explique: por que um `JOIN` e não uma segunda consulta por linha? (evita o problema "N+1": uma consulta em vez de uma por comanda).

---

**Dica final:** na hora, diga em voz alta o que vai mudar e em qual camada (banco → API → interface) antes de digitar.
Se travar, leia o erro devolvido pela API no painel de requisição/resposta: ele costuma dizer o que está errado.
