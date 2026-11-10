<?php
/** Tabela clientes. Atendentes cadastram e editam; exclusão é do administrador. */
class ClientesResource extends Resource
{
    protected string $tabela = 'clientes';
    protected array $extras = ['criado_em'];
    protected array $campos = [
        'nome'     => ['tipo' => 'string', 'obrigatorio' => true, 'max' => 100],
        'telefone' => ['tipo' => 'string', 'max' => 20],
    ];
    protected array $filtros = ['q' => ['t.nome', 'LIKE']];
    protected array $ordenaveis = ['id', 'nome', 'criado_em'];
}
