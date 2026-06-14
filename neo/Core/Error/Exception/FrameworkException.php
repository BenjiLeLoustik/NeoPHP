<?php
declare(strict_types=1);

namespace Neo\Core\Error\Exception;

use Throwable;

class FrameworkException extends \Exception
{
    private string $title;

    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $title,
        string $message,
        int $code = 0,
        array $context = [],
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
        $this->title = $title;
        $this->context = $context;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public static function fromThrowable(Throwable $e, string $title = 'Framework Error'): self
    {
        return new self(
            $title,
            $e->getMessage(),
            (int) $e->getCode(),
            [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
                'previous' => $e->getPrevious(),
            ],
            $e
        );
    }
}