<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public string $use;
    public string $message;
    public string $onError;
    public ?string $redirect;
    public array $params;

    public function __construct(string $use, string $message = '', string $onError = 'block', ?string $redirect = null, array $params = [])
    {
        $this->use = $use;
        $this->message = $message;
        $this->onError = $onError;
        $this->redirect = $redirect;
        $this->params = $params;
    }
}
