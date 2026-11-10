<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comanda_id = $_POST['comanda_id'];
    $cliente = $_POST['cliente'];

    try {
        $pdo->beginTransaction();

        // 1. Atualiza nome do cliente
        $pdo->prepare("UPDATE comandas SET cliente = ? WHERE id = ?")->execute([$cliente, $comanda_id]);

        // 2. Apaga itens antigos
        $pdo->prepare("DELETE FROM itens_comanda WHERE comanda_id = ?")->execute([$comanda_id]);

        // 3. Reinsere novos itens
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'qtd-') === 0 && (int)$value > 0) {
                $produto_id = str_replace('qtd-', '', $key);
                $quantidade = (int)$value;
                $val = $pdo->prepare("SELECT valor FROM produtos WHERE id = ?");
                $val->execute([$produto_id]);
                $p = $val->fetch(PDO::FETCH_ASSOC);
                
                $pdo->prepare("INSERT INTO itens_comanda (comanda_id, produto_id, quantidade, valor_unitario) VALUES (?, ?, ?, ?)")
                    ->execute([$comanda_id, $produto_id, $quantidade, $p['valor']]);
            }
        }

        $pdo->commit();
        header("Location: ../home.php?status=sucesso");
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: ../home.php?status=erro");
    }
}