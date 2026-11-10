<?php
session_start();
require_once 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Captura o nome do cliente (Certifique-se que o input no HTML tenha name="cliente")
    $cliente = trim($_POST['cliente'] ?? '');

    // Validação básica
    if (empty($cliente)) {
        header("Location: ../home.php?status=erro_cliente");
        exit;
    }

    try {
        // Inicia transação para garantir integridade dos dados
        $pdo->beginTransaction();

        // 2. Insere a Comanda
        $stmt = $pdo->prepare("INSERT INTO comandas (cliente, status, data_criacao) VALUES (?, 'aberta', NOW())");
        $stmt->execute([$cliente]);
        $comanda_id = $pdo->lastInsertId();

        // 3. Processa os itens da comanda
        // Percorre o $_POST procurando por campos 'qtd-ID_DO_PRODUTO'
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'qtd-') === 0) {
                $quantidade = (int) $value;

                // Só insere se a quantidade for maior que zero
                if ($quantidade > 0) {
                    $produto_id = str_replace('qtd-', '', $key);

                    // Busca o preço atual do produto
                    $stmtVal = $pdo->prepare("SELECT valor FROM produtos WHERE id = ?");
                    $stmtVal->execute([$produto_id]);
                    $produto = $stmtVal->fetch(PDO::FETCH_ASSOC);

                    if ($produto) {
                        // Grava o item vinculado à comanda
                        $stmtItem = $pdo->prepare("
                            INSERT INTO itens_comanda (comanda_id, produto_id, quantidade, valor_unitario) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmtItem->execute([$comanda_id, $produto_id, $quantidade, $produto['valor']]);
                    }
                }
            }
        }

        // Confirma a transação
        $pdo->commit();

        // Redireciona com sucesso
        header("Location: ../home.php?status=sucesso");
        exit;

    } catch (Exception $e) {
        // Se algo der errado, desfaz tudo
        $pdo->rollBack();
        header("Location: ../home.php?status=erro");
        exit;
    }
}
?>