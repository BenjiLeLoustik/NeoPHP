<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Profiler\Collector\CollectorInterface;

class AuthCollector implements CollectorInterface
{
    public function __construct(
        private readonly AuthManager $auth
    ) {}

    public function getName(): string
    {
        return 'auth';
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $data
     */
    public function renderTab(array $data): string
    {
        $authenticated = $data['authenticated'] ?? false;
        $color = $authenticated ? '#4ade80' : '#71717a';
        $label = $authenticated
            ? htmlspecialchars((string) (
                $data['user']['attributes']['email']
                ?? $data['user']['attributes']['name']
                ?? 'Logged'
            ))
            : 'Guest';

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('auth')" title="Authentification">
    <span class="n-label">User</span>
    <span class="n-value" style="color:{$color}">{$label}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        if (!($data['authenticated'] ?? false)) {
            return <<<HTML
<div class="n-auth-chip off">
    <span class="n-auth-chip-dot"></span>
    No users logged in
</div>
HTML;
        }

        $user = $data['user'];
        $id   = htmlspecialchars((string) ($user['id'] ?? '—'));

        $rows = '';
        foreach (($user['attributes'] ?? []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $k = htmlspecialchars($key);
            $v = htmlspecialchars(is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : (string) $value
            );
            $rows .= "<dt>{$k}</dt><dd>{$v}</dd>";
        }

        return <<<HTML
<div class="n-auth-chip on">
    <span class="n-auth-chip-dot"></span>
    Connected &mdash; ID {$id}
</div>
<dl class="n-kv">{$rows}</dl>
HTML;
    }
}