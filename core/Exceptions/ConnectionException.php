<?php

namespace Nanoframe\Core\Exceptions;

/**
 * Exceção específica para falhas de conexão com o banco
 */
class ConnectionException extends QueryBuilderException
{
    /**
     * Tipo de conexão que falhou
     * @var string
     */
    private $connectionType;

    public function __construct(string $message = "", int $code = 0, \Throwable $previous, string $connectionType = 'MySQL')
    {
        parent::__construct($message, $code, $previous);
        $this->connectionType = $connectionType;
    }

    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'connection_type' => $this->connectionType,
            'timestamp' => time()
        ]);
    }
}