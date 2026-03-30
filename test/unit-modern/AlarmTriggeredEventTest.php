<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */

namespace Horde\Alarm\Test;

use Horde\Alarm\AlarmConfig;
use Horde\Alarm\AlarmTriggeredEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AlarmTriggeredEvent::class)]
class AlarmTriggeredEventTest extends TestCase
{
    private AlarmConfig $alarm;

    protected function setUp(): void
    {
        $this->alarm = new AlarmConfig(
            id: 'test-alarm',
            user: 'john',
            start: new \Horde_Date('2026-04-01 14:00:00'),
            title: 'Test Alarm',
        );
    }

    public function testEventContainsAlarm(): void
    {
        $event = new AlarmTriggeredEvent($this->alarm);

        $this->assertSame($this->alarm, $event->alarm);
    }

    public function testPropagationNotStoppedByDefault(): void
    {
        $event = new AlarmTriggeredEvent($this->alarm);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagation(): void
    {
        $event = new AlarmTriggeredEvent($this->alarm);

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testMultipleStopPropagationCalls(): void
    {
        $event = new AlarmTriggeredEvent($this->alarm);

        $event->stopPropagation();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
