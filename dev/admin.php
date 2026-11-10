<?php
session_start();
require_once 'api/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// RESTRIÇÃO PARE DEIXAR ACESSO APENAS PARA ADMINS
$stmtPerfil = $pdo->prepare("SELECT tipo_perfil FROM usuarios WHERE id = ?");
$stmtPerfil->execute([$_SESSION['usuario_id']]);
$perfilAtual = $stmtPerfil->fetchColumn();

if ($perfilAtual !== 'admin') {
    header("Location: home.php?erro=acesso_negado");
    exit;
}

$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim    = $_GET['data_fim']    ?? '';


$whereDatas  = '';
$paramsDatas = [];

if (!empty($dataInicio) && !empty($dataFim)) {
    $whereDatas  = "AND DATE(c.data_criacao) BETWEEN ? AND ?";
    $paramsDatas = [$dataInicio, $dataFim];
} elseif (!empty($dataInicio)) {
    $whereDatas  = "AND DATE(c.data_criacao) >= ?";
    $paramsDatas = [$dataInicio];
} elseif (!empty($dataFim)) {
    $whereDatas  = "AND DATE(c.data_criacao) <= ?";
    $paramsDatas = [$dataFim];
}

// Contagem por status
$sqlStatus = "SELECT status, COUNT(*) as total FROM comandas c WHERE 1=1 $whereDatas GROUP BY status";
$stmtStatus = $pdo->prepare($sqlStatus);
$stmtStatus->execute($paramsDatas);
$contagens = ['aberta' => 0, 'fiado' => 0, 'fechada' => 0];
foreach ($stmtStatus->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (array_key_exists($r['status'], $contagens))
        $contagens[$r['status']] = (int) $r['total'];
}
$totalComandas = array_sum($contagens);

// Totais financeiros
$sqlFin = "
    SELECT c.status, COALESCE(SUM(i.quantidade * i.valor_unitario), 0) as total
    FROM comandas c
    LEFT JOIN itens_comanda i ON c.id = i.comanda_id
    WHERE 1=1 $whereDatas
    GROUP BY c.status
";
$stmtFin = $pdo->prepare($sqlFin);
$stmtFin->execute($paramsDatas);
$financeiro = ['aberta' => 0, 'fiado' => 0, 'fechada' => 0];
foreach ($stmtFin->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (array_key_exists($r['status'], $financeiro))
        $financeiro[$r['status']] = (float) $r['total'];
}
$totalFaturado = $financeiro['fechada'];
$totalFiado    = $financeiro['fiado'];
$totalEmAberto = $financeiro['aberta'];

// Ticket médio
$sqlTicket = "
    SELECT AVG(sub.total) FROM (
        SELECT c.id, COALESCE(SUM(i.quantidade * i.valor_unitario), 0) as total
        FROM comandas c
        LEFT JOIN itens_comanda i ON c.id = i.comanda_id
        WHERE c.status = 'fechada' $whereDatas
        GROUP BY c.id HAVING total > 0
    ) sub
";
$stmtTicket = $pdo->prepare($sqlTicket);
$stmtTicket->execute($paramsDatas);
$ticketMedio = (float) ($stmtTicket->fetchColumn() ?? 0);

// Top 5 produtos mais vendidos
$sqlTop = "
    SELECT p.nome, p.imagem,
           SUM(i.quantidade) as total_vendido,
           SUM(i.quantidade * i.valor_unitario) as receita
    FROM itens_comanda i
    JOIN produtos p ON i.produto_id = p.id
    JOIN comandas c ON i.comanda_id = c.id
    WHERE 1=1 $whereDatas
    GROUP BY p.id
    ORDER BY total_vendido DESC
    LIMIT 6
";
$stmtTop = $pdo->prepare($sqlTop);
$stmtTop->execute($paramsDatas);
$topProdutos = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

// Últimas 5 comandas
$sqlRecentes = "
    SELECT c.id, c.cliente, c.status, c.data_criacao,
           COALESCE(SUM(i.quantidade * i.valor_unitario), 0) as valor_total
    FROM comandas c
    LEFT JOIN itens_comanda i ON c.id = i.comanda_id
    WHERE 1=1 $whereDatas
    GROUP BY c.id
    ORDER BY c.data_criacao DESC
    LIMIT 8
";
$stmtRecentes = $pdo->prepare($sqlRecentes);
$stmtRecentes->execute($paramsDatas);
$recentesComandas = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);

// Gestão de Usuários
$stmtUsers = $pdo->query("
    SELECT id, nome, email, tipo_perfil, status_conta, criado_em
    FROM usuarios ORDER BY id DESC
");
$usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$mensagens = [
    'usuario_salvo'    => ['tipo' => 'sucesso', 'texto' => 'Usuário salvo com sucesso!'],
    'usuario_excluido' => ['tipo' => 'sucesso', 'texto' => 'Usuário excluído com sucesso!'],
    'campos_obrigatorios' => ['tipo' => 'erro', 'texto' => 'Preencha todos os campos obrigatórios.'],
    'email_invalido'   => ['tipo' => 'erro', 'texto' => 'E-mail inválido.'],
    'email_duplicado'  => ['tipo' => 'erro', 'texto' => 'Este e-mail já está cadastrado.'],
    'senha_obrigatoria'=> ['tipo' => 'erro', 'texto' => 'A senha é obrigatória para novos usuários.'],
    'auto_exclusao'    => ['tipo' => 'erro', 'texto' => 'Você não pode excluir a própria conta.'],
    'ultimo_admin'     => ['tipo' => 'erro', 'texto' => 'Não é possível excluir o único administrador do sistema.'],
    'erro_interno'     => ['tipo' => 'erro', 'texto' => 'Ocorreu um erro interno. Tente novamente.'],
];
$feedbackKey = $_GET['sucesso'] ?? $_GET['erro'] ?? '';
$feedback    = $mensagens[$feedbackKey] ?? null;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Gestão de Comandas - Painel Administrativo</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="home">

<header class="nav">
    <img src="assets/logo.png" alt="Logo" class="logo-header">

    <button class="btn-hamburguer" aria-label="Abrir menu" onclick="toggleMenu()">
        <span></span><span></span><span></span>
    </button>

    <nav id="nav-menu">
        <ul>
            <li><a href="home.php">Comandas</a></li>
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="admin.php" class="select-nav">Painel Administrativo</a></li>
            <li><a href="logout.php" class="nav-sair">Sair</a></li>
        </ul>
    </nav>
</header>

<main class="conteudo admin-conteudo">

    <div class="title" style="margin-bottom: 20px;">
        <h2>Painel Administrativo</h2>
    </div>

    <?php if ($feedback): ?>
        <div class="admin-feedback admin-feedback--<?= $feedback['tipo'] ?>">
            <?= $feedback['texto'] ?>
        </div>
    <?php endif; ?>

    <form method="GET" action="admin.php" class="admin-filtro-datas">
        <label>
            <span>De</span>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
        </label>
        <label>
            <span>Até</span>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
        </label>
        <button type="submit" class="btn-filtro-data">Filtrar</button>
        <?php if ($dataInicio || $dataFim): ?>
            <a href="admin.php" class="btn-limpar-filtro">✕ Limpar filtro</a>
        <?php endif; ?>
        <?php if ($dataInicio || $dataFim): ?>
            <span class="admin-filtro-label">
                Exibindo dados de <?= $dataInicio ? date('d/m/Y', strtotime($dataInicio)) : '—' ?>
                até <?= $dataFim ? date('d/m/Y', strtotime($dataFim)) : '—' ?>
            </span>
        <?php endif; ?>
    </form>

    <div class="admin-cards">

        <div class="admin-card admin-card--blue">
            <div class="admin-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="admin-card__label">Total de Comandas</p>
            <p class="admin-card__value"><?= $totalComandas ?></p>
            <p class="admin-card__sub">Abertas: <?= $contagens['aberta'] ?> &nbsp;|&nbsp; Fiado: <?= $contagens['fiado'] ?> &nbsp;|&nbsp; Fechadas: <?= $contagens['fechada'] ?></p>
        </div>

        <div class="admin-card admin-card--green">
            <div class="admin-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="admin-card__label">Total Faturado</p>
            <p class="admin-card__value">R$ <?= number_format($totalFaturado, 2, ',', '.') ?></p>
            <p class="admin-card__sub">Comandas fechadas</p>
        </div>

        <div class="admin-card admin-card--red">
            <div class="admin-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <p class="admin-card__label">Total Fiado</p>
            <p class="admin-card__value">R$ <?= number_format($totalFiado, 2, ',', '.') ?></p>
            <p class="admin-card__sub"><?= $contagens['fiado'] ?> comanda(s) pendente(s)</p>
        </div>

        <div class="admin-card admin-card--orange">
            <div class="admin-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <p class="admin-card__label">Em Aberto</p>
            <p class="admin-card__value">R$ <?= number_format($totalEmAberto, 2, ',', '.') ?></p>
            <p class="admin-card__sub"><?= $contagens['aberta'] ?> comanda(s) ativa(s)</p>
        </div>

        <div class="admin-card admin-card--purple">
            <div class="admin-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <p class="admin-card__label">Ticket Médio</p>
            <p class="admin-card__value">R$ <?= number_format($ticketMedio, 2, ',', '.') ?></p>
            <p class="admin-card__sub">Por comanda fechada</p>
        </div>

    </div>

    <!-- TOP PRODUTOS + ÚLTIMAS COMANDAS -->
    <div class="admin-row">

        <div class="admin-section">
            <h3 class="admin-section__title">🏆 Produtos Mais Vendidos</h3>
            <div class="admin-top-produtos">
                <?php foreach ($topProdutos as $i => $prod): ?>
                    <div class="admin-top-item">
                        <span class="admin-top-rank"><?= $i + 1 ?>º</span>
                        <img src="<?= htmlspecialchars($prod['imagem'] ?? '') ?>" alt=""
                            onerror="this.style.display='none'">
                        <div class="admin-top-info">
                            <p class="admin-top-nome"><?= htmlspecialchars($prod['nome']) ?></p>
                            <p class="admin-top-qtd"><?= (int)$prod['total_vendido'] ?> unid. vendidas</p>
                        </div>
                        <span class="admin-top-receita">R$ <?= number_format($prod['receita'], 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($topProdutos)): ?>
                    <p style="color:#aaa; text-align:center; padding:20px;">Nenhum produto vendido no período.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-section">
            <h3 class="admin-section__title">🕐 Últimas Comandas</h3>
            <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentesComandas as $c): ?>
                        <tr>
                            <td data-label="#"><?= $c['id'] ?></td>
                            <td data-label="Cliente"><?= htmlspecialchars($c['cliente'] ?: 'Não informado') ?></td>
                            <td data-label="Status">
                                <span class="badge-status badge-status--<?= $c['status'] ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td data-label="Data" style="white-space:nowrap; color:#6b7280; font-size:0.85rem;">
                                <?= date('d/m/Y', strtotime($c['data_criacao'])) ?>
                            </td>
                            <td data-label="Total"><strong>R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentesComandas)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#aaa;">Nenhuma comanda no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
            <a href="home.php" class="admin-link-ver-todas">Ver todas as comandas →</a>
        </div>

    </div>

    <!-- GESTÃO DE USUÁRIOS -->
    <div class="admin-section" style="margin-top: 28px;">
        <div class="admin-section__header">
            <h3 class="admin-section__title">👤 Usuários do Sistema</h3>
            <button class="btn-secundario btn-new-user" onclick="abrirModalNovoUsuario()">
                + Novo Usuário
            </button>
        </div>

        <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Cadastrado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td data-label="#"><?= $u['id'] ?></td>
                        <td data-label="Nome"><?= htmlspecialchars($u['nome']) ?></td>
                        <td data-label="E-mail"><?= htmlspecialchars($u['email']) ?></td>
                        <td data-label="Perfil">
                            <span class="badge-status <?= $u['tipo_perfil'] === 'admin' ? 'badge-status--aberta' : 'badge-status--usuario' ?>">
                                <?= ucfirst(htmlspecialchars($u['tipo_perfil'])) ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <span class="badge-status <?= $u['status_conta'] === 'ativo' ? 'badge-status--aberta' : 'badge-status--fiado' ?>">
                                <?= ucfirst(htmlspecialchars($u['status_conta'])) ?>
                            </span>
                        </td>
                        <td data-label="Cadastrado em" style="white-space:nowrap; color:#6b7280; font-size:0.85rem;">
                            <?= date('d/m/Y H:i', strtotime($u['criado_em'])) ?>
                        </td>
                        <td data-label="Ações">
                            <button class="btn-acao-tabela btn-acao-tabela--editar"
                                onclick="abrirModalEditarUsuario(
                                    <?= $u['id'] ?>,
                                    '<?= addslashes($u['nome']) ?>',
                                    '<?= addslashes($u['email']) ?>',
                                    '<?= $u['tipo_perfil'] ?>',
                                    '<?= $u['status_conta'] ?>'
                                )">Editar</button>
                            <button class="btn-acao-tabela btn-acao-tabela--excluir"
                                onclick="confirmarExclusaoUsuario(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>')">
                                Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="7" style="text-align:center; color:#aaa;">Nenhum usuário cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</main>

<!-- MODAL: NOVO / EDITAR USUÁRIO -->
<div id="modal-usuario" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width: 480px;">
        <h2 id="modal-usuario-titulo" style="font-size:1.2rem; font-weight:700; margin-bottom:20px;">Novo Usuário</h2>
        <form id="form-usuario" action="api/salvar_usuario.php" method="POST">
            <input type="hidden" name="id" id="usuario-id">

            <div class="field" style="margin-bottom:14px;">
                <label style="font-size:.875rem; color:#6b7280; display:block; margin-bottom:6px;">Nome</label>
                <input type="text" id="usuario-nome" name="nome" placeholder="Nome completo" required
                    style="width:100%; padding:10px; border:1px solid #e0e0e0; border-radius:8px; font-size:1rem;">
            </div>

            <div class="field" style="margin-bottom:14px;">
                <label style="font-size:.875rem; color:#6b7280; display:block; margin-bottom:6px;">E-mail</label>
                <input type="email" id="usuario-email" name="email" placeholder="email@exemplo.com" required
                    style="width:100%; padding:10px; border:1px solid #e0e0e0; border-radius:8px; font-size:1rem;">
            </div>

            <div class="field" style="margin-bottom:14px;">
                <label style="font-size:.875rem; color:#6b7280; display:block; margin-bottom:6px;">
                    Senha <span id="label-senha-hint" style="color:#aaa;">(deixe em branco para não alterar)</span>
                </label>
                <input type="password" id="usuario-senha" name="senha" placeholder="••••••••"
                    style="width:100%; padding:10px; border:1px solid #e0e0e0; border-radius:8px; font-size:1rem;">
            </div>

            <div style="display:flex; gap:14px; margin-bottom:20px;">
                <div style="flex:1;">
                    <label style="font-size:.875rem; color:#6b7280; display:block; margin-bottom:6px;">Perfil</label>
                    <select id="usuario-perfil" name="tipo_perfil"
                        style="width:100%; padding:10px; border:1px solid #e0e0e0; border-radius:8px; font-size:1rem; background:#fff;">
                        <option value="atendente">Atendente</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="font-size:.875rem; color:#6b7280; display:block; margin-bottom:6px;">Status</label>
                    <select id="usuario-status" name="status_conta"
                        style="width:100%; padding:10px; border:1px solid #e0e0e0; border-radius:8px; font-size:1rem; background:#fff;">
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancelar" onclick="fecharModalUsuario()">Cancelar</button>
                <button type="submit" class="btn-acao">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CONFIRMAR EXCLUSÃO -->
<div id="modal-confirmar-exclusao-usuario" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width: 400px;">
        <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:12px;">Confirmar Exclusão</h3>
        <p style="color:#555; margin-bottom:20px;">
            Deseja excluir o usuário <strong id="nome-usuario-excluir"></strong>? Esta ação não pode ser desfeita.
        </p>
        <div class="modal-actions">
            <button class="btn-cancelar" onclick="fecharModalConfirmarExclusao()">Cancelar</button>
            <button class="btn-acao" style="background:#dc3545;" onclick="executarExclusaoUsuario()">Sim, excluir</button>
        </div>
    </div>
</div>

<script src="js/admin.js" defer></script>
<script src="js/script.js" defer></script>
</body>
</html>