<?php
/**
 * Leitura da requisição e escrita da resposta HTTP.
 */
class Request
{
    public string $metodo;
    public array $segmentos;
    public array $query;
    public array $corpo = [];

    public function __construct()
    {
        $this->metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query  = $_GET;

        // Caminho relativo à pasta da API (funciona com e sem mod_rewrite)
        $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && str_starts_with($caminho, $base)) {
            $caminho = substr($caminho, strlen($base));
        }
        $caminho = preg_replace('#^/index\.php#', '', $caminho);
        $this->segmentos = array_values(array_filter(explode('/', trim($caminho, '/')), 'strlen'));

        if (in_array($this->metodo, ['POST', 'PUT', 'PATCH'], true)) {
            $bruto = file_get_contents('php://input');
            if ($bruto !== '' && $bruto !== false) {
                $json = json_decode($bruto, true);
                if (!is_array($json)) {
                    throw new ApiException(400, 'Corpo inválido: envie um JSON válido (Content-Type: application/json).');
                }
                $this->corpo = $json;
            } elseif (!empty($_POST)) {
                $this->corpo = $_POST;
            }
        }
    }

    public function token(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($h === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) { $h = $v; }
            }
        }
        return preg_match('/^Bearer\s+(.+)$/i', $h, $m) ? trim($m[1]) : null;
    }
}

class Response
{
    public static function json(int $status, ?array $payload = null): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($status !== 204 && $payload !== null) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        exit;
    }

    public static function erro(int $status, string $mensagem, array $detalhes = []): never
    {
        $corpo = ['erro' => $mensagem];
        if ($detalhes) { $corpo['detalhes'] = $detalhes; }
        self::json($status, $corpo);
    }
}
