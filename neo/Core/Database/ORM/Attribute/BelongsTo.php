<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Attribute;

use Attribute;

/* ---------- BELONGS TO ---------- */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    public string $target;       // classe cible
    public string $foreignKey;   // FK dans CE modèle
    public string $ownerKey;     // PK cible
    public bool $nullable;

    public function __construct(
        string $target,
        string $foreignKey,
        string $ownerKey = 'id',
        bool $nullable = false
    ) {
        $this->target     = $target;
        $this->foreignKey = $foreignKey;
        $this->ownerKey   = $ownerKey;
        $this->nullable   = $nullable;
    }
}
