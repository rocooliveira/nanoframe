<?php

namespace Nanoframe\Core\Exceptions;

/**
 * Exceção base para erros específicos do QueryBuilder
 */
class QueryBuilderException extends \RuntimeException
{
    /**
     * Contexto adicional do erro (opcional)
     * @var array
     */
    protected $context = [];

    public function __construct(string $message = "", int $code = 0, \Throwable $previous, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}