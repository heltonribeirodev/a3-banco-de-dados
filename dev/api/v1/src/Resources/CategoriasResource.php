<?php
/** Tabela categorias. Escrita restrita a administradores. */
class CategoriasResource extends Resource
{
    protected string $tabela = 'categorias';
    protected array $campos = [
        'nome' => ['tipo' => 'string', 'obrigatorio' => true, 'max' => 50, 'descricao' => 'Único'],
    ];
    protected array $filtros = ['q' => ['t.nome', 'LIKE']];
    protected array $ordenaveis = ['id', 'nome'];
    protected array $permissoes = ['listar' => 'auth', 'ver' => 'auth', 'criar' => 'admin', 'atualizar' => 'admin', 'excluir' => 'admin'];
}
