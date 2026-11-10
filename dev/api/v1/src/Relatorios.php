<?php
/**
 * Relatórios somente leitura (GET /relatorios/{nome}).
 * Cada método é uma das consultas DQL do script sistema_gestao_a3.sql, com os
 * valores recebidos sempre passados por parâmetros (prepared statements).
 */
class Relatorios
{
    /** Relatórios disponíveis: nome => [descrição, parâmetros aceitos]. */
    private const LISTA = [
        'comandas-periodo'       => ['Comandas com cliente, atendente, itens, total e pago', ['data_inicio', 'data_fim']],
        'produtos-mais-vendidos' => ['Ranking de produtos por quantidade vendida', ['data_inicio', 'data_fim', 'limite']],
        'faturamento-categoria'  => ['Faturamento por categoria de produto', ['data_inicio', 'data_fim']],
        'pagamentos-metodo'      => ['Recebimentos por forma de pagamento', ['data_inicio', 'data_fim']],
        'desempenho-atendentes'  => ['Comandas e valores por atendente', []],
        'fiado-saldo-devedor'    => ['Saldo devedor das comandas em fiado', []],
        'faturamento-mensal'     => ['Faturamento por mês (parâmetro minimo: filtro HAVING)', ['minimo']],
    ];

    /** Texto exibido em GET /. */
    public static function descricao(): array
    {
        $doc = [];
        foreach (self::LISTA as $nome => [$texto, $params]) {
            $doc[$nome] = ['descricao' => $texto, 'parametros' => $params, 'metodo' => 'GET', 'permissao' => 'auth'];
        }
        return $doc;
    }

    public static function executar(string $nome, array $q): array
    {
        if (!isset(self::LISTA[$nome])) {
            throw new ApiException(404, "Relatório '$nome' não existe. Veja GET / para a lista.");
        }
        [$sql, $params, $numericos] = match ($nome) {
            'comandas-periodo'       => self::comandasPeriodo($q),
            'produtos-mais-vendidos' => self::produtosMaisVendidos($q),
            'faturamento-categoria'  => self::faturamentoCategoria($q),
            'pagamentos-metodo'      => self::pagamentosMetodo($q),
            'desempenho-atendentes'  => self::desempenhoAtendentes(),
            'fiado-saldo-devedor'    => self::fiadoSaldoDevedor(),
            'faturamento-mensal'     => self::faturamentoMensal($q),
        };
        $st = Database::conexao()->prepare($sql);
        $st->execute($params);
        $linhas = $st->fetchAll();
        foreach ($linhas as &$l) {
            // texto numérico ('5' ou '47.00') vira int ou float
            foreach ($numericos as $col) { $l[$col] = $l[$col] + 0; }
        }
        unset($l);
        $filtros = array_intersect_key($q, array_flip(self::LISTA[$nome][1]));
        return ['dados' => $linhas, 'meta' => ['relatorio' => $nome, 'total' => count($linhas), 'filtros' => (object)$filtros]];
    }

    // ------------------------------------------------------------------

    /** Valida uma data AAAA-MM-DD (devolve null se não foi informada). */
    private static function data(array $q, string $chave): ?string
    {
        $v = $q[$chave] ?? '';
        if ($v === '') { return null; }
        $d = is_string($v) ? DateTime::createFromFormat('Y-m-d', $v) : false;
        if (!$d || $d->format('Y-m-d') !== $v) {
            throw new ApiException(422, 'Dados inválidos.', [$chave => 'Data inválida. Use AAAA-MM-DD.']);
        }
        return $v;
    }

    /**
     * Monta o filtro de período sobre uma coluna de data.
     * O fim é inclusivo: usa "< fim + 1 dia".
     * @return array{0: string, 1: array} [condições SQL (começam com AND), parâmetros]
     */
    private static function periodo(array $q, string $coluna): array
    {
        $ini = self::data($q, 'data_inicio');
        $fim = self::data($q, 'data_fim');
        if ($ini && $fim && $ini > $fim) {
            throw new ApiException(422, 'Dados inválidos.', ['data_fim' => 'A data final deve ser maior ou igual à inicial.']);
        }
        $sql = ''; $params = [];
        if ($ini) { $sql .= " AND $coluna >= ?"; $params[] = $ini; }
        if ($fim) { $sql .= " AND $coluna < DATE_ADD(?, INTERVAL 1 DAY)"; $params[] = $fim; }
        return [$sql, $params];
    }

    private static function comandasPeriodo(array $q): array
    {
        [$per, $params] = self::periodo($q, 'c.aberta_em');
        $sql = "SELECT c.id AS comanda, cl.nome AS cliente, u.nome AS atendente, c.status, c.aberta_em,
                       COALESCE((SELECT SUM(i.quantidade) FROM itens_comanda i WHERE i.comanda_id = c.id), 0) AS qtd_itens,
                       v.total, v.pago
                  FROM comandas c
                  JOIN clientes cl ON cl.id = c.cliente_id
                  JOIN usuarios u  ON u.id  = c.usuario_id
                  JOIN vw_resumo_comandas v ON v.comanda_id = c.id
                 WHERE 1 = 1 $per
                 ORDER BY c.aberta_em";
        return [$sql, $params, ['comanda', 'qtd_itens', 'total', 'pago']];
    }

    private static function produtosMaisVendidos(array $q): array
    {
        [$per, $params] = self::periodo($q, 'c.aberta_em');
        $limite = min(100, max(1, (int)($q['limite'] ?? 10)));
        $sql = "SELECT p.nome AS produto, ct.nome AS categoria,
                       SUM(i.quantidade) AS qtd_vendida,
                       SUM(i.quantidade * i.valor_unitario) AS receita
                  FROM itens_comanda i
                  JOIN produtos p    ON p.id  = i.produto_id
                  JOIN categorias ct ON ct.id = p.categoria_id
                  JOIN comandas c    ON c.id  = i.comanda_id
                 WHERE c.status IN ('fechada','fiado') $per
                 GROUP BY p.id, p.nome, ct.nome
                 ORDER BY qtd_vendida DESC, p.nome
                 LIMIT $limite";
        return [$sql, $params, ['qtd_vendida', 'receita']];
    }

    private static function faturamentoCategoria(array $q): array
    {
        [$per, $params] = self::periodo($q, 'c.aberta_em');
        $sql = "SELECT ct.nome AS categoria, SUM(i.quantidade * i.valor_unitario) AS faturamento
                  FROM categorias ct
                  JOIN produtos p      ON p.categoria_id = ct.id
                  JOIN itens_comanda i ON i.produto_id = p.id
                  JOIN comandas c      ON c.id = i.comanda_id
                 WHERE c.status <> 'cancelada' $per
                 GROUP BY ct.id, ct.nome
                 ORDER BY faturamento DESC";
        return [$sql, $params, ['faturamento']];
    }

    private static function pagamentosMetodo(array $q): array
    {
        [$per, $params] = self::periodo($q, 'p.pago_em');
        $sql = "SELECT p.metodo, COUNT(*) AS qtd_pagamentos, SUM(p.valor) AS total_recebido
                  FROM pagamentos p
                 WHERE 1 = 1 $per
                 GROUP BY p.metodo
                 ORDER BY total_recebido DESC";
        return [$sql, $params, ['qtd_pagamentos', 'total_recebido']];
    }

    private static function desempenhoAtendentes(): array
    {
        $sql = "SELECT u.nome AS atendente, u.status_conta,
                       COUNT(DISTINCT c.id) AS comandas_abertas,
                       COALESCE(SUM(v.total), 0) AS valor_total
                  FROM usuarios u
                  LEFT JOIN comandas c ON c.usuario_id = u.id
                  LEFT JOIN vw_resumo_comandas v ON v.comanda_id = c.id
                 WHERE u.perfil = 'atendente'
                 GROUP BY u.id, u.nome, u.status_conta
                 ORDER BY valor_total DESC";
        return [$sql, [], ['comandas_abertas', 'valor_total']];
    }

    private static function fiadoSaldoDevedor(): array
    {
        $sql = "SELECT cl.nome AS cliente, cl.telefone, v.comanda_id,
                       v.total, v.pago, (v.total - v.pago) AS saldo_devedor
                  FROM vw_resumo_comandas v
                  JOIN comandas c  ON c.id = v.comanda_id
                  JOIN clientes cl ON cl.id = c.cliente_id
                 WHERE c.status = 'fiado' AND v.total > v.pago
                 ORDER BY saldo_devedor DESC";
        return [$sql, [], ['comanda_id', 'total', 'pago', 'saldo_devedor']];
    }

    private static function faturamentoMensal(array $q): array
    {
        $min = $q['minimo'] ?? '0';
        if (!is_numeric($min) || (float)$min < 0) {
            throw new ApiException(422, 'Dados inválidos.', ['minimo' => 'Deve ser um número maior ou igual a zero.']);
        }
        $sql = "SELECT DATE_FORMAT(c.aberta_em, '%Y-%m') AS mes,
                       COUNT(*) AS qtd_comandas,
                       SUM(v.total) AS faturamento
                  FROM comandas c
                  JOIN vw_resumo_comandas v ON v.comanda_id = c.id
                 WHERE c.status <> 'cancelada'
                 GROUP BY DATE_FORMAT(c.aberta_em, '%Y-%m')
                HAVING SUM(v.total) >= ?
                 ORDER BY mes";
        return [$sql, [(float)$min], ['qtd_comandas', 'faturamento']];
    }
}
