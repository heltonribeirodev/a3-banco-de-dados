<?php
/**
 * Recurso genérico com CRUD completo sobre uma tabela.
 *
 * Cada tabela do banco é representada por uma subclasse que declara apenas
 * seus campos, filtros, permissões e regras de negócio (hooks). O ciclo
 * GET / POST / PUT / DELETE, a validação e a paginação ficam centralizados aqui.
 */
abstract class Resource
{
    /** Tabela no banco e segmento da URL (ex.: "produtos"). */
    protected string $tabela;

    /**
     * Campos aceitos no corpo da requisição.
     * Chaves de cada campo: tipo (int|string|decimal|bool|enum|email|datetime|fk),
     * obrigatorio, obrigatorioCriacao, max, min, valores (enum), tabela (fk),
     * somenteCriacao (não muda no PUT), virtual (não é coluna), anulavel (PUT com
     * null/"" grava NULL), descricao.
     */
    protected array $campos = [];

    /** Colunas devolvidas além de id e dos campos (ex.: criado_em). */
    protected array $extras = [];

    /** Filtros da query string: nome => [coluna, operador]. */
    protected array $filtros = [];

    /** Colunas que aceitam ordenação (?ordem=coluna&direcao=desc). */
    protected array $ordenaveis = ['id'];

    /** Quem pode executar cada ação: "auth" (qualquer logado) ou "admin". */
    protected array $permissoes = [
        'listar' => 'auth', 'ver' => 'auth', 'criar' => 'auth', 'atualizar' => 'auth', 'excluir' => 'admin',
    ];

    protected PDO $pdo;
    protected array $usuario = [];

    public function __construct()
    {
        $this->pdo = Database::conexao();
    }

    public function nome(): string { return $this->tabela; }

    public function setUsuario(array $u): void { $this->usuario = $u; }

    /** Descrição do recurso, usada pela documentação em GET /. */
    public function descricao(): array
    {
        return [
            'campos'     => $this->campos,
            'filtros'    => array_keys($this->filtros),
            'permissoes' => $this->permissoes,
        ];
    }

    public function exigirPermissao(string $acao, array $usuario): void
    {
        if (($this->permissoes[$acao] ?? 'admin') === 'admin' && $usuario['perfil'] !== 'admin') {
            throw new ApiException(403, 'Apenas administradores podem executar esta ação.');
        }
    }

    // ------------------------------------------------------------------
    // Operações do CRUD
    // ------------------------------------------------------------------

    /** GET /recurso */
    public function listar(array $query): array
    {
        $pagina = max(1, (int)($query['pagina'] ?? 1));
        $limite = min(100, max(1, (int)($query['limite'] ?? 20)));

        [$where, $params] = $this->montarFiltros($query);
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $this->origem() . $sqlWhere);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        $ordem = $this->montarOrdem($query);
        $sql   = 'SELECT ' . $this->colunas() . ' FROM ' . $this->origem() . $sqlWhere
               . " ORDER BY $ordem LIMIT $limite OFFSET " . (($pagina - 1) * $limite);
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return [
            'dados' => array_map([$this, 'formatar'], $st->fetchAll()),
            'meta'  => ['pagina' => $pagina, 'limite' => $limite, 'total' => $total,
                        'paginas' => (int)ceil($total / $limite)],
        ];
    }

    /** GET /recurso/{id} */
    public function ver(int $id): array
    {
        return ['dados' => $this->detalhar($this->encontrar($id))];
    }

    /** POST /recurso */
    public function criar(array $corpo): array
    {
        [$dados, $erros] = $this->validar($corpo, true);
        if ($erros) { throw new ApiException(422, 'Dados inválidos.', $erros); }

        $this->pdo->beginTransaction();
        try {
            $this->antesCriar($dados);
            $id = $this->inserir($dados);
            $this->depoisCriar($id, $dados);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return ['dados' => $this->detalhar($this->encontrar($id))];
    }

    /** PUT /recurso/{id} */
    public function atualizar(int $id, array $corpo): array
    {
        $atual = $this->encontrar($id);
        [$dados, $erros] = $this->validar($corpo, false);
        if ($erros) { throw new ApiException(422, 'Dados inválidos.', $erros); }

        $this->pdo->beginTransaction();
        try {
            $this->antesAtualizar($id, $atual, $dados);
            $cols = $this->colunasGravaveis($dados);
            if ($cols) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($cols)));
                $st  = $this->pdo->prepare("UPDATE {$this->tabela} SET $set WHERE id = ?");
                $st->execute([...array_values($cols), $id]);
            }
            $this->depoisAtualizar($id, $atual, $dados);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return ['dados' => $this->detalhar($this->encontrar($id))];
    }

    /** DELETE /recurso/{id} */
    public function excluir(int $id): void
    {
        $atual = $this->encontrar($id);
        $this->pdo->beginTransaction();
        try {
            $this->antesExcluir($id, $atual);
            $st = $this->pdo->prepare("DELETE FROM {$this->tabela} WHERE id = ?");
            $st->execute([$id]);
            $this->depoisExcluir($id, $atual);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Pontos de extensão (regras de negócio de cada recurso)
    // ------------------------------------------------------------------
    protected function antesCriar(array &$dados): void {}
    protected function depoisCriar(int $id, array $dados): void {}
    protected function antesAtualizar(int $id, array $atual, array &$dados): void {}
    protected function depoisAtualizar(int $id, array $atual, array $dados): void {}
    protected function antesExcluir(int $id, array $atual): void {}
    protected function depoisExcluir(int $id, array $atual): void {}

    /** Complementa o registro em GET /recurso/{id} (ex.: itens da comanda). */
    protected function detalhar(array $registro): array { return $registro; }

    // ------------------------------------------------------------------
    // SQL e formatação
    // ------------------------------------------------------------------
    protected function origem(): string { return "{$this->tabela} t"; }

    protected function colunas(): string
    {
        $cols = ['t.id'];
        foreach ($this->campos as $nome => $c) {
            if (empty($c['virtual'])) { $cols[] = "t.$nome"; }
        }
        foreach ($this->extras as $e) { $cols[] = "t.$e"; }
        return implode(', ', $cols);
    }

    protected function encontrar(int $id): array
    {
        $st = $this->pdo->prepare('SELECT ' . $this->colunas() . ' FROM ' . $this->origem() . ' WHERE t.id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            throw new ApiException(404, ucfirst($this->tabela) . " com id $id não encontrado.");
        }
        return $this->formatar($row);
    }

    /** Converte os tipos do banco (strings) para os tipos JSON corretos. */
    protected function formatar(array $row): array
    {
        foreach ($row as $col => $valor) {
            if ($valor === null) { continue; }
            $tipo = $this->campos[$col]['tipo'] ?? null;
            if ($col === 'id' || str_ends_with($col, '_id') || $tipo === 'int' || $tipo === 'fk') {
                $row[$col] = (int)$valor;
            } elseif ($tipo === 'decimal') {
                $row[$col] = (float)$valor;
            } elseif ($tipo === 'bool') {
                $row[$col] = (bool)$valor;
            }
        }
        return $row;
    }

    private function montarFiltros(array $query): array
    {
        $where = []; $params = [];
        foreach ($this->filtros as $chave => [$coluna, $op]) {
            if (!isset($query[$chave]) || $query[$chave] === '') { continue; }
            $valor = (string)$query[$chave];
            // data_fim recebida só com a data (AAAA-MM-DD) vale até o fim do dia
            if ($chave === 'data_fim' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) { $valor .= ' 23:59:59'; }
            $where[]  = "$coluna $op ?";
            $params[] = $op === 'LIKE' ? '%' . $valor . '%' : $valor;
        }
        return [$where, $params];
    }

    private function montarOrdem(array $query): string
    {
        $coluna  = $query['ordem'] ?? '';
        $direcao = strtolower($query['direcao'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        return in_array($coluna, $this->ordenaveis, true) ? "t.$coluna $direcao" : 't.id ASC';
    }

    // ------------------------------------------------------------------
    // Validação e gravação
    // ------------------------------------------------------------------

    /** @return array{0: array, 1: array} [dados válidos, erros por campo] */
    protected function validar(array $corpo, bool $criacao): array
    {
        $dados = []; $erros = [];
        foreach ($this->campos as $nome => $c) {
            if (!$criacao && !empty($c['somenteCriacao'])) { continue; }

            $presente = array_key_exists($nome, $corpo) && $corpo[$nome] !== null && $corpo[$nome] !== '';
            if (!$presente) {
                // campo opcional marcado como "anulavel": enviar null/"" no PUT limpa o valor
                if (!$criacao && !empty($c['anulavel']) && array_key_exists($nome, $corpo)) { $dados[$nome] = null; continue; }
                $obrig = !empty($c['obrigatorio']) || ($criacao && !empty($c['obrigatorioCriacao']));
                if ($obrig) { $erros[$nome] = 'Campo obrigatório.'; }
                continue;
            }
            $r = $this->validarCampo($nome, $c, $corpo[$nome]);
            if (is_array($r) && isset($r['erro'])) { $erros[$nome] = $r['erro']; }
            else { $dados[$nome] = $r; }
        }
        return [$dados, $erros];
    }

    private function validarCampo(string $nome, array $c, mixed $v): mixed
    {
        switch ($c['tipo']) {
            case 'int':
            case 'fk':
                if (!is_int($v) && !(is_string($v) && ctype_digit($v))) { return ['erro' => 'Deve ser um número inteiro.']; }
                $v = (int)$v;
                if (isset($c['min']) && $v < $c['min']) { return ['erro' => "Deve ser maior ou igual a {$c['min']}."]; }
                if ($c['tipo'] === 'fk') {
                    $st = $this->pdo->prepare("SELECT 1 FROM {$c['tabela']} WHERE id = ?");
                    $st->execute([$v]);
                    if (!$st->fetchColumn()) { return ['erro' => "Registro $v não existe em {$c['tabela']}."]; }
                }
                return $v;
            case 'decimal':
                if (!is_numeric($v)) { return ['erro' => 'Deve ser um número.']; }
                $v = round((float)$v, 2);
                if (isset($c['min']) && $v < $c['min']) { return ['erro' => "Deve ser maior ou igual a {$c['min']}."]; }
                if ($v > 99999999.99) { return ['erro' => 'Valor muito alto.']; }
                return $v;
            case 'string':
                if (!is_string($v)) { return ['erro' => 'Deve ser um texto.']; }
                $v = trim($v);
                if ($v === '') { return ['erro' => 'Não pode ser vazio.']; }
                if (isset($c['max']) && mb_strlen($v) > $c['max']) { return ['erro' => "Máximo de {$c['max']} caracteres."]; }
                if (isset($c['min']) && mb_strlen($v) < $c['min']) { return ['erro' => "Mínimo de {$c['min']} caracteres."]; }
                return $v;
            case 'email':
                if (!is_string($v) || !filter_var(trim($v), FILTER_VALIDATE_EMAIL)) { return ['erro' => 'E-mail inválido.']; }
                return trim($v);
            case 'enum':
                if (!in_array($v, $c['valores'], true)) { return ['erro' => 'Valores aceitos: ' . implode(', ', $c['valores']) . '.']; }
                return $v;
            case 'bool':
                $b = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                return $b === null ? ['erro' => 'Deve ser verdadeiro ou falso.'] : (int)$b;
            case 'datetime':
                foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d'] as $fmt) {
                    $d = DateTime::createFromFormat($fmt, (string)$v);
                    if ($d && $d->format($fmt) === (string)$v) { return $d->format('Y-m-d H:i:s'); }
                }
                return ['erro' => 'Data inválida. Use AAAA-MM-DD HH:MM:SS.'];
        }
        return ['erro' => 'Tipo de campo desconhecido.'];
    }

    /** Remove os campos virtuais (que não são colunas). */
    protected function colunasGravaveis(array $dados): array
    {
        foreach ($dados as $nome => $_) {
            if (!empty($this->campos[$nome]['virtual'])) { unset($dados[$nome]); }
        }
        return $dados;
    }

    private function inserir(array $dados): int
    {
        $cols = $this->colunasGravaveis($dados);
        $sql  = "INSERT INTO {$this->tabela} (" . implode(', ', array_keys($cols)) . ') VALUES ('
              . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $this->pdo->prepare($sql)->execute(array_values($cols));
        return (int)$this->pdo->lastInsertId();
    }
}
