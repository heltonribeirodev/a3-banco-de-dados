<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null; 
    $nome = $_POST['nome'] ?? '';
    $valor = $_POST['valor'] ?? '0';
    $categoria = $_POST['categoria'] ?? '';

    $caminhoImagem = null;

    // Processamento da imagem
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '-', $nome));
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        
        // Pasta onde o arquivo será salvo no servidor
        $pastaDestino = '../assets/uploads/';
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0777, true);
        }

        $nomeArquivo = 'assets/uploads/' . $slug . '-' . time() . '.' . $extensao;
        $destinoFinal = '../' . $nomeArquivo;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destinoFinal)) {
            $caminhoImagem = $nomeArquivo;
        }
    }

    try {
        if (!empty($id)) {
            // EDITAR
            if ($caminhoImagem) {
                $stmt = $pdo->prepare("UPDATE produtos SET nome=?, valor=?, categoria=?, imagem=? WHERE id=?");
                $stmt->execute([$nome, $valor, $categoria, $caminhoImagem, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE produtos SET nome=?, valor=?, categoria=? WHERE id=?");
                $stmt->execute([$nome, $valor, $categoria, $id]);
            }
            $status = "editado";
        } else {
            // CADASTRAR
            $caminhoImagem = $caminhoImagem ?? 'assets/default.png';
            $stmt = $pdo->prepare("INSERT INTO produtos (nome, valor, categoria, imagem) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $valor, $categoria, $caminhoImagem]);
            $status = "cadastrado";
        }

        header("Location: ../produtos.php?status=" . $status);
        exit;
    } catch (PDOException $e) {
        header("Location: ../produtos.php?status=erro");
        exit;
    }
}
?>