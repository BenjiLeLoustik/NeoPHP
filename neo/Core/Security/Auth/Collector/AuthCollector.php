<?php

declare(strict_types=1);

namespace Neo\Core\Security\Auth\Collector;

use Neo\Core\Database\Form\PropertyAccessor;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Security\Auth\AuthManager;
use ReflectionClass;
use ReflectionProperty;

final class AuthCollector implements CollectorInterface
{
    private const array MASKED_FIELDS = ['password', 'passwordHash', 'hashedPassword', 'secret', 'token'];

    private readonly PropertyAccessor $accessor;

    public function __construct(private readonly AuthManager $auth)
    {
        $this->accessor = new PropertyAccessor();
    }

    public function getName(): string
    {
        return 'auth';
    }

    public function collect(): array
    {
        if (!$this->auth->isEnabled()) {
            return [
                'enabled' => false,
                'guard' => null,
                'authenticated' => false,
                'identifierField' => null,
                'identifierValue' => null,
                'role' => null,
                'entityClass' => null,
                'properties' => [],
            ];
        }

        $authenticated = $this->auth->check();
        $user = $authenticated ? $this->auth->user() : null;
        $identifierField = $this->auth->getIdentifierField();

        return [
            'enabled' => true,
            'guard' => $this->auth->getGuardType(),
            'authenticated' => $authenticated,
            'identifierField' => $identifierField,
            'identifierValue' => $user !== null ? $this->readValue($user, $identifierField) : null,
            'role' => $user !== null ? $this->resolveRole($user) : null,
            'entityClass' => $user !== null ? $user::class : null,
            'properties' => $user !== null ? $this->dumpProperties($user) : [],
        ];
    }

    public function inToolbar(): bool
    {
        return true;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        $data = $this->collect();

        if (!$data['enabled']) {
            return [
                'label' => 'Auth',
                'value' => 'Disabled',
                'badge' => null,
            ];
        }

        return [
            'label' => 'Auth',
            'value' => $data['authenticated'] ? $this->formatIdentifier($data) : 'Guest',
            'badge' => strtoupper($data['guard']),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatIdentifier(array $data): string
    {
        $value = $data['identifierValue'] ?? '?';

        if ($this->looksLikeEmail($value)) {
            return $value;
        }

        return '@' . $value;
    }

    private function looksLikeEmail(mixed $value): bool
    {
        return is_string($value)
            && str_contains($value, '@')
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if (!$data['enabled']) {
            return [
                'title' => 'Auth',
                'badge' => null,
                'group' => 'Security',
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'Disabled in auth.config.php'],
                        ],
                    ],
                ],
            ];
        }

        $blocks = [
            [
                'type' => 'kv',
                'section' => null,
                'rows' => [
                    ['label' => 'Guard', 'value' => $data['guard']],
                    ['label' => 'Authenticated', 'value' => $data['authenticated'] ? 'Yes' : 'No'],
                    ['label' => 'Identifier field', 'value' => $data['identifierField']],
                    ['label' => 'Identifier value', 'value' => $data['identifierValue'] ?? 'n/a'],
                    ['label' => 'Role', 'value' => $data['role'] ?? 'n/a'],
                    ['label' => 'Entity class', 'value' => $data['entityClass'] ?? 'n/a'],
                ],
            ],
        ];

        if ($data['authenticated'] && $data['properties'] !== []) {
            $blocks[] = [
                'type' => 'table',
                'section' => 'User entity properties',
                'columns' => ['Property', 'Value'],
                'rows' => array_map(
                    static fn (string $name, string $value) => [$name, $value],
                    array_keys($data['properties']),
                    array_values($data['properties'])
                ),
            ];
        }

        return [
            'title' => 'Auth',
            'badge' => null,
            'group' => 'Security',
            'metrics' => [
                ['label' => 'Guard', 'value' => strtoupper($data['guard'])],
                ['label' => 'Authenticated', 'value' => $data['authenticated'] ? 'Yes' : 'No'],
            ],
            'blocks' => $blocks,
        ];
    }

    private function readValue(object $user, string $property): ?string
    {
        $value = $this->accessor->getValue($user, $property);

        return $value !== null ? (string)$value : null;
    }

    private function resolveRole(object $user): ?string
    {
        $roleConfig = $this->auth->getRoleConfig();

        if ($roleConfig === null) {
            return null;
        }

        $roleValue = $this->accessor->getValue($user, $roleConfig->getRelation());

        if ($roleValue === null) {
            return null;
        }

        $field = $roleConfig->getField();

        if (is_object($roleValue)) {
            $value = $this->accessor->getValue($roleValue, $field);
            return $value !== null ? (string)$value : null;
        }

        return (string)$roleValue;
    }

    /**
     * @return array<string, string>
     */
    private function dumpProperties(object $user): array
    {
        $ref = new ReflectionClass($user);
        $result = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE) as $prop) {
            if (!$prop->isInitialized($user)) {
                continue;
            }

            $name = $prop->getName();

            if ($this->isMasked($name)) {
                $result[$name] = '••••••••';
                continue;
            }

            $value = $prop->getValue($user);
            $result[$name] = $this->stringify($value);
        }

        return $result;
    }

    private function isMasked(string $propertyName): bool
    {
        $lower = strtolower($propertyName);

        foreach (self::MASKED_FIELDS as $masked) {
            if (str_contains($lower, strtolower($masked))) {
                return true;
            }
        }

        return false;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string)$value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_object($value) => $value::class,
            is_array($value) => json_encode($value) ?: '[]',
            default => (string)$value,
        };
    }
}