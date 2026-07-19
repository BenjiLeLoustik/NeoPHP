<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\Http\Request\Request;
use Neo\Core\Security\Csrf\CsrfManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CsrfManager $csrfManager,
        private readonly Request $request,
    ) {}

    public function handle(): bool
    {
        if (in_array($this->request->getMethod(), self::SAFE_METHODS, true)) {
            return true;
        }

        return $this->csrfManager->validate();
    }
}