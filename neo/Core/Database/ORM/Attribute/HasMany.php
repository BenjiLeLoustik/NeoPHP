<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Attribute;

use Attribute;

/* ---------- HAS MANY ---------- */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    public string $target;       // classe cible
    public string $foreignKey;   // FK dans le modèle distant
    public string $localKey;     // PK locale

    public function __construct(
        string $target,
        string $foreignKey,
        string $localKey = 'id'
    ) {
        $this->target     = $target;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
    }
}
