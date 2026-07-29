<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Validator\Assert\Exists;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class ExistsValidator implements ConstraintValidatorInterface
{
    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Exists) {
            return;
        }

        $metadata = $this->em->getClassMetadata($constraint->entity);

        $table = $metadata->table;
        $column = $metadata->getColumnName($constraint->field);

        $platform = $this->em->getPlatform();
        $sql = sprintf(
            'SELECT COUNT(*) AS c FROM %s WHERE %s = ?',
            $platform->quoteIdentifier($table),
            $platform->quoteIdentifier($column)
        );

        $row = $this->em->getDatabase()->fetch($sql, [$value]);

        if ((int) ($row['c'] ?? 0) === 0) {
            $context->addViolation($constraint->getMessage() ?: 'This value does not exist.');
        }
    }
}
