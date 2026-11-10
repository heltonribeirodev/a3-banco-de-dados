<?php
/**
 * Tabela itens_comanda. Só é possível mexer nos itens de comanda aberta.
 * Se valor_unitario não for informado, usa o preço atual do produto
 * (o valor fica gravado para preservar o histórico de vendas).
 */
class ItensComandaResource extends Resource
{
    protected string $tabela = 'itens_comanda';
    protected array $campos = [
        'comanda_id'     => ['tipo' => 'fk', 'tabela' => 'comandas', 'obrigatorio' => true, 'somenteCriacao' => true],
        'produto_id'     => ['tipo' => 'fk', 'tabela' => 'produtos', 'obrigatorio' => true],
        'quantidade'     => ['tipo' => 'int', 'obrigatorio' => true, 'min' => 1],
        'valor_unitario' => ['tipo' => 'decimal', 'min' => 0, 'descricao' => 'Padrão: preço atual do produto'],
    ];
    protected array $filtros = ['comanda_id' => ['t.comanda_id', '='], 'produto_id' => ['t.produto_id', '=']];
    protected array $ordenaveis = ['id', 'comanda_id', 'produto_id', 'quantidade'];
    protected array $permissoes = ['listar' => 'auth', 'ver' => 'auth', 'criar' => 'auth', 'atualizar' => 'auth', 'excluir' => 'auth'];

    protected function origem(): string
    {
        return 'itens_comanda t JOIN produtos p ON p.id = t.produto_id';
    }

    protected function colunas(): string
    {
        return parent::colunas() . ', p.nome AS produto_nome, (t.quantidade * t.valor_unitario) AS subtotal';
    }

    protected function formatar(array $row): array
    {
        $row = parent::formatar($row);
        $row['subtotal'] = (float)$row['subtotal'];
        return $row;
    }

    private function exigirComandaAberta(int $comandaId): void
    {
        $status = ComandasResource::resumo($comandaId)['status'];
        if ($status !== 'aberta') {
            throw new ApiException(409, "Só é possível alterar itens de comanda aberta (status atual: $status).");
        }
    }

    private function produto(int $id): array
    {
        $st = $this->pdo->prepare('SELECT valor, ativo FROM produtos WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch();
    }

    protected function antesCriar(array &$dados): void
    {
        $this->exigirComandaAberta($dados['comanda_id']);
        $p = $this->produto($dados['produto_id']);
        if (!(int)$p['ativo']) { throw new ApiException(409, 'Produto inativo não pode ser vendido.'); }
        $dados['valor_unitario'] ??= (float)$p['valor'];
    }

    protected function antesAtualizar(int $id, array $atual, array &$dados): void
    {
        $this->exigirComandaAberta($atual['comanda_id']);
        if (isset($dados['produto_id']) && $dados['produto_id'] !== $atual['produto_id']) {
            $p = $this->produto($dados['produto_id']);
            if (!(int)$p['ativo']) { throw new ApiException(409, 'Produto inativo não pode ser vendido.'); }
            $dados['valor_unitario'] ??= (float)$p['valor'];
        }
    }

    protected function antesExcluir(int $id, array $atual): void
    {
        $this->exigirComandaAberta($atual['comanda_id']);
    }
}
