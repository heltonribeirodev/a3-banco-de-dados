<?php
/**
 * Exceção de domínio da API: carrega o código HTTP e os detalhes do erro.
 */
class ApiException extends Exception
{
    public function __construct(
        public int $status,
        string $mensagem,
        public array $detalhes = []
    ) {
        parent::__construct($mensagem);
    }
}
