<?php
/**
 * Tabela comandas.
 * Regras: o atendente é o usuário do token; o total vem dos itens; a comanda só
 * fecha quando o valor pago cobre o total; comanda com pagamento não é cancelada.
 */
class ComandasResource extends Resource
{
    protected string $tabela = 'comandas';
    protected array $campos = [
        'cliente_id' => ['tipo' => 'fk', 'tabela' => 'clientes', 'obrigatorio' => true],
        'status'     => ['tipo' => 'enum', 'valores' => ['aberta', 'fechada', 'fiado', 'cancelada'],
                         'descricao' => 'Na criação é sempre "aberta"'],
    ];
    protected array $extras = ['usuario_id', 'aberta_em', 'fechada_em'];
    protected array $filtros = [
        'status' => ['t.status', '='], 'cliente_id' => ['t.cliente_id', '='], 'usuario_id' => ['t.usuario_id', '='],
        'data_inicio' => ['t.aberta_em', '>='], 'data_fim' => ['t.aberta_em', '<='],
    ];
    protected array $ordenaveis = ['id', 'status', 'aberta_em', 'fechada_em', 'cliente_id'];
    protected array $permissoes = ['listar' => 'auth', 'ver' => 'auth', 'criar' => 'auth', 'atualizar' => 'auth', 'excluir' => 'admin'];

    protected function origem(): string
    {
        return 'comandas t JOIN clientes cl ON cl.id = t.cliente_id JOIN vw_resumo_comandas v ON v.comanda_id = t.id';
    }

    protected function colunas(): string
    {
        return parent::colunas() . ', cl.nome AS cliente_nome, v.total, v.pago, (v.total - v.pago) AS saldo';
    }

    protected function formatar(array $row): array
    {
        $row = parent::formatar($row);
        foreach (['total', 'pago', 'saldo'] as $k) { $row[$k] = (float)$row[$k]; }
        return $row;
    }

    protected function detalhar(array $c): array
    {
        $st = $this->pdo->prepare(
            'SELECT i.id, i.produto_id, p.nome AS produto_nome, i.quantidade, i.valor_unitario,
                    (i.quantidade * i.valor_unitario) AS subtotal
               FROM itens_comanda i JOIN produtos p ON p.id = i.produto_id
              WHERE i.comanda_id = ? ORDER BY i.id'
        );
        $st->execute([$c['id']]);
        $c['itens'] = array_map(fn($i) => [
            'id' => (int)$i['id'], 'produto_id' => (int)$i['produto_id'], 'produto_nome' => $i['produto_nome'],
            'quantidade' => (int)$i['quantidade'], 'valor_unitario' => (float)$i['valor_unitario'],
            'subtotal' => (float)$i['subtotal'],
        ], $st->fetchAll());

        $st = $this->pdo->prepare('SELECT id, metodo, valor, pago_em FROM pagamentos WHERE comanda_id = ? ORDER BY id');
        $st->execute([$c['id']]);
        $c['pagamentos'] = array_map(fn($p) => [
            'id' => (int)$p['id'], 'metodo' => $p['metodo'], 'valor' => (float)$p['valor'], 'pago_em' => $p['pago_em'],
        ], $st->fetchAll());
        return $c;
    }

    protected function antesCriar(array &$dados): void
    {
        if (($dados['status'] ?? 'aberta') !== 'aberta') {
            throw new ApiException(422, 'Dados inválidos.', ['status' => 'Uma comanda nova deve ser criada como "aberta".']);
        }
        $dados['status']     = 'aberta';
        $dados['usuario_id'] = $this->usuario['id'];
    }

    protected function antesAtualizar(int $id, array $atual, array &$dados): void
    {
        if (in_array($atual['status'], ['fechada', 'cancelada'], true)) {
            throw new ApiException(409, "Comanda {$atual['status']} não pode ser alterada.");
        }
        $novo = $dados['status'] ?? $atual['status'];
        if ($novo === $atual['status']) { return; }

        if ($novo === 'fechada') {
            if ($atual['total'] <= 0 || $atual['saldo'] > 0) {
                throw new ApiException(409, 'A comanda só pode ser fechada quando o valor pago cobre o total.');
            }
            $this->pdo->prepare('UPDATE comandas SET fechada_em = NOW() WHERE id = ?')->execute([$id]);
        } elseif ($novo === 'cancelada') {
            if ($atual['pago'] > 0) {
                throw new ApiException(409, 'Comanda com pagamentos registrados não pode ser cancelada.');
            }
            $this->pdo->prepare('UPDATE comandas SET fechada_em = NOW() WHERE id = ?')->execute([$id]);
        }
    }

    // ---- Utilitários usados também por itens e pagamentos ----

    /** Total, valor pago, saldo e status de uma comanda (ou lança 404). */
    public static function resumo(int $id): array
    {
        $st = Database::conexao()->prepare('SELECT comanda_id, status, total, pago FROM vw_resumo_comandas WHERE comanda_id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) { throw new ApiException(404, "Comanda com id $id não encontrada."); }
        return ['status' => $r['status'], 'total' => (float)$r['total'], 'pago' => (float)$r['pago'],
                'saldo' => round((float)$r['total'] - (float)$r['pago'], 2)];
    }

    /** Fecha a comanda quando totalmente paga; reabre se um pagamento foi removido/reduzido. */
    public static function sincronizarStatus(int $id, ?string $quando = null): void
    {
        $r   = self::resumo($id);
        $pdo = Database::conexao();
        if (in_array($r['status'], ['aberta', 'fiado'], true) && $r['total'] > 0 && $r['saldo'] <= 0) {
            $pdo->prepare("UPDATE comandas SET status = 'fechada', fechada_em = ? WHERE id = ?")
                ->execute([$quando ?? date('Y-m-d H:i:s'), $id]);
        } elseif ($r['status'] === 'fechada' && $r['saldo'] > 0) {
            $pdo->prepare("UPDATE comandas SET status = 'aberta', fechada_em = NULL WHERE id = ?")->execute([$id]);
        }
    }
}
