<?php
// api/get_comanda.php
require_once 'conexao.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT 
        i.id           AS id_linha,
        i.produto_id, 
        i.quantidade, 
        i.valor_unitario,
        i.data_criacao AS data_adicao,   -- data de cada linha individualmente
        p.nome         AS nome_produto 
    FROM itens_comanda i
    JOIN produtos p ON i.produto_id = p.id
    WHERE i.comanda_id = ?
    ORDER BY i.data_criacao ASC, i.id ASC
");
$stmt->execute([$id]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['itens' => $itens]);