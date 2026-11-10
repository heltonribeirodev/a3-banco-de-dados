<?php
/**
 * Tabela pagamentos. Uma comanda pode ter vários pagamentos (pagamento dividido).
 * Ao cobrir o total, a comanda passa automaticamente para "fechada".
 */
class PagamentosResource extends Resource
{
    protected string $tabela = 'pagamentos';
    protected array $campos = [
        'comanda_id' => ['tipo' => 'fk', 'tabela' => 'comandas', 'obrigatorio' => true, 'somenteCriacao' => true],
        'metodo'     => ['tipo' => 'enum', 'obrigatorio' => true, 'valores' => ['pix', 'dinheiro', 'cartao_credito', 'cartao_debito']],
        'valor'      => ['tipo' => 'decimal', 'obrigatorio' => true, 'min' => 0.01],
        'pago_em'    => ['tipo' => 'datetime', 'descricao' => 'Padrão: data e hora atuais'],
    ];
    protected array $extras = ['usuario_id'];
    protected array $filtros = [
        'comanda_id' => ['t.comanda_id', '='], 'metodo' => ['t.metodo', '='],
        'data_inicio' => ['t.pago_em', '>='], 'data_fim' => ['t.pago_em', '<='],
    ];
    protected array $ordenaveis = ['id', 'comanda_id', 'valor', 'pago_em'];
    protected array $permissoes = ['listar' => 'auth', 'ver' => 'auth', 'criar' => 'auth', 'atualizar' => 'admin', 'excluir' => 'admin'];

    protected function antesCriar(array &$dados): void
    {
        $r = ComandasResource::resumo($dados['comanda_id']);
        if (!in_array($r['status'], ['aberta', 'fiado'], true)) {
            throw new ApiException(409, "Não é possível registrar pagamento em comanda {$r['status']}.");
        }
        if ($r['total'] <= 0) {
            throw new ApiException(409, 'A comanda não possui itens.');
        }
        $this->exigirDentroDoSaldo($dados['valor'], $r['saldo']);
        $dados['usuario_id'] = $this->usuario['id'];
        $dados['pago_em'] ??= date('Y-m-d H:i:s');
    }

    protected function depoisCriar(int $id, array $dados): void
    {
        ComandasResource::sincronizarStatus($dados['comanda_id'], $dados['pago_em']);
    }

    protected function antesAtualizar(int $id, array $atual, array &$dados): void
    {
        $r = ComandasResource::resumo($atual['comanda_id']);
        if ($r['status'] === 'cancelada') {
            throw new ApiException(409, 'Comanda cancelada não aceita alteração de pagamentos.');
        }
        $this->exigirDentroDoSaldo($dados['valor'] ?? $atual['valor'], $r['saldo'] + $atual['valor']);
    }

    protected function depoisAtualizar(int $id, array $atual, array $dados): void
    {
        ComandasResource::sincronizarStatus($atual['comanda_id'], $dados['pago_em'] ?? $atual['pago_em']);
    }

    protected function depoisExcluir(int $id, array $atual): void
    {
        ComandasResource::sincronizarStatus($atual['comanda_id']);
    }

    private function exigirDentroDoSaldo(float $valor, float $saldo): void
    {
        if (round($valor, 2) > round($saldo, 2)) {
            throw new ApiException(422, 'Dados inválidos.', [
                'valor' => 'O valor excede o saldo da comanda (R$ ' . number_format($saldo, 2, ',', '.') . ').',
            ]);
        }
    }
}
