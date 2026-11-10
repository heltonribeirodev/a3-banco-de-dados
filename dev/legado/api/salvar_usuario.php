<?php
// api/salvar_usuario.php
session_start();
require_once 'conexao.php';

// Somente admins podem acessar esta API
$stmtPerfil = $pdo->prepare("SELECT tipo_perfil FROM usuarios WHERE id = ?");
$stmtPerfil->execute([$_SESSION['usuario_id'] ?? 0]);
if ($stmtPerfil->fetchColumn() !== 'admin') {
    header("Location: ../home.php");
    exit;
}

$id          = trim($_POST['id']           ?? '');
$nome        = trim($_POST['nome']         ?? '');
$email       = trim($_POST['email']        ?? '');
$senha       = trim($_POST['senha']        ?? '');
$tipo_perfil = trim($_POST['tipo_perfil']  ?? 'atendente');
$status      = trim($_POST['status_conta'] ?? 'ativo');

$redirect = '../admin.php';

// ── Validações ──────────────────────────────────────────
if (empty($nome) || empty($email)) {
    header("Location: $redirect?erro=campos_obrigatorios");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: $redirect?erro=email_invalido");
    exit;
}

$isNovo = empty($id);

// Senha obrigatória somente na criação
if ($isNovo && empty($senha)) {
    header("Location: $redirect?erro=senha_obrigatoria");
    exit;
}

// Verifica e-mail duplicado (ignora o próprio usuário ao editar)
$sqlDup  = $isNovo
    ? "SELECT COUNT(*) FROM usuarios WHERE email = ?"
    : "SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?";
$stmtDup = $pdo->prepare($sqlDup);
$stmtDup->execute($isNovo ? [$email] : [$email, $id]);
if ((int) $stmtDup->fetchColumn() > 0) {
    header("Location: $redirect?erro=email_duplicado");
    exit;
}

// ── Salvar ───────────────────────────────────────────────
try {
    if ($isNovo) {
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nome, email, senha, tipo_perfil, status_conta)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $email, $hash, $tipo_perfil, $status]);
    } else {
        if (!empty($senha)) {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nome = ?, email = ?, senha = ?, tipo_perfil = ?, status_conta = ?
                WHERE id = ?
            ");
            $stmt->execute([$nome, $email, $hash, $tipo_perfil, $status, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET nome = ?, email = ?, tipo_perfil = ?, status_conta = ?
                WHERE id = ?
            ");
            $stmt->execute([$nome, $email, $tipo_perfil, $status, $id]);
        }
    }

    header("Location: $redirect?sucesso=usuario_salvo");
} catch (Exception $e) {
    header("Location: $redirect?erro=erro_interno");
}
exit;