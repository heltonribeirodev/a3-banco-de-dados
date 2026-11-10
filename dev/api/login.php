<?php
// 1. SILENCIA ERROS: Impede que warnings apareçam na resposta do JSON
error_reporting(0);
ini_set('display_errors', 0);

// 2. BUFFERIZA: Garante que nada saia antes da hora
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'conexao.php';

// Inicializa a variável para evitar o erro de "Undefined variable"
$usuario = null; 

try {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare('SELECT id, senha FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        ob_end_clean(); // Limpa o buffer antes de enviar o sucesso
        echo json_encode(["status" => "sucesso", "mensagem" => "Login aprovado!"]);
    } else {
        ob_end_clean(); // Limpa o buffer antes de enviar o erro
        echo json_encode(["status" => "erro", "mensagem" => "E-mail ou senha incorretos."]);
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "erro", "mensagem" => "Erro no servidor."]);
}
exit;
?>