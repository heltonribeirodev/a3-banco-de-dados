<?php
require_once 'conexao.php';
$comanda_id = $_POST['comanda_id'];

foreach ($_POST as $key => $value) {
    if (strpos($key, 'qtd-') === 0 && $value > 0) {
        $produto_id = str_replace('qtd-', '', $key);
        $qtd = (int)$value;
        
        // Pega preço atual
        $stmt = $pdo->prepare("SELECT valor FROM produtos WHERE id = ?");
        $stmt->execute([$produto_id]);
        $preco = $stmt->fetchColumn();
        
        // Insere
        $stmt = $pdo->prepare("INSERT INTO itens_comanda (comanda_id, produto_id, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
        $stmt->execute([$comanda_id, $produto_id, $qtd, $preco]);
    }
}
header("Location: ../home.php?status=sucesso");
?>