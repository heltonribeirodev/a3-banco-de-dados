<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}
require_once 'api/conexao.php';

// 1. Busca os produtos ordenados (Categoria A-Z, Nome A-Z)
$stmt = $pdo->query("SELECT * FROM produtos ORDER BY categoria ASC, nome ASC");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Busca as categorias também ordenadas alfabeticamente
$stmtCats = $pdo->query("SELECT categoria, COUNT(*) as total FROM produtos GROUP BY categoria ORDER BY categoria ASC");
$categoriasComCount = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$totalGeral = array_sum(array_column($categoriasComCount, 'total'));
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Gestão de Comandas - Produtos</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="home">
    <header class="nav">
        <img src="assets/logo.png" alt="Logo" class="logo-header">

        <!-- Botão hambúrguer (visível só no mobile via CSS) -->
        <button class="btn-hamburguer" aria-label="Abrir menu" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </button>

        <nav id="nav-menu">
            <ul>
                <li><a href="home.php">Comandas</a></li>
                <li><a href="produtos.php" class="select-nav">Produtos</a></li>
                <li><a href="admin.php">Painel Administrativo</a></li>
                <li><a href="logout.php" class="nav-sair">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main class="conteudo">
        <div class="title">
            <h2>Produtos</h2>
            <div class="grupo-acoes">
                <div class="search-container">
                    <input type="text" id="buscar-produto" placeholder="Buscar produtos...">
                    <button class="btn-pesquisar" aria-label="Pesquisar">🔍</button>
                </div>
                <a href="#" class="btn-secundario" onclick="event.preventDefault(); abrirModalProduto()">+ Novo Produto</a>
            </div>
        </div>

        <div class="status">
            <ul>
                <li class="btn-filtro active" data-filter="todos">
                    Todos (<?= $totalGeral ?>)
                </li>

                <?php foreach ($categoriasComCount as $c): ?>
                    <li class="btn-filtro" data-filter="<?= htmlspecialchars($c['categoria']) ?>">
                        <?= htmlspecialchars($c['categoria']) ?> (<?= $c['total'] ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="grid-produtos">
            <?php foreach ($produtos as $p): ?>
                <div class="card-produto" data-categoria="<?= htmlspecialchars($p['categoria']) ?>">
                    <p><strong><?= htmlspecialchars($p['nome']) ?></strong></p>

                    <img src="/saas-gestao-comandas/<?= ltrim(htmlspecialchars($p['imagem']), '/') ?>" alt="Produto"
                        style="max-width: 100px; display: block; margin: 10px auto;"
                        onerror="this.src='/saas-gestao-comandas/assets/default.png';">
                    <p>R$ <?= number_format($p['valor'], 2, ',', '.') ?></p>
                    <div class="card-acoes">
                        <button onclick="editarProduto(<?= (int)$p['id'] ?>)" class="btn-sm">✏️</button>
                        <button onclick="excluirProduto(<?= (int)$p['id'] ?>)" class="btn-sm">🗑️</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="modal-novo-produto" class="modal-overlay">
        <div class="modal-content">
            <h2>Novo Produto</h2>
            <form action="api/salvar_produto.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="produto-id" value="">

                <input type="text" name="nome" placeholder="Nome do Produto" required>
                <input type="number" step="0.01" name="valor" placeholder="Valor" required>
                <input type="text" name="categoria" placeholder="Categoria" required>

                <label>Foto do Produto:</label>
                <input type="file" name="imagem" accept="image/*">

                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" onclick="fecharModalProduto()">Cancelar</button>
                    <button type="submit" class="btn-acao">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-confirmacao" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <p>Tem certeza que deseja excluir este produto?</p>
            <div class="modal-actions">
                <button onclick="fecharConfirmacao()" class="btn-cancelar">Cancelar</button>
                <button id="btn-confirmar-exclusao" class="btn-perigo">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="js/script.js" defer></script>
</body>

</html>