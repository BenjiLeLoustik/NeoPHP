<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Attribute;

use Attribute;

/* ---------- BELONGS TO MANY ---------- */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToMany
{
    public string $target;          // classe cible
    public string $pivotTable;      // table pivot
    public string $pivotLocalKey; // FK pivot → modèle courant
    public string $pivotTargetKey; // FK pivot → modèle cible
    public string $localKey;        // PK locale
    public string $relatedKey;      // PK cible

    public function __construct(
        string $target,
        string $pivotTable,
        string $pivotLocalKey,
        string $pivotTargetKey,
        string $localKey = 'id',
        string $relatedKey = 'id'
    ) {
        $this->target          = $target;
        $this->pivotTable      = $pivotTable;
        $this->foreignPivotKey = $pivotLocalKey;
        $this->relatedPivotKey = $pivotTargetKey;
        $this->localKey        = $localKey;
        $this->relatedKey      = $relatedKey;
    }
}
