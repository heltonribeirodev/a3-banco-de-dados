<?php
/** Tabela produtos. Produto com histórico de venda não é excluído: deve ser inativado. */
class ProdutosResource extends Resource
{
    protected string $tabela = 'produtos';
    protected array $extras = ['criado_em'];
    protected array $campos = [
        'categoria_id' => ['tipo' => 'fk', 'tabela' => 'categorias', 'obrigatorio' => true],
        'nome'         => ['tipo' => 'string', 'obrigatorio' => true, 'max' => 100],
        'valor'        => ['tipo' => 'decimal', 'obrigatorio' => true, 'min' => 0],
        'imagem'       => ['tipo' => 'string', 'max' => 255, 'descricao' => 'Caminho da imagem'],
        'ativo'        => ['tipo' => 'bool'],
    ];
    protected array $filtros = [
        'categoria_id' => ['t.categoria_id', '='], 'ativo' => ['t.ativo', '='], 'q' => ['t.nome', 'LIKE'],
    ];
    protected array $ordenaveis = ['id', 'nome', 'valor', 'categoria_id', 'criado_em'];
    protected array $permissoes = ['listar' => 'auth', 'ver' => 'auth', 'criar' => 'admin', 'atualizar' => 'admin', 'excluir' => 'admin'];
}
