<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Scanner\Tests\Fixture;

#[Tag('on-class')]
final class AnnotatedClass
{
    #[Tag('on-property')]
    public string $name = '';

    #[Tag('on-method')]
    public function doSomething(#[Tag('on-parameter')] string $input): void {}

    public function noAttribute(): void {}
}