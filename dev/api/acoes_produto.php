<?php
require_once 'conexao.php';

$acao = $_GET['acao'] ?? '';
$id = $_GET['id'] ?? 0;

if ($acao === 'excluir' && $id > 0) {
    // 1. Primeiro, buscamos o caminho da imagem para poder apagá-la
    $stmt = $pdo->prepare("SELECT imagem FROM produtos WHERE id = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($produto) {
        // Apaga o arquivo físico da pasta, se não for a imagem padrão
        $caminhoImagem = '../' . $produto['imagem'];
        if (file_exists($caminhoImagem) && $produto['imagem'] !== 'assets/default.png') {
            unlink($caminhoImagem);
        }

        // 2. Agora deletamos o registro do banco de dados
        $stmtDelete = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
        $stmtDelete->execute([$id]);
    }
    
    // 3. Redireciona de volta com status de sucesso
    header("Location: ../produtos.php?status=sucesso");
    exit;
}
?>