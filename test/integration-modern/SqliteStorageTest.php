<?php

declare(strict_types=1);

/**
 * Copyright 2007-2026 The Horde Project (http://www.horde.org/)
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
use Horde\Alarm\SqlStorage;
use Horde_Date;
use Horde\Db\Adapter\Pdo\Sqlite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlStorage::class)]
class SqliteStorageTest extends TestCase
{
    private static ?Sqlite $db = null;
    private static ?SqlStorage $storage = null;
    private Horde_Date $now;

    public static function setUpBeforeClass(): void
    {
        self::$db = new Sqlite([
            'dbname' => ':memory:',
            'charset' => 'utf-8',
        ]);

        // Create schema
        self::$db->execute('
            CREATE TABLE horde_alarms (
                alarm_id VARCHAR(255) NOT NULL,
                alarm_uid VARCHAR(255),
                alarm_start DATETIME NOT NULL,
                alarm_end DATETIME,
                alarm_methods VARCHAR(255),
                alarm_params TEXT,
                alarm_title VARCHAR(255) NOT NULL,
                alarm_text TEXT,
                alarm_snooze DATETIME,
                alarm_dismissed INTEGER DEFAULT 0 NOT NULL,
                alarm_internal TEXT,
                alarm_instanceid VARCHAR(255),
                PRIMARY KEY (alarm_id, alarm_uid)
            )
        ');

        self::$storage = new SqlStorage(self::$db);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db !== null) {
            self::$db->disconnect();
        }
    }

    protected function setUp(): void
    {
        $this->now = new Horde_Date(time());

        // Clean table before each test
        if (self::$db !== null) {
            self::$db->execute('DELETE FROM horde_alarms');
        }
    }

    public function testSetAndGet(): void
    {
        $alarm = new AlarmConfig(
            id: 'sql1',
            user: 'testuser',
            start: $this->now,
            title: 'SQL Test',
            text: 'Test description',
            methods: [NotificationMethod::Notify],
            params: ['notify' => ['sound' => 'beep.wav']],
        );

        self::$storage->set($alarm);
        $retrieved = self::$storage->get('sql1', 'testuser');

        $this->assertEquals('sql1', $retrieved->id);
        $this->assertEquals('testuser', $retrieved->user);
        $this->assertEquals('SQL Test', $retrieved->title);
        $this->assertEquals('Test description', $retrieved->text);
        $this->assertCount(1, $retrieved->methods);
        $this->assertEquals(NotificationMethod::Notify, $retrieved->methods[0]);
        $this->assertEquals(['notify' => ['sound' => 'beep.wav']], $retrieved->params);
    }

    #[Depends('testSetAndGet')]
    public function testExists(): void
    {
        $alarm = new AlarmConfig(
            id: 'exists1',
            user: 'user',
            start: $this->now,
            title: 'Test',
        );

        $this->assertFalse(self::$storage->exists('exists1', 'user'));
        self::$storage->set($alarm);
        $this->assertTrue(self::$storage->exists('exists1', 'user'));
    }

    #[Depends('testSetAndGet')]
    public function testUpdate(): void
    {
        $alarm = new AlarmConfig(
            id: 'update1',
            user: 'user',
            start: $this->now,
            title: 'Original',
        );
        self::$storage->set($alarm);

        $updated = new AlarmConfig(
            id: 'update1',
            user: 'user',
            start: $this->now,
            title: 'Updated',
            text: 'New description',
        );
        self::$storage->set($updated);

        $retrieved = self::$storage->get('update1', 'user');
        $this->assertEquals('Updated', $retrieved->title);
        $this->assertEquals('New description', $retrieved->text);
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
        self::$storage->set($alarm);
        $this->assertTrue(self::$storage->exists('delete1', 'user'));

        self::$storage->delete('delete1', 'user');
        $this->assertFalse(self::$storage->exists('delete1', 'user'));
    }

    #[Depends('testSetAndGet')]
    public function testListAlarms(): void
    {
        $past = new Horde_Date(time() - 3600);
        $future = new Horde_Date(time() + 3600);

        $alarm1 = new AlarmConfig(id: 'list1', user: 'user1', start: $past, title: 'Past');
        $alarm2 = new AlarmConfig(id: 'list2', user: 'user1', start: $future, title: 'Future');
        $alarm3 = new AlarmConfig(id: 'list3', user: 'user2', start: $past, title: 'Other User');

        self::$storage->set($alarm1);
        self::$storage->set($alarm2);
        self::$storage->set($alarm3);

        $alarms = self::$storage->listAlarms('user1', $this->now);
        $this->assertCount(1, $alarms);
        $this->assertEquals('list1', $alarms[0]->id);
    }

    #[Depends('testSetAndGet')]
    public function testGlobalAlarms(): void
    {
        $global = new AlarmConfig(
            id: 'global1',
            user: '',
            start: $this->now,
            title: 'Global Alarm',
        );
        $personal = new AlarmConfig(
            id: 'personal1',
            user: 'user',
            start: $this->now,
            title: 'Personal',
        );

        self::$storage->set($global);
        self::$storage->set($personal);

        $globals = self::$storage->globalAlarms();
        $this->assertCount(1, $globals);
        $this->assertEquals('global1', $globals[0]->id);
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
        self::$storage->set($alarm);

        $snoozeUntil = new Horde_Date(time() + 600);
        self::$storage->snooze('snooze1', 'user', $snoozeUntil);

        $this->assertTrue(self::$storage->isSnoozed('snooze1', 'user', $this->now));

        $active = self::$storage->listAlarms('user', $this->now);
        $this->assertCount(0, $active);
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
        self::$storage->set($alarm);

        self::$storage->dismiss('dismiss1', 'user');
        $this->assertTrue(self::$storage->isSnoozed('dismiss1', 'user', $this->now));

        $active = self::$storage->listAlarms('user', $this->now);
        $this->assertCount(0, $active);
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
        self::$storage->set($alarm);

        self::$storage->updateInternal('internal1', 'user', ['mail' => ['sent' => true]]);
        $retrieved = self::$storage->get('internal1', 'user');
        $this->assertEquals(['mail' => ['sent' => true]], $retrieved->internal);
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
        self::$storage->set($alarm1);

        $alarm2 = new AlarmConfig(
            id: 'recurring',
            user: 'user',
            start: $this->now,
            title: 'Instance 2',
            instanceId: 'inst-2',
        );
        self::$storage->set($alarm2);

        $this->assertTrue(self::$storage->exists('recurring', 'user', 'inst-2'));
        $this->assertFalse(self::$storage->exists('recurring', 'user', 'inst-1'));

        $retrieved = self::$storage->get('recurring', 'user');
        $this->assertEquals('Instance 2', $retrieved->title);
        $this->assertEquals('inst-2', $retrieved->instanceId);
    }

    #[Depends('testSetAndGet')]
    public function testGarbageCollection(): void
    {
        $expired = new AlarmConfig(
            id: 'expired',
            user: 'user',
            start: new Horde_Date(time() - 7200),
            end: new Horde_Date(time() - 3600),
            title: 'Expired',
        );
        $active = new AlarmConfig(
            id: 'active',
            user: 'user',
            start: new Horde_Date(time() - 3600),
            end: new Horde_Date(time() + 3600),
            title: 'Active',
        );

        self::$storage->set($expired);
        self::$storage->set($active);

        self::$storage->gc();

        $this->assertFalse(self::$storage->exists('expired', 'user'));
        $this->assertTrue(self::$storage->exists('active', 'user'));
    }
}
