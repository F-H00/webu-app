<?php declare(strict_types = 1);
namespace SpawnCore\System\Database\Entity;

use SpawnCore\System\Custom\Throwables\AbstractException;
use Throwable;

class InvalidRepositoryInteractionException extends AbstractException {

    public function __construct(string $message, Throwable $previous = null)
    {
        parent::__construct([
            'message' => $message
        ], $previous);
    }


    protected function getMessageTemplate(): string
    {
        return '%message%';
    }

    protected function getExitCode(): int
    {
        return 189;
    }
}