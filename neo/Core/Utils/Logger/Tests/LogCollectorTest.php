<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger\Tests;

use Neo\Core\Utils\Logger\LogCollector;
use PHPUnit\Framework\TestCase;

class LogCollectorTest extends TestCase
{
    public function testGetNameReturnsLogs(): void
    {
        self::assertSame('logs', new LogCollector()->getName());
    }

    public function testCollectReturnsZeroCountWhenNothingRecorded(): void
    {
        $collector = new LogCollector();

        $data = $collector->collect();

        self::assertSame(0, $data['count']);
        self::assertSame([], $data['by_level']);
        self::assertSame([], $data['entries']);
    }

    public function testRecordAccumulatesEntriesAndCountsByLevel(): void
    {
        $collector = new LogCollector();

        $collector->record('INFO', 'first', [], 'system');
        $collector->record('ERROR', 'second', ['key' => 'value'], 'app');
        $collector->record('INFO', 'third', [], 'system');

        $data = $collector->collect();

        self::assertSame(3, $data['count']);
        self::assertSame(['INFO' => 2, 'ERROR' => 1], $data['by_level']);
        self::assertSame('second', $data['entries'][1]['message']);
        self::assertSame(['key' => 'value'], $data['entries'][1]['context']);
    }

    public function testRenderTabIncludesEntryCount(): void
    {
        $collector = new LogCollector();
        $collector->record('INFO', 'hello', [], 'system');

        $html = $collector->renderTab($collector->collect());

        self::assertStringContainsString('>1<', $html);
    }

    public function testRenderPanelShowsEmptyMessageWhenNoEntries(): void
    {
        $collector = new LogCollector();

        $html = $collector->renderPanel($collector->collect());

        self::assertStringContainsString('Aucun log', $html);
    }

    public function testRenderPanelListsRecordedEntries(): void
    {
        $collector = new LogCollector();
        $collector->record('WARNING', 'Disk almost full', ['percent' => 92], 'monitor');

        $html = $collector->renderPanel($collector->collect());

        self::assertStringContainsString('Disk almost full', $html);
        self::assertStringContainsString('monitor', $html);
        self::assertStringContainsString('percent', $html);
    }
}