<?php
// api/salvar_edicao_comanda.php
require_once 'conexao.php';

$comanda_id = $_POST['comanda_id'] ?? 0;

if (!$comanda_id) {
    header('Location: ../home.php?status=erro');
    exit;
}

$pdo->beginTransaction();

try {
    // 1. Busca TODOS os itens existentes na comanda, agrupados por produto_id
    //    Pode haver múltiplas linhas do mesmo produto (de dias diferentes)
    $stmtAtuais = $pdo->prepare("
        SELECT id, produto_id, quantidade, valor_unitario 
        FROM itens_comanda 
        WHERE comanda_id = ? 
        ORDER BY data_criacao ASC
    ");
    $stmtAtuais->execute([$comanda_id]);
    $todasAsLinhas = $stmtAtuais->fetchAll(PDO::FETCH_ASSOC);

    // Agrupa por produto_id: soma a quantidade total e guarda todas as linhas
    $itensExistentes = [];
    foreach ($todasAsLinhas as $linha) {
        $pid = $linha['produto_id'];
        if (!isset($itensExistentes[$pid])) {
            $itensExistentes[$pid] = [
                'quantidade_total' => 0,
                'linhas'           => [],
                'valor_unitario'   => $linha['valor_unitario'],
            ];
        }
        $itensExistentes[$pid]['quantidade_total'] += (int)$linha['quantidade'];
        $itensExistentes[$pid]['linhas'][]           = $linha;
    }

    // 2. Busca os preços atuais dos produtos (para novas inserções)
    $stmtProdutos = $pdo->query("SELECT id, valor FROM produtos");
    $precosAtuais = [];
    foreach ($stmtProdutos->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $precosAtuais[$p['id']] = $p['valor'];
    }

    // Statements reutilizáveis
    $stmtUpdate = $pdo->prepare("UPDATE itens_comanda SET quantidade = ? WHERE id = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO itens_comanda (comanda_id, produto_id, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
    $stmtDelete = $pdo->prepare("DELETE FROM itens_comanda WHERE id = ?");

    // 3. Processa o POST
    $produtosProcessados = [];

    foreach ($_POST as $campo => $valor) {
        if (strpos($campo, 'qtd-') !== 0) continue;

        $produto_id       = (int) str_replace('qtd-', '', $campo);
        $qtd_nova_total   = (int) $valor;
        $produtosProcessados[] = $produto_id;

        if (isset($itensExistentes[$produto_id])) {
            $qtd_atual_total = $itensExistentes[$produto_id]['quantidade_total'];
            $linhas          = $itensExistentes[$produto_id]['linhas'];

            if ($qtd_nova_total === 0) {
                // Zerar: remove todas as linhas deste produto
                foreach ($linhas as $linha) {
                    $stmtDelete->execute([$linha['id']]);
                }

            } elseif ($qtd_nova_total < $qtd_atual_total) {
                // DIMINUIU: reduz a quantidade na última linha
                // Se a última linha ficar com 0, deleta ela
                $diferenca = $qtd_atual_total - $qtd_nova_total;
                $linhasReverso = array_reverse($linhas);

                foreach ($linhasReverso as $linha) {
                    if ($diferenca <= 0) break;

                    $qtd_linha = (int)$linha['quantidade'];
                    if ($diferenca >= $qtd_linha) {
                        // Remove esta linha inteira
                        $stmtDelete->execute([$linha['id']]);
                        $diferenca -= $qtd_linha;
                    } else {
                        // Reduz a quantidade desta linha
                        $stmtUpdate->execute([$qtd_linha - $diferenca, $linha['id']]);
                        $diferenca = 0;
                    }
                }

            } elseif ($qtd_nova_total > $qtd_atual_total) {
                // AUMENTOU: insere uma NOVA linha com a diferença e data de hoje
                // As linhas antigas ficam intactas com suas datas originais
                $diferenca = $qtd_nova_total - $qtd_atual_total;
                $preco     = $precosAtuais[$produto_id] ?? $itensExistentes[$produto_id]['valor_unitario'];
                $stmtInsert->execute([$comanda_id, $produto_id, $diferenca, $preco]);
            }
            // Se qtd_nova_total === qtd_atual_total: não faz nada

        } else {
            // Produto novo na comanda
            if ($qtd_nova_total > 0 && isset($precosAtuais[$produto_id])) {
                $stmtInsert->execute([
                    $comanda_id,
                    $produto_id,
                    $qtd_nova_total,
                    $precosAtuais[$produto_id]
                ]);
            }
        }
    }

    // 4. Remove produtos que existiam no banco mas não vieram no POST
    foreach ($itensExistentes as $prod_id => $dados) {
        if (!in_array($prod_id, $produtosProcessados)) {
            foreach ($dados['linhas'] as $linha) {
                $stmtDelete->execute([$linha['id']]);
            }
        }
    }

    $pdo->commit();
    header('Location: ../home.php?status=sucesso');

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro ao salvar comanda: " . $e->getMessage());
    header('Location: ../home.php?status=erro');
}
exit;