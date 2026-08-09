<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use DateTime;
use DateTimeInterface;
use ReflectionNamedType;
use ReflectionProperty;

final class PropertyAccessor
{
    public function getValue(object $entity, string $property): mixed
    {
        if (property_exists($entity, $property)) {
            $refProp = new ReflectionProperty($entity, $property);
            if (!$refProp->isInitialized($entity)) {
                return null;
            }
        }

        foreach (['get' . ucfirst($property), 'is' . ucfirst($property), 'has' . ucfirst($property)] as $getter) {
            if (method_exists($entity, $getter)) {
                return $entity->$getter();
            }
        }

        if (property_exists($entity, $property)) {
            return new ReflectionProperty($entity, $property)->getValue($entity);
        }

        return null;
    }

    public function setValue(object $entity, string $property, mixed $value): void
    {
        $coerced = $this->coerce($entity, $property, $value);

        $setter = 'set' . ucfirst($property);
        if (method_exists($entity, $setter)) {
            $entity->$setter($coerced);
            return;
        }

        if (property_exists($entity, $property)) {
            new ReflectionProperty($entity, $property)->setValue($entity, $coerced);
        }
    }

    private function coerce(object $entity, string $property, mixed $value): mixed
    {
        if (!property_exists($entity, $property)) {
            return $value;
        }

        $type = new ReflectionProperty($entity, $property)->getType();
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $nullable = $type->allowsNull();
        if (($value === null || $value === '') && $nullable) {
            return null;
        }

        return match ($type->getName()) {
            'int' => $value === '' ? ($nullable ? null : 0) : (int) $value,
            'float' => $value === '' ? ($nullable ? null : 0.0) : (float) $value,
            'bool' => (bool) $value,
            'DateTime', DateTime::class => $this->toDateTime($value, $nullable),
            'array' => is_array($value) ? $value : ($value === '' ? [] : [$value]),
            default => (string) $value,
        };
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function toDateTime(mixed $value, bool $nullable): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        if ($value === null || $value === '') {
            return $nullable ? null : new DateTime();
        }
        return new DateTime((string) $value);
    }
}