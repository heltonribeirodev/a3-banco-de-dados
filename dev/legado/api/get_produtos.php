<?php
// api/get_produtos.php
require_once 'conexao.php';

// 1. Verifica se a requisição está pedindo um produto específico pelo ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];
    
    // Utiliza prepare() para evitar SQL Injection
    $stmt = $pdo->prepare("SELECT id, nome, valor, categoria, imagem FROM produtos WHERE id = ?");
    $stmt->execute([$id]);
    
    // fetch() traz apenas 1 registro (em vez de fetchAll)
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    
    if ($produto) {
        // Retorna o produto diretamente, permitindo que o JS leia 'data.nome'
        echo json_encode($produto);
    } else {
        // Boas práticas: retorna status 404 se o ID não existir no banco
        http_response_code(404);
        echo json_encode(['erro' => 'Produto não encontrado']);
    }
    exit; // Encerra a execução aqui para não listar os demais
}

// 2. Se a requisição NÃO enviar um ID, mantém a listagem de todos 
// (útil caso você use essa mesma API para popular tabelas no futuro)
$stmt = $pdo->query("SELECT id, nome, valor, categoria, imagem FROM produtos ORDER BY categoria, nome");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['produtos' => $produtos]);
?>  