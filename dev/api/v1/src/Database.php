<?php
/**
 * Conexão única (singleton) com o banco, via PDO.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function conexao(): PDO
    {
        if (self::$pdo === null) {
            $c   = (require __DIR__ . '/../config.php')['db'];
            $dsn = "mysql:host={$c['host']};port={$c['porta']};dbname={$c['banco']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $c['usuario'], $c['senha'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }
}
