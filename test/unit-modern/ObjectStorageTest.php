<?php

declare(strict_types=1);

/**
 * Copyright 2010-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 * @subpackage UnitTests
 */

namespace Horde\Alarm\Test;

use Horde\Alarm\AlarmConfig;
use Horde\Alarm\NotificationMethod;
use Horde\Alarm\ObjectStorage;
use Horde_Date;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectStorage::class)]
class ObjectStorageTest extends TestCase
{
    private ObjectStorage $storage;
    private Horde_Date $now;

    protected function setUp(): void
    {
        $this->storage = new ObjectStorage();
        $this->now = new Horde_Date(time());
    }

    public function testSetAndGet(): void
    {
        $alarm = new AlarmConfig(
            id: 'test1',
            user: 'john',
            start: $this->now,
            title: 'Test Alarm',
            text: 'Test description',
        );

        $this->storage->set($alarm);
        $retrieved = $this->storage->get('test1', 'john');

        $this->assertEquals('test1', $retrieved->id);
        $this->assertEquals('john', $retrieved->user);
        $this->assertEquals('Test Alarm', $retrieved->title);
        $this->assertEquals('Test description', $retrieved->text);
    }

    #[Depends('testSetAndGet')]
    public function testExists(): void
    {
        $alarm = new AlarmConfig(
            id: 'test2',
            user: 'jane',
            start: $this->now,
            title: 'Test',
        );

        $this->assertFalse($this->storage->exists('test2', 'jane'));
        $this->storage->set($alarm);
        $this->assertTrue($this->storage->exists('test2', 'jane'));
    }

    #[Depends('testSetAndGet')]
    public function testUpdate(): void
    {
        $alarm = new AlarmConfig(
            id: 'test3',
            user: 'bob',
            start: $this->now,
            title: 'Original',
        );
        $this->storage->set($alarm);

        $updated = new AlarmConfig(
            id: 'test3',
            user: 'bob',
            start: $this->now,
            title: 'Updated',
        );
        $this->storage->set($updated);

        $retrieved = $this->storage->get('test3', 'bob');
        $this->assertEquals('Updated', $retrieved->title);
    }

    #[Depends('testSetAndGet')]
    public function testListAlarms(): void
    {
        $past = new Horde_Date(time() - 3600);
        $future = new Horde_Date(time() + 3600);

        $alarm1 = new AlarmConfig(id: 'list1', user: 'user', start: $past, title: 'Past');
        $alarm2 = new AlarmConfig(id: 'list2', user: 'user', start: $future, title: 'Future');

        $this->storage->set($alarm1);
        $this->storage->set($alarm2);

        $active = $this->storage->listAlarms('user', $this->now);
        $this->assertCount(1, $active);
        $this->assertEquals('list1', $active[0]->id);
    }

    #[Depends('testSetAndGet')]
    public function testListAlarmsWithEnd(): void
    {
        $past = new Horde_Date(time() - 7200);
        $start = new Horde_Date(time() - 3600);
        $end = new Horde_Date(time() + 3600);

        $alarm = new AlarmConfig(
            id: 'range1',
            user: 'user',
            start: $start,
            end: $end,
            title: 'Range Test',
        );
        $this->storage->set($alarm);

        $active = $this->storage->listAlarms('user', $this->now);
        $this->assertCount(1, $active);

        $afterEnd = new Horde_Date(time() + 7200);
        $expired = $this->storage->listAlarms('user', $afterEnd);
        $this->assertCount(0, $expired);
    }

    #[Depends('testSetAndGet')]
    public function testSnooze(): void
    {
        $alarm = new AlarmConfig(
            id: 'snooze1',
            user: 'user',
            start: new Horde_Date(time() - 3600),
            title: 'Snooze Test',
        );
        $this->storage->set($alarm);

        $snoozeUntil = new Horde_Date(time() + 600);
        $this->storage->snooze('snooze1', 'user', $snoozeUntil);

        $this->assertTrue($this->storage->isSnoozed('snooze1', 'user', $this->now));
    }

    #[Depends('testSetAndGet')]
    public function testDismiss(): void
    {
        $alarm = new AlarmConfig(
            id: 'dismiss1',
            user: 'user',
            start: new Horde_Date(time() - 3600),
            title: 'Dismiss Test',
        );
        $this->storage->set($alarm);

        $this->storage->dismiss('dismiss1', 'user');
        $this->assertTrue($this->storage->isSnoozed('dismiss1', 'user', $this->now));

        $active = $this->storage->listAlarms('user', $this->now);
        $this->assertCount(0, $active);
    }

    #[Depends('testSetAndGet')]
    public function testDelete(): void
    {
        $alarm = new AlarmConfig(
            id: 'delete1',
            user: 'user',
            start: $this->now,
            title: 'Delete Test',
        );
        $this->storage->set($alarm);
        $this->assertTrue($this->storage->exists('delete1', 'user'));

        $this->storage->delete('delete1', 'user');
        $this->assertFalse($this->storage->exists('delete1', 'user'));
    }

    #[Depends('testSetAndGet')]
    public function testUpdateInternal(): void
    {
        $alarm = new AlarmConfig(
            id: 'internal1',
            user: 'user',
            start: $this->now,
            title: 'Internal Test',
        );
        $this->storage->set($alarm);

        $this->storage->updateInternal('internal1', 'user', ['key' => 'value']);
        $retrieved = $this->storage->get('internal1', 'user');
        $this->assertEquals(['key' => 'value'], $retrieved->internal);
    }

    #[Depends('testSetAndGet')]
    public function testGlobalAlarms(): void
    {
        $global = new AlarmConfig(
            id: 'global1',
            user: '',
            start: $this->now,
            title: 'Global',
        );
        $personal = new AlarmConfig(
            id: 'personal1',
            user: 'user',
            start: $this->now,
            title: 'Personal',
        );

        $this->storage->set($global);
        $this->storage->set($personal);

        $globals = $this->storage->globalAlarms();
        $this->assertCount(1, $globals);
        $this->assertEquals('global1', $globals[0]->id);
    }

    #[Depends('testSetAndGet')]
    public function testRecurringWithInstanceId(): void
    {
        $alarm1 = new AlarmConfig(
            id: 'recurring',
            user: 'user',
            start: $this->now,
            title: 'Instance 1',
            instanceId: 'inst-1',
        );
        $this->storage->set($alarm1);

        $alarm2 = new AlarmConfig(
            id: 'recurring',
            user: 'user',
            start: $this->now,
            title: 'Instance 2',
            instanceId: 'inst-2',
        );
        $this->storage->set($alarm2);

        $this->assertTrue($this->storage->exists('recurring', 'user', 'inst-2'));
        $this->assertFalse($this->storage->exists('recurring', 'user', 'inst-1'));

        $retrieved = $this->storage->get('recurring', 'user');
        $this->assertEquals('Instance 2', $retrieved->title);
        $this->assertEquals('inst-2', $retrieved->instanceId);
    }

    #[Depends('testSetAndGet')]
    public function testListAlarmsChronologicalOrder(): void
    {
        $time1 = new Horde_Date(time() - 7200);
        $time2 = new Horde_Date(time() - 3600);
        $time3 = new Horde_Date(time() - 1800);

        $alarm2 = new AlarmConfig(id: 'alarm2', user: 'user', start: $time2, title: 'Second');
        $alarm3 = new AlarmConfig(id: 'alarm3', user: 'user', start: $time3, title: 'Third');
        $alarm1 = new AlarmConfig(id: 'alarm1', user: 'user', start: $time1, title: 'First');

        $this->storage->set($alarm2);
        $this->storage->set($alarm3);
        $this->storage->set($alarm1);

        $alarms = $this->storage->listAlarms('user', $this->now);
        $this->assertCount(3, $alarms);
        $this->assertEquals('alarm1', $alarms[0]->id);
        $this->assertEquals('alarm2', $alarms[1]->id);
        $this->assertEquals('alarm3', $alarms[2]->id);
    }
}
