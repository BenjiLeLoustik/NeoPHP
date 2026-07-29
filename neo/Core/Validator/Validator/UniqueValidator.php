<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Validator\Assert\Unique;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class UniqueValidator implements ConstraintValidatorInterface
{
    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Unique) {
            return;
        }

        $metadata = $this->em->getClassMetadata($context->getModel()::class);
        $field = $constraint->field ?? $context->getField();

        $table = $metadata->table;
        $column = $metadata->getColumnName($field);

        $platform = $this->em->getPlatform();
        $sql = sprintf(
            'SELECT COUNT(*) AS c FROM %s WHERE %s = ?',
            $platform->quoteIdentifier($table),
            $platform->quoteIdentifier($column)
        );
        $params = [$value];

        $currentId = $metadata->getIdentifierValue($context->getModel());
        if ($currentId !== null) {
            $sql .= sprintf(' AND %s != ?', $platform->quoteIdentifier($metadata->getSingleIdColumnName()));
            $params[] = $currentId;
        }

        foreach ($constraint->conditions as $condColumn => $condValue) {
            if ($condValue === null) {
                $sql .= sprintf(' AND %s IS NULL', $platform->quoteIdentifier($condColumn));
                continue;
            }
            $sql .= sprintf(' AND %s = ?', $platform->quoteIdentifier($condColumn));
            $params[] = $condValue;
        }

        $row = $this->em->getDatabase()->fetch($sql, $params);

        if ((int) ($row['c'] ?? 0) > 0) {
            $context->addViolation($constraint->getMessage() ?: 'This value is already used.');
        }
    }
}
