<?php
// Arquivo: api/logout.php

session_start();

// Limpa todas as variáveis da sessão
session_unset();

// Destrói a sessão no servidor
session_destroy();

// Redireciona para a tela de login na raiz do projeto
header("Location: ../index.php");
exit;
?>