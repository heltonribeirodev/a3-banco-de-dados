<?php
/**
 * Testes automatizados da API v1 (PHP CLI + curl).
 *
 * Como rodar (Apache e MySQL ligados, banco importado):
 *   C:\xampp\php\php.exe dev\tests\teste_api.php [url_base]
 *
 * O que faz:
 *   1) autentica (administrador e atendente);
 *   2) exercita GET, POST, PUT e DELETE das 7 tabelas;
 *   3) valida regras de negócio (422, 409, 403, 401);
 *   4) remove todos os dados que criou, mesmo se algum teste falhar.
 * Termina com código 0 se tudo passou e 1 se algum teste falhou.
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://localhost/gestao-comandas-a3/dev/api/v1', '/');
$sufixo = substr(uniqid(), -6);          // evita colidir com dados existentes (e-mail, nome)
$resultados = [];                        // [teste, esperado, obtido, passou]
$criados = [];                           // ids criados, para a limpeza final: [recurso, id]

/** Chamada HTTP. Devolve [status, json decodificado ou null]. */
function chamar(string $metodo, string $caminho, ?array $corpo = null, ?string $token = null, ?string $corpoBruto = null): array
{
    global $base;
    $ch = curl_init($base . $caminho);
    $cab = ['Accept: application/json'];
    if ($token) { $cab[] = 'Authorization: Bearer ' . $token; }
    if ($corpo !== null || $corpoBruto !== null) {
        $cab[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $corpoBruto ?? json_encode($corpo));
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $cab,
                            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $texto = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, is_string($texto) && $texto !== '' ? json_decode($texto, true) : null];
}

/** Registra um teste comparando o valor esperado com o obtido. */
function conferir(string $nome, mixed $esperado, mixed $obtido): void
{
    global $resultados;
    $resultados[] = [$nome, (string)$esperado, (string)$obtido, $esperado == $obtido];
}

/** Chama a API, confere o status HTTP e devolve o JSON (para reaproveitar ids). */
function testar(string $nome, int $esperado, string $metodo, string $caminho, ?array $corpo, ?string $token): ?array
{
    [$status, $json] = chamar($metodo, $caminho, $corpo, $token);
    conferir($nome, $esperado, $status);
    return $json;
}

function login(string $email, string $senha = 'Senha@123'): ?string
{
    [$st, $json] = chamar('POST', '/auth/login', ['email' => $email, 'senha' => $senha]);
    return $st === 200 ? $json['token'] : null;
}

try {
    // ------------------------------------------------------------------
    // 1) Autenticação
    // ------------------------------------------------------------------
    $admin = login('admin@a3.local');
    conferir('Login do administrador retorna token', 'sim', $admin ? 'sim' : 'não');
    $atend = login('ana@a3.local');
    conferir('Login do atendente retorna token', 'sim', $atend ? 'sim' : 'não');
    testar('Login com senha errada', 401, 'POST', '/auth/login', ['email' => 'admin@a3.local', 'senha' => 'errada'], null);
    testar('Login de usuário inativo', 401, 'POST', '/auth/login', ['email' => 'lucas@a3.local', 'senha' => 'Senha@123'], null);
    testar('GET /auth/me com token', 200, 'GET', '/auth/me', null, $admin);
    if (!$admin || !$atend) { throw new RuntimeException('Sem token: verifique se o banco foi importado.'); }

    // ------------------------------------------------------------------
    // 2) CRUD das 7 tabelas
    // ------------------------------------------------------------------
    // categorias
    testar('GET /categorias (lista)', 200, 'GET', '/categorias', null, $admin);
    $r = testar('POST /categorias', 201, 'POST', '/categorias', ['nome' => "Cat Teste $sufixo"], $admin);
    $catId = $r['dados']['id'] ?? 0; $criados[] = ['categorias', $catId];
    testar('GET /categorias/{id}', 200, 'GET', "/categorias/$catId", null, $admin);
    testar('PUT /categorias/{id}', 200, 'PUT', "/categorias/$catId", ['nome' => "Cat Teste $sufixo B"], $admin);

    // produtos
    testar('GET /produtos (lista)', 200, 'GET', '/produtos', null, $admin);
    $r = testar('POST /produtos', 201, 'POST', '/produtos', ['categoria_id' => $catId, 'nome' => "Produto Teste $sufixo", 'valor' => 10], $admin);
    $prodId = $r['dados']['id'] ?? 0; $criados[] = ['produtos', $prodId];
    testar('GET /produtos/{id}', 200, 'GET', "/produtos/$prodId", null, $admin);
    testar('PUT /produtos/{id}', 200, 'PUT', "/produtos/$prodId", ['categoria_id' => $catId, 'nome' => "Produto Teste $sufixo", 'valor' => 10, 'imagem' => 'assets/default.png'], $admin);

    // clientes
    testar('GET /clientes (lista)', 200, 'GET', '/clientes', null, $admin);
    $r = testar('POST /clientes', 201, 'POST', '/clientes', ['nome' => "Cliente Teste $sufixo", 'telefone' => '(41) 90000-0000'], $admin);
    $cliId = $r['dados']['id'] ?? 0; $criados[] = ['clientes', $cliId];
    testar('GET /clientes/{id}', 200, 'GET', "/clientes/$cliId", null, $admin);
    testar('PUT /clientes/{id}', 200, 'PUT', "/clientes/$cliId", ['nome' => "Cliente Teste $sufixo", 'telefone' => ''], $admin);

    // usuarios
    testar('GET /usuarios (lista)', 200, 'GET', '/usuarios', null, $admin);
    $email = "teste$sufixo@a3.local";
    $r = testar('POST /usuarios', 201, 'POST', '/usuarios', ['nome' => "Usuário Teste $sufixo", 'email' => $email, 'senha' => 'Senha@123', 'perfil' => 'atendente'], $admin);
    $usrId = $r['dados']['id'] ?? 0; $criados[] = ['usuarios', $usrId];
    conferir('Resposta do usuário não expõe senha nem hash', 'sim', isset($r['dados']['senha']) || isset($r['dados']['senha_hash']) ? 'não' : 'sim');
    conferir('Novo usuário consegue fazer login', 'sim', login($email) ? 'sim' : 'não');
    testar('GET /usuarios/{id}', 200, 'GET', "/usuarios/$usrId", null, $admin);
    testar('PUT /usuarios/{id}', 200, 'PUT', "/usuarios/$usrId", ['nome' => "Usuário Teste $sufixo B", 'email' => $email, 'perfil' => 'atendente'], $admin);

    // comandas
    testar('GET /comandas (lista)', 200, 'GET', '/comandas', null, $admin);
    $r = testar('POST /comandas', 201, 'POST', '/comandas', ['cliente_id' => $cliId], $admin);
    $cmdId = $r['dados']['id'] ?? 0; $criados[] = ['comandas', $cmdId];
    conferir('Comanda nova nasce "aberta"', 'aberta', $r['dados']['status'] ?? '');
    testar('GET /comandas/{id}', 200, 'GET', "/comandas/$cmdId", null, $admin);
    testar('PUT /comandas/{id}', 200, 'PUT', "/comandas/$cmdId", ['cliente_id' => $cliId], $admin);

    // itens_comanda
    testar('GET /itens_comanda (lista)', 200, 'GET', '/itens_comanda', null, $admin);
    $r = testar('POST /itens_comanda', 201, 'POST', '/itens_comanda', ['comanda_id' => $cmdId, 'produto_id' => $prodId, 'quantidade' => 1], $admin);
    $itemId = $r['dados']['id'] ?? 0;
    conferir('Item usa o preço atual do produto', 10, $r['dados']['valor_unitario'] ?? '');
    testar('GET /itens_comanda/{id}', 200, 'GET', "/itens_comanda/$itemId", null, $admin);
    testar('PUT /itens_comanda/{id}', 200, 'PUT', "/itens_comanda/$itemId", ['produto_id' => $prodId, 'quantidade' => 2], $admin);
    [, $c] = chamar('GET', "/comandas/$cmdId", null, $admin);
    conferir('Total da comanda = 2 x 10', 20, $c['dados']['total'] ?? '');

    // pagamentos
    testar('GET /pagamentos (lista)', 200, 'GET', '/pagamentos', null, $admin);
    $r = testar('POST /pagamentos', 201, 'POST', '/pagamentos', ['comanda_id' => $cmdId, 'metodo' => 'pix', 'valor' => 5], $admin);
    $pagId = $r['dados']['id'] ?? 0; $criados[] = ['pagamentos', $pagId];
    testar('GET /pagamentos/{id}', 200, 'GET', "/pagamentos/$pagId", null, $admin);
    testar('PUT /pagamentos/{id}', 200, 'PUT', "/pagamentos/$pagId", ['metodo' => 'dinheiro', 'valor' => 6], $admin);
    testar('DELETE /pagamentos/{id}', 204, 'DELETE', "/pagamentos/$pagId", null, $admin);
    array_pop($criados);                       // já removido
    testar('GET /pagamentos/{id} após excluir', 404, 'GET', "/pagamentos/$pagId", null, $admin);

    // ------------------------------------------------------------------
    // 3) Regras de negócio
    // ------------------------------------------------------------------
    // (1) pagamento acima do saldo -> 422
    $r = testar('Regra 1: pagamento acima do saldo', 422, 'POST', '/pagamentos', ['comanda_id' => $cmdId, 'metodo' => 'pix', 'valor' => 25], $admin);
    // pagamento dividido que quita a comanda -> fecha automaticamente
    $r1 = testar('Pagamento dividido (parte 1 de 2)', 201, 'POST', '/pagamentos', ['comanda_id' => $cmdId, 'metodo' => 'pix', 'valor' => 12], $admin);
    $criados[] = ['pagamentos', $r1['dados']['id'] ?? 0];
    $r2 = testar('Pagamento dividido (parte 2 de 2)', 201, 'POST', '/pagamentos', ['comanda_id' => $cmdId, 'metodo' => 'dinheiro', 'valor' => 8], $admin);
    $criados[] = ['pagamentos', $r2['dados']['id'] ?? 0];
    [, $c] = chamar('GET', "/comandas/$cmdId", null, $admin);
    conferir('Comanda quitada passa para "fechada"', 'fechada', $c['dados']['status'] ?? '');
    // (2) item em comanda fechada -> 409
    testar('Regra 2: item em comanda fechada', 409, 'POST', '/itens_comanda', ['comanda_id' => $cmdId, 'produto_id' => $prodId, 'quantidade' => 1], $admin);
    // (3) exclusão com vínculo -> 409
    testar('Regra 3: excluir produto com itens vendidos', 409, 'DELETE', "/produtos/$prodId", null, $admin);
    testar('Regra 3b: excluir categoria com produtos', 409, 'DELETE', "/categorias/$catId", null, $admin);
    testar('Regra 3c: excluir comanda com pagamentos', 409, 'DELETE', "/comandas/$cmdId", null, $admin);
    // (4) atendente em rota de administrador -> 403
    testar('Regra 4: atendente em GET /usuarios', 403, 'GET', '/usuarios', null, $atend);
    testar('Regra 4b: atendente em POST /categorias', 403, 'POST', '/categorias', ['nome' => "X $sufixo"], $atend);
    // (5) sem token -> 401
    testar('Regra 5: GET /produtos sem token', 401, 'GET', '/produtos', null, null);
    testar('Regra 5b: GET /relatorios sem token', 401, 'GET', '/relatorios/pagamentos-metodo', null, null);

    // validações e erros gerais
    testar('Validação: valor negativo no produto', 422, 'POST', '/produtos', ['categoria_id' => $catId, 'nome' => 'X', 'valor' => -1], $admin);
    testar('Validação: campo obrigatório ausente', 422, 'POST', '/clientes', [], $admin);
    testar('Validação: e-mail duplicado', 409, 'POST', '/usuarios', ['nome' => 'Dup', 'email' => $email, 'senha' => 'Senha@123'], $admin);
    testar('Registro inexistente', 404, 'GET', '/produtos/99999999', null, $admin);
    [$st] = chamar('POST', '/clientes', null, $admin, '{json quebrado');
    conferir('JSON inválido', 400, $st);
    testar('Administrador não exclui a si mesmo', 409, 'DELETE', '/usuarios/1', null, $admin);

    // relatórios
    foreach (['comandas-periodo', 'produtos-mais-vendidos', 'faturamento-categoria', 'pagamentos-metodo',
              'desempenho-atendentes', 'fiado-saldo-devedor', 'faturamento-mensal'] as $rel) {
        testar("GET /relatorios/$rel", 200, 'GET', "/relatorios/$rel", null, $atend);
    }
    testar('Relatório com data inválida', 422, 'GET', '/relatorios/comandas-periodo?data_inicio=2026-13-45', null, $admin);
    testar('Relatório inexistente', 404, 'GET', '/relatorios/nao-existe', null, $admin);
    $r = chamar('GET', '/relatorios/comandas-periodo?data_inicio=2026-10-05&data_fim=2026-10-05', null, $admin)[1];
    conferir('Relatório por período (05/10/2026) retorna 2 comandas', 2, count($r['dados'] ?? []));

    // DELETE dos demais recursos, na ordem das dependências.
    // Os pagamentos saem primeiro: isso reabre a comanda, e só assim o item pode ser removido.
    foreach ($criados as [$rec, $id]) {
        if ($rec === 'pagamentos') { chamar('DELETE', "/pagamentos/$id", null, $admin); }
    }
    $criados = array_values(array_filter($criados, fn($c) => $c[0] !== 'pagamentos'));
    testar('DELETE /itens_comanda/{id}', 204, 'DELETE', "/itens_comanda/$itemId", null, $admin);
    testar('DELETE /comandas/{id}', 204, 'DELETE', "/comandas/$cmdId", null, $admin);
    testar('DELETE /produtos/{id}', 204, 'DELETE', "/produtos/$prodId", null, $admin);
    testar('DELETE /categorias/{id}', 204, 'DELETE', "/categorias/$catId", null, $admin);
    testar('DELETE /clientes/{id}', 204, 'DELETE', "/clientes/$cliId", null, $admin);
    testar('DELETE /usuarios/{id}', 204, 'DELETE', "/usuarios/$usrId", null, $admin);
    $criados = [];                              // tudo removido pelos próprios testes
} catch (Throwable $e) {
    $resultados[] = ['Execução interrompida', 'sem erros', $e->getMessage(), false];
} finally {
    // Limpeza de segurança: se algo falhou no meio, apaga o que sobrou (ordem inversa da criação)
    if (!empty($admin)) {
        foreach (array_reverse($criados) as [$rec, $id]) {
            if ($rec === 'comandas') {
                [, $c] = chamar('GET', "/comandas/$id", null, $admin);
                foreach ($c['dados']['pagamentos'] ?? [] as $p) { chamar('DELETE', '/pagamentos/' . $p['id'], null, $admin); }
            }
            if ($id) { chamar('DELETE', "/$rec/$id", null, $admin); }
        }
    }
}

// ----------------------------------------------------------------------
// Relatório final: tabela "teste | esperado | obtido"
// ----------------------------------------------------------------------
$larg = max(array_map(fn($x) => mb_strlen($x[0]), $resultados));
$falhas = 0;
echo str_pad('TESTE', $larg) . ' | ESPERADO | OBTIDO | RESULTADO' . PHP_EOL . str_repeat('-', $larg + 38) . PHP_EOL;
foreach ($resultados as [$nome, $esp, $obt, $passou]) {
    if (!$passou) { $falhas++; }
    echo $nome . str_repeat(' ', $larg - mb_strlen($nome)) . ' | ' . str_pad($esp, 8) . ' | ' . str_pad($obt, 6) . ' | ' . ($passou ? 'OK' : 'FALHOU') . PHP_EOL;
}
echo PHP_EOL . count($resultados) . ' testes, ' . (count($resultados) - $falhas) . ' ok, ' . $falhas . ' falha(s).' . PHP_EOL;
exit($falhas ? 1 : 0);
