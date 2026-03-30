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
use Horde\Alarm\AlarmException;
use Horde\Alarm\NotificationMethod;
use Horde\Alarm\NullStorage;
use Horde_Date;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullStorage::class)]
class NullStorageTest extends TestCase
{
    private NullStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new NullStorage();
    }

    public function testListAlarmsReturnsEmpty(): void
    {
        $time = new Horde_Date(time());
        $this->assertEquals([], $this->storage->listAlarms('user', $time));
        $this->assertEquals([], $this->storage->listAlarms(null, $time));
    }

    public function testGlobalAlarmsReturnsEmpty(): void
    {
        $this->assertEquals([], $this->storage->globalAlarms());
    }

    public function testGetThrowsException(): void
    {
        $this->expectException(AlarmException::class);
        $this->expectExceptionMessage('Alarm not found');
        $this->storage->get('test', 'user');
    }

    public function testSetDoesNothing(): void
    {
        $alarm = new AlarmConfig(
            id: 'test',
            user: 'user',
            start: new Horde_Date(time()),
            title: 'Test',
        );
        $this->storage->set($alarm);
        $this->assertFalse($this->storage->exists('test', 'user'));
    }

    public function testUpdateInternalDoesNothing(): void
    {
        $this->storage->updateInternal('test', 'user', ['key' => 'value']);
        $this->assertFalse($this->storage->exists('test', 'user'));
    }

    public function testExistsReturnsFalse(): void
    {
        $this->assertFalse($this->storage->exists('test', 'user'));
        $this->assertFalse($this->storage->exists('test', 'user', 'instance-1'));
    }

    public function testSnoozeDoesNothing(): void
    {
        $this->storage->snooze('test', 'user', new Horde_Date(time()));
        $this->assertFalse($this->storage->exists('test', 'user'));
    }

    public function testIsSnoozedReturnsFalse(): void
    {
        $this->assertFalse($this->storage->isSnoozed('test', 'user', new Horde_Date(time())));
    }

    public function testDismissDoesNothing(): void
    {
        $this->storage->dismiss('test', 'user');
        $this->assertFalse($this->storage->exists('test', 'user'));
    }

    public function testDeleteDoesNothing(): void
    {
        $this->storage->delete('test', 'user');
        $this->assertFalse($this->storage->exists('test', 'user'));
    }

    public function testGcDoesNothing(): void
    {
        $this->storage->gc();
        $this->assertTrue(true);
    }

    public function testInitializeDoesNothing(): void
    {
        $this->storage->initialize();
        $this->assertTrue(true);
    }
}
