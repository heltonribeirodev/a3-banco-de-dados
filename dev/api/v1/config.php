<?php
if (!function_exists('a3_segredo_jwt')) {
function a3_segredo_jwt(): string
{
    $env = getenv('A3_JWT_SECRET');
    if ($env !== false && $env !== '') { return $env; }
    $arquivo = __DIR__ . '/.jwt_secret';
    if (!is_file($arquivo)) { file_put_contents($arquivo, bin2hex(random_bytes(32))); }
    return trim((string)file_get_contents($arquivo));
}
}

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
    'cors' => array_filter(array_map('trim', explode(',',
        getenv('A3_CORS_ORIGINS') ?: 'http://localhost,http://127.0.0.1'))),
];
