<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Tests;

use Neo\Core\Profiler\Profiler;
use Neo\Core\Profiler\Tests\Fixture\Collectors\FakeCollector;
use Neo\Core\Profiler\Toolbar\Toolbar;
use PHPUnit\Framework\TestCase;

class ToolbarTest extends TestCase
{
    protected function setUp(): void
    {
        Profiler::reset();
    }

    protected function tearDown(): void
    {
        Profiler::reset();
    }

    public function testRenderIncludesMemoryUsage(): void
    {
        $profiler = Profiler::getInstance();
        $toolbar = new Toolbar($profiler);

        $html = $toolbar->render();

        self::assertStringContainsString('n-label">Memory', $html);
    }

    public function testRenderIncludesCollectorTabAndPanel(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->addCollector(new FakeCollector());
        $toolbar = new Toolbar($profiler);

        $html = $toolbar->render();

        self::assertStringContainsString('n-tab-fake">42</div>', $html);
        self::assertStringContainsString('n-panel-fake">42</div>', $html);
        self::assertStringContainsString('id="npane-fake"', $html);
    }

    public function testRenderProducesEmptyTabsWhenNoCollectors(): void
    {
        $profiler = Profiler::getInstance();
        $toolbar = new Toolbar($profiler);

        $html = $toolbar->render();

        self::assertStringNotContainsString('n-tab-fake', $html);
    }
}