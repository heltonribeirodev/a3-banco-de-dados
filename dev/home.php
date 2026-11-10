<?php
session_start();
require_once 'api/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM produtos");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categorias = [];
if ($produtos) {
    foreach ($produtos as $p) {
        if (!in_array($p['categoria'], $categorias)) {
            $categorias[] = $p['categoria'];
        }
    }
}

// BUSCA COM O METODO_PAGAMENTO PARA EXIBIR NA HOME
$stmtComandas = $pdo->query("
    SELECT c.id, c.cliente, c.status, c.metodo_pagamento,
           SUM(i.quantidade * i.valor_unitario) as valor_total,
           SUM(i.quantidade) as qtd_produtos
    FROM comandas c
    LEFT JOIN itens_comanda i ON c.id = i.comanda_id
    GROUP BY c.id
    ORDER BY c.data_criacao DESC
");
$comandas = $stmtComandas->fetchAll(PDO::FETCH_ASSOC);

// Busca a contagem de comandas por status de forma explícita
$stmtContagem = $pdo->query("
    SELECT status, COUNT(*) as total 
    FROM comandas 
    GROUP BY status
");
$resultados = $stmtContagem->fetchAll(PDO::FETCH_ASSOC);

// Inicializa o array com ZEROS
$contagens = [
    'aberta' => 0,
    'fiado' => 0,
    'fechada' => 0
];

foreach ($resultados as $res) {
    if (array_key_exists($res['status'], $contagens)) {
        $contagens[$res['status']] = $res['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Gestão de Comandas - Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="home">
    <header class="nav">
        <img src="assets/logo.png" alt="Logo do SaaS Gestão de Comandas" class="logo-header">

        <!-- Botão hambúrguer (visível só no mobile via CSS) -->
        <button class="btn-hamburguer" aria-label="Abrir menu" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </button>

        <nav id="nav-menu">
            <ul>
                <li><a href="home.php" class="select-nav">Comandas</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="admin.php">Painel Administrativo</a></li>
                <li><a href="logout.php" class="nav-sair">Sair</a></li>
            </ul>
        </nav>
    </header>

    <main class="conteudo">
        <div class="title">
            <h2>Comandas</h2>
            <div class="grupo-acoes">
                <div class="search-container">
                    <input type="text" id="buscar-comanda" placeholder="Buscar comandas...">
                    <button class="btn-pesquisar" aria-label="Pesquisar">🔍</button>
                </div>
                <a href="#" class="btn-nova-comanda btn-secundario" onclick="event.preventDefault(); abrirModal();">
                    + Nova Comanda
                </a>
            </div>
        </div>

        <div class="status">
            <ul>
                <li class="btn-filtro active" data-filter="aberta">
                    <span class="status-aberta"></span> Abertas (<?= $contagens['aberta'] ?>)
                </li>
                <li class="btn-filtro" data-filter="fiado">
                    <span class="status-pendente"></span> Fiado (<?= $contagens['fiado'] ?>)
                </li>
                <li class="btn-filtro" data-filter="fechada">
                    <span class="status-fechada"></span> Fechadas (<?= $contagens['fechada'] ?>)
                </li>
            </ul>
        </div>

        <div class="container-comandas">
            <?php foreach ($comandas as $c): ?>
                <div class="cartao-comanda" data-status="<?= htmlspecialchars($c['status']) ?>">

                    <h3>Comanda #<?= $c['id'] ?></h3>
                    <p><strong>Cliente:</strong>
                        <?= !empty($c['cliente']) ? htmlspecialchars($c['cliente']) : 'Não informado' ?></p>
                    <p><strong>Status:</strong> <span class="status-text"><?= htmlspecialchars($c['status']) ?></span></p>
                    <p>Itens: <?= $c['qtd_produtos'] ?? 0 ?> | Total: <span class="destaque-valor">R$
                            <?= number_format($c['valor_total'] ?? 0, 2, ',', '.') ?></span></p>

                    <button type="button" class="btn-editar"
                        onclick="abrirAcoes(<?= (int)$c['id'] ?>, '<?= addslashes(htmlspecialchars($c['cliente'] ?? '', ENT_QUOTES, 'UTF-8')) ?>', '<?= htmlspecialchars($c['status'] ?? '') ?>', '<?= htmlspecialchars($c['metodo_pagamento'] ?? '') ?>')">
                        Ações / Pagamento
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="modal-comanda" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <form id="form-nova-comanda" action="api/salvar_comanda.php" method="POST">
                <div id="step-1" class="new-comanda">
                    <h2>Nova Comanda (1/2)</h2>
                    <div id="erro-cliente" class="balao-erro" style="display: none;"></div>
                    <input type="text" id="cliente" name="cliente" placeholder="Nome do Cliente ou Número da Mesa"
                        required>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
                        <button type="button" class="btn-acao" onclick="proximoPasso()">Próximo</button>
                    </div>
                </div>

                <div id="step-2" style="display: none;" class="new-comanda">
                    <h2>Nova Comanda (2/2)</h2>
                    <div class="status-filtros" style="margin-bottom: 10px;">
                        <button type="button" class="btn-filtro-modal active"
                            onclick="filtrarProdutos('todos')">Todos</button>
                        <?php foreach ($categorias as $cat): ?>
                            <button type="button" class="btn-filtro-modal"
                                onclick="filtrarProdutos('<?= htmlspecialchars($cat, ENT_QUOTES) ?>')">
                                <?= htmlspecialchars($cat) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid-produtos-comanda">
                        <?php foreach ($produtos as $p): ?>
                            <div class="card-produto-sel" data-categoria="<?= htmlspecialchars($p['categoria']) ?>">
                                <img src="<?= htmlspecialchars($p['imagem']) ?>" alt="Produto">
                                <p class="nome-produto"><?= htmlspecialchars($p['nome']) ?></p>
                                <p class="valor-produto">R$ <?= number_format($p['valor'], 2, ',', '.') ?></p>
                                <div class="controles-qtd">
                                    <button type="button" class="btn-qtd"
                                        onclick="ajustarQtd(<?= $p['id'] ?>, -1)">-</button>
                                    <input type="number" name="qtd-<?= $p['id'] ?>" id="qtd-<?= $p['id'] ?>" value="0"
                                        min="0">
                                    <button type="button" class="btn-qtd"
                                        onclick="ajustarQtd(<?= $p['id'] ?>, 1)">+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="modal-actions" style="margin-top: 15px;">
                        <button type="button" class="btn-cancelar" onclick="voltarPasso()">Voltar</button>
                        <button type="submit" class="btn-acao">Salvar Comanda</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-acoes" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h2>Comanda #<span id="num-comanda"></span> - <span id="nome-cliente"></span></h2>

            <div id="visualizacao-conta">
                <div id="lista-itens-edit"></div>
                <div class="modal-actions">
                    <button id="btn-editar-produtos" class="btn-acao"
                        onclick="abrirModalEdicao(document.getElementById('num-comanda').innerText)">
                        Editar / Adicionar Produtos
                    </button>
                    <button class="btn-acao" onclick="alternarPagamento(true)">Pagamento</button>
                    <button onclick="fecharModalAcoes()" class="btn-cancelar">Fechar</button>
                </div>
            </div>

            <div id="menu-pagamento" style="display:none;">
                <h3>Escolha a forma de pagamento:</h3>
                <div class="modal-actions">
                    <button class="btn-pagar" data-metodo="pix" onclick="prepararPagamento('pix')">Pix</button>
                    <button class="btn-pagar" data-metodo="dinheiro"
                        onclick="prepararPagamento('dinheiro')">Dinheiro</button>
                    <button class="btn-pagar" data-metodo="cartao_debito"
                        onclick="prepararPagamento('cartao_debito')">Débito</button>
                    <button class="btn-pagar" data-metodo="cartao_credito"
                        onclick="prepararPagamento('cartao_credito')">Crédito</button>
                    <button class="btn-pagar" data-metodo="fiado" onclick="prepararPagamento('fiado')">Fiado</button>
                    <button class="btn-cancelar" onclick="alternarPagamento(false)">Voltar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modal-confirmacao-pagamento" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h3>Confirmar Pagamento</h3>
            <p>Deseja realmente confirmar o pagamento via <strong id="metodo-selecionado"></strong>?</p>
            <div class="modal-actions">
                <button onclick="executarPagamento()" class="btn-acao">Sim, confirmar</button>
                <button onclick="document.getElementById('modal-confirmacao-pagamento').style.display='none'"
                    class="btn-cancelar">Voltar</button>
            </div>
        </div>
    </div>

    <div id="modal-editar-itens" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h2>Editar Itens da Comanda #<span id="edit-num-comanda"></span></h2>

            <form id="form-editar-itens" action="api/salvar_edicao_comanda.php" method="POST">
                <input type="hidden" name="comanda_id" id="edit-comanda-id">

                <div id="filtros-edicao" class="status-filtros" style="margin-bottom: 10px;">
                    <!-- Preenchido dinamicamente pelo JS -->
                </div>

                <div id="grid-itens-edicao" class="grid-produtos-comanda">
                    <!-- Preenchido dinamicamente pelo JS -->
                </div>

                <div class="modal-actions" style="margin-top: 15px;">
                    <button type="button" class="btn-cancelar" onclick="fecharModalEdicao()">Fechar</button>
                    <button type="submit" class="btn-acao">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/script.js" defer></script>
</body>

</html>