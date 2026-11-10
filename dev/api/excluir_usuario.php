<?php
// api/excluir_usuario.php
session_start();
require_once 'conexao.php';

// Somente admins podem acessar esta API
$stmtPerfil = $pdo->prepare("SELECT tipo_perfil FROM usuarios WHERE id = ?");
$stmtPerfil->execute([$_SESSION['usuario_id'] ?? 0]);
if ($stmtPerfil->fetchColumn() !== 'admin') {
    header("Location: ../home.php");
    exit;
}

$id       = (int) ($_GET['id'] ?? 0);
$redirect = '../admin.php';

if (!$id) {
    header("Location: $redirect?erro=erro_interno");
    exit;
}

// Impede que o admin exclua a própria conta
if ($id === (int) $_SESSION['usuario_id']) {
    header("Location: $redirect?erro=auto_exclusao");
    exit;
}

// Impede excluir o último admin do sistema
$stmtAdmins = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_perfil = 'admin'");
$totalAdmins = (int) $stmtAdmins->fetchColumn();

$stmtTipoPerfil = $pdo->prepare("SELECT tipo_perfil FROM usuarios WHERE id = ?");
$stmtTipoPerfil->execute([$id]);
$perfilAlvo = $stmtTipoPerfil->fetchColumn();

if ($perfilAlvo === 'admin' && $totalAdmins <= 1) {
    header("Location: $redirect?erro=ultimo_admin");
    exit;
}

// Exclui o usuário
try {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: $redirect?sucesso=usuario_excluido");
} catch (Exception $e) {
    header("Location: $redirect?erro=erro_interno");
}
exit;