<?php
/**
 * API REST do Sistema de Gestão de Comandas (Projeto A3 - Banco de Dados).
 *
 * Ponto de entrada único (front controller). Rotas:
 *   POST /auth/login            -> autenticação (retorna token JWT)
 *   GET  /auth/me               -> usuário autenticado
 *   GET  /                      -> documentação dos recursos
 *   GET|POST     /{recurso}     -> listar | criar
 *   GET|PUT|DELETE /{recurso}/{id} -> consultar | atualizar | excluir
 */
declare(strict_types=1);

spl_autoload_register(function (string $classe): void {
    foreach (['src/', 'src/Resources/'] as $pasta) {
        $arquivo = __DIR__ . "/$pasta$classe.php";
        if (is_file($arquivo)) { require_once $arquivo; return; }
    }
});
require_once __DIR__ . '/src/Http.php';       // Request e Response
require_once __DIR__ . '/src/ApiException.php';

// CORS liberado para facilitar o protótipo em outra origem durante o desenvolvimento
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { Response::json(204); }

$recursos = [];
foreach (['Usuarios', 'Categorias', 'Produtos', 'Clientes', 'Comandas', 'ItensComanda', 'Pagamentos'] as $n) {
    $classe = $n . 'Resource';
    $recursos[(new ReflectionProperty($classe, 'tabela'))->getDefaultValue()] = $classe;
}

try {
    $req = new Request();
    $seg = $req->segmentos;

    // ---- Documentação (pública) ----
    if (!$seg) {
        if ($req->metodo !== 'GET') { throw new ApiException(405, 'Método não permitido.'); }
        $doc = [];
        foreach ($recursos as $nome => $classe) { $doc[$nome] = (new $classe())->descricao(); }
        Response::json(200, ['api' => 'Gestão de Comandas - A3', 'versao' => '1.0', 'autenticacao' => 'Bearer (JWT)', 'recursos' => $doc]);
    }

    // ---- Autenticação ----
    if ($seg[0] === 'auth') {
        if (($seg[1] ?? '') === 'login' && $req->metodo === 'POST') { Response::json(200, Auth::login($req->corpo)); }
        if (($seg[1] ?? '') === 'me' && $req->metodo === 'GET')     { Response::json(200, ['dados' => Auth::usuario($req)]); }
        throw new ApiException(404, 'Rota de autenticação não encontrada.');
    }

    // ---- Recursos (todos exigem token) ----
    if (!isset($recursos[$seg[0]])) { throw new ApiException(404, "Recurso '{$seg[0]}' não existe."); }
    $usuario  = Auth::usuario($req);
    $recurso  = new $recursos[$seg[0]]();
    $recurso->setUsuario($usuario);

    $id = null;
    if (isset($seg[1])) {
        if (!ctype_digit($seg[1]) || isset($seg[2])) { throw new ApiException(404, 'Rota não encontrada.'); }
        $id = (int)$seg[1];
    }

    switch ([$req->metodo, $id === null]) {
        case ['GET', true]:
            $recurso->exigirPermissao('listar', $usuario);
            Response::json(200, $recurso->listar($req->query));
        case ['GET', false]:
            $recurso->exigirPermissao('ver', $usuario);
            Response::json(200, $recurso->ver($id));
        case ['POST', true]:
            $recurso->exigirPermissao('criar', $usuario);
            Response::json(201, $recurso->criar($req->corpo));
        case ['PUT', false]:
            $recurso->exigirPermissao('atualizar', $usuario);
            Response::json(200, $recurso->atualizar($id, $req->corpo));
        case ['DELETE', false]:
            $recurso->exigirPermissao('excluir', $usuario);
            $recurso->excluir($id);
            Response::json(204);
        default:
            header('Allow: ' . ($id === null ? 'GET, POST' : 'GET, PUT, DELETE'));
            throw new ApiException(405, 'Método não permitido para esta rota.');
    }
} catch (ApiException $e) {
    Response::erro($e->status, $e->getMessage(), $e->detalhes);
} catch (PDOException $e) {
    // Traduz os erros de integridade do MySQL/MariaDB para respostas HTTP claras
    $codigo = $e->errorInfo[1] ?? 0;
    $msg = match (true) {
        $codigo === 1062                       => [409, 'Registro duplicado: já existe um registro com este valor único.'],
        $codigo === 1451                       => [409, 'O registro possui vínculos (itens, produtos, pagamentos etc.) e não pode ser excluído.'],
        $codigo === 1452                       => [422, 'Referência inválida: o registro relacionado não existe.'],
        in_array($codigo, [3819, 4025], true)  => [422, 'Valor viola uma restrição do banco de dados (CHECK).'],
        default                                => null,
    };
    if ($msg) { Response::erro($msg[0], $msg[1]); }
    error_log('[API A3] ' . $e->getMessage());
    Response::erro(500, 'Erro interno ao acessar o banco de dados.');
} catch (Throwable $e) {
    error_log('[API A3] ' . $e->getMessage());
    Response::erro(500, 'Erro interno no servidor.');
}
