<?php
/** Tabela usuarios. Apenas administradores gerenciam usuários. */
class UsuariosResource extends Resource
{
    protected string $tabela = 'usuarios';
    protected array $extras = ['criado_em'];
    protected array $campos = [
        'nome'         => ['tipo' => 'string', 'obrigatorio' => true, 'max' => 100],
        'email'        => ['tipo' => 'email', 'obrigatorio' => true, 'descricao' => 'Único no sistema'],
        'senha'        => ['tipo' => 'string', 'obrigatorioCriacao' => true, 'min' => 8, 'max' => 72, 'virtual' => true,
                           'descricao' => 'Somente escrita; armazenada com hash bcrypt'],
        'perfil'       => ['tipo' => 'enum', 'valores' => ['admin', 'atendente']],
        'status_conta' => ['tipo' => 'enum', 'valores' => ['ativo', 'inativo']],
    ];
    protected array $filtros = ['perfil' => ['t.perfil', '='], 'status_conta' => ['t.status_conta', '='], 'q' => ['t.nome', 'LIKE']];
    protected array $ordenaveis = ['id', 'nome', 'email', 'criado_em'];
    protected array $permissoes = ['listar' => 'admin', 'ver' => 'admin', 'criar' => 'admin', 'atualizar' => 'admin', 'excluir' => 'admin'];

    protected function antesCriar(array &$dados): void
    {
        $dados['senha_hash'] = password_hash($dados['senha'], PASSWORD_BCRYPT);
    }

    protected function antesAtualizar(int $id, array $atual, array &$dados): void
    {
        if (isset($dados['senha'])) {
            $dados['senha_hash'] = password_hash($dados['senha'], PASSWORD_BCRYPT);
        }
        // Não permite deixar o sistema sem nenhum administrador ativo
        $perdeAdmin = $atual['perfil'] === 'admin'
            && (($dados['perfil'] ?? 'admin') !== 'admin' || ($dados['status_conta'] ?? 'ativo') === 'inativo');
        if ($perdeAdmin && $this->totalAdminsAtivos() <= 1) {
            throw new ApiException(409, 'Não é possível rebaixar ou inativar o último administrador ativo.');
        }
    }

    protected function antesExcluir(int $id, array $atual): void
    {
        if ($id === $this->usuario['id']) {
            throw new ApiException(409, 'Você não pode excluir a própria conta.');
        }
        if ($atual['perfil'] === 'admin' && $this->totalAdminsAtivos() <= 1) {
            throw new ApiException(409, 'Não é possível excluir o último administrador.');
        }
    }

    private function totalAdminsAtivos(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='admin' AND status_conta='ativo'")->fetchColumn();
    }
}
