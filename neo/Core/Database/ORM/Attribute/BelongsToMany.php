<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToMany
{
    public string $target;
    public string $pivotTable;
    public string $pivotLocalKey;
    public string $pivotTargetKey;
    public string $localKey;
    public string $relatedKey;

    public function __construct(
        string $target,
        string $pivotTable,
        string $pivotLocalKey,
        string $pivotTargetKey,
        string $localKey = 'id',
        string $relatedKey = 'id'
    ) {
        $this->target = $target;
        $this->pivotTable = $pivotTable;
        $this->pivotLocalKey  = $pivotLocalKey;
        $this->pivotTargetKey = $pivotTargetKey;
        $this->localKey = $localKey;
        $this->relatedKey = $relatedKey;
    }
}
