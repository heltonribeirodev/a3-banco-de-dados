<?php
/**
 * Configurações da API v1.
 * Os valores podem ser sobrescritos por variáveis de ambiente, o que evita
 * deixar credenciais fixas no código em um ambiente de produção.
 */
return [
    'db' => [
        'host'    => getenv('A3_DB_HOST') ?: 'localhost',
        'porta'   => getenv('A3_DB_PORT') ?: '3306',
        'banco'   => getenv('A3_DB_NAME') ?: 'sistema_gestao_a3',
        'usuario' => getenv('A3_DB_USER') ?: 'root',
        'senha'   => getenv('A3_DB_PASS') !== false ? getenv('A3_DB_PASS') : '',
    ],
    'jwt' => [
        // Troque esta chave em produção (variável A3_JWT_SECRET).
        'segredo'  => getenv('A3_JWT_SECRET') ?: 'chave-de-desenvolvimento-a3-trocar-em-producao',
        'validade' => 8 * 3600, // 8 horas
    ],
];
