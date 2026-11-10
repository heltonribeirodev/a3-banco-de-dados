<?php
/**
 * Configurações da API v1.
 * Os valores podem ser sobrescritos por variáveis de ambiente, o que evita
 * deixar credenciais fixas no código.
 */

/**
 * Segredo usado para assinar os tokens JWT.
 * 1) Se existir a variável de ambiente A3_JWT_SECRET, ela é usada.
 * 2) Caso contrário, gera-se um segredo aleatório na primeira execução e ele é
 *    guardado em .jwt_secret (arquivo ignorado pelo Git e bloqueado no .htaccess).
 */
if (!function_exists('a3_segredo_jwt')) {
function a3_segredo_jwt(): string
{
    $env = getenv('A3_JWT_SECRET');
    if ($env !== false && $env !== '') { return $env; }
    $arquivo = __DIR__ . '/.jwt_secret';
    if (!is_file($arquivo)) { file_put_contents($arquivo, bin2hex(random_bytes(32))); }
    return trim((string)file_get_contents($arquivo));
}
} // o arquivo é incluído várias vezes; a função só pode ser declarada uma vez

return [
    'db' => [
        'host'    => getenv('A3_DB_HOST') ?: 'localhost',
        'porta'   => getenv('A3_DB_PORT') ?: '3306',
        'banco'   => getenv('A3_DB_NAME') ?: 'sistema_gestao_a3',
        'usuario' => getenv('A3_DB_USER') ?: 'root',
        'senha'   => getenv('A3_DB_PASS') !== false ? getenv('A3_DB_PASS') : '',
    ],
    'jwt' => [
        'segredo'  => a3_segredo_jwt(),
        'validade' => 8 * 3600, // 8 horas
    ],
    // Origens autorizadas a chamar a API pelo navegador (CORS), separadas por vírgula.
    'cors' => array_filter(array_map('trim', explode(',',
        getenv('A3_CORS_ORIGINS') ?: 'http://localhost,http://127.0.0.1'))),
];
