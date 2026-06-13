<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Security\Auth\AuthManager;

class AuthCollector implements CollectorInterface
{
    public function __construct(
        private readonly AuthManager $auth
    ) {}

    public function getName(): string
    {
        return 'auth';
    }

    public function collect(): array
    {
        if (!$this->auth->check()) {
            return [
                'authenticated' => false,
                'guard' => null,
                'user' => null,
            ];
        }

        $user = $this->auth->user();

        if ($user === null) {
            return [
                'authenticated' => false,
                'guard' => null,
                'user' => null,
            ];
        }

        $pk = $user::getPrimaryKey();
        $attributes = $user->toArray();

        foreach (['password', 'remember_token', 'token'] as $sensitive) {
            unset($attributes[$sensitive]);
        }

        return [
            'authenticated' => true,
            'user' => [
                'id' => $user->{$pk} ?? null,
                'attributes' => $attributes,
            ],
        ];
    }
}