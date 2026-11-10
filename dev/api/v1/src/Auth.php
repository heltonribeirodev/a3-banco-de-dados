<?php
/**
 * Autenticação (login) e identificação do usuário a partir do token Bearer.
 */
class Auth
{
    public static function login(array $corpo): array
    {
        $email = trim((string)($corpo['email'] ?? ''));
        $senha = (string)($corpo['senha'] ?? '');
        if ($email === '' || $senha === '') {
            throw new ApiException(422, 'Informe e-mail e senha.');
        }
        $st = Database::conexao()->prepare(
            'SELECT id, nome, email, senha_hash, perfil, status_conta FROM usuarios WHERE email = ?'
        );
        $st->execute([$email]);
        $u = $st->fetch();

        // Mesma mensagem para e-mail inexistente, senha errada ou conta inativa
        if (!$u || $u['status_conta'] !== 'ativo' || !password_verify($senha, $u['senha_hash'])) {
            throw new ApiException(401, 'E-mail ou senha inválidos.');
        }
        $cfg = (require __DIR__ . '/../config.php')['jwt'];
        return [
            'token'     => Jwt::gerar(['sub' => (int)$u['id']]),
            'tipo'      => 'Bearer',
            'expira_em' => $cfg['validade'],
            'usuario'   => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'email' => $u['email'], 'perfil' => $u['perfil']],
        ];
    }

    /** Retorna o usuário logado ou lança 401. Consulta o banco a cada requisição. */
    public static function usuario(Request $req): array
    {
        $token   = $req->token();
        $payload = $token ? Jwt::validar($token) : null;
        if (!$payload) {
            throw new ApiException(401, 'Token ausente, inválido ou expirado. Faça login em POST /auth/login.');
        }
        $st = Database::conexao()->prepare('SELECT id, nome, email, perfil, status_conta FROM usuarios WHERE id = ?');
        $st->execute([$payload['sub']]);
        $u = $st->fetch();
        if (!$u || $u['status_conta'] !== 'ativo') {
            throw new ApiException(401, 'Usuário inexistente ou inativo.');
        }
        $u['id'] = (int)$u['id'];
        return $u;
    }
}
