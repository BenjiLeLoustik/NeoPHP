<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Attribute;

use Attribute;

/* ---------- HAS ONE ---------- */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne
{
    public string $target;       // classe cible
    public string $foreignKey;   // FK dans le modèle distant
    public string $localKey;     // PK locale
    public bool $nullable;

    public function __construct(
        string $target,
        string $foreignKey,
        string $localKey = 'id',
        bool $nullable = true
    ) {
        $this->target     = $target;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->nullable   = $nullable;
    }
}
