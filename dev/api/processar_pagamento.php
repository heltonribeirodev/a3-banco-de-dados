<?php
header('Content-Type: application/json');
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comanda_id = $_POST['comanda_id'] ?? 0;
    $metodo = $_POST['metodo'] ?? '';

    if ($comanda_id > 0 && !empty($metodo)) {
        
        // Regra estrita: 
        // Apenas 'pix', 'dinheiro', 'cartao_credito' e 'cartao_debito' alteram o status para 'fechada'.
        $metodos_fechamento = ['pix', 'dinheiro', 'cartao_debito', 'cartao_credito'];
        
        if ($metodo === 'fiado') {
            $novo_status = 'fiado';
        } elseif (in_array($metodo, $metodos_fechamento)) {
            $novo_status = 'fechada';
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Método de pagamento não reconhecido.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE comandas 
                SET status = ?, 
                    metodo_pagamento = ?, 
                    data_pagamento = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$novo_status, $metodo, $comanda_id]);

            echo json_encode(['status' => 'sucesso']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'erro', 'mensagem' => 'Erro interno: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos recebidos pelo servidor.']);
    }
}
?>