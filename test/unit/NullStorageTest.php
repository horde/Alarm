<?php

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Alarm
 * @subpackage UnitTests
 */

namespace Horde\Alarm\Test\Unnamespaced;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use Horde_Alarm_Null;
use Horde_Alarm_Exception;

#[CoversNothing]
class NullStorageTest extends StorageTestBase
{
    public function testFactory()
    {
        self::$alarm = new Horde_Alarm_Null();
        $this->assertInstanceOf(Horde_Alarm_Null::class, self::$alarm);
    }

    #[Depends('testFactory')]
    public function testExists()
    {
        $this->assertFalse(self::$alarm->exists('personalalarm', 'john'));
    }

    #[Depends('testFactory')]
    public function testGet()
    {
        $this->expectException(Horde_Alarm_Exception::class);
        $alarm = self::$alarm->get('personalalarm', 'john');
    }

    #[Depends('testFactory')]
    public function testUpdate($alarm)
    {
        $this->markTestIncomplete();
    }

    #[Depends('testFactory')]
    public function testListAlarms()
    {
        self::$date->min--;
        self::$alarm->set(['id' => 'publicalarm',
            'start' => self::$date,
            'end' => self::$end,
            'methods' => [],
            'params' => [],
            'title' => 'This is a public alarm.']);
        $list = self::$alarm->listAlarms('john');
        $this->assertEquals(0, count($list));
    }

    #[Depends('testFactory')]
    public function testDelete()
    {
        self::$alarm->delete('publicalarm', '');
        $list = self::$alarm->listAlarms('john');
        $this->assertEquals(0, count($list));
    }

    #[Depends('testFactory')]
    public function testSnooze()
    {
        $this->assertFalse(self::$alarm->isSnoozed('personalalarm', 'john'));
    }

    #[Depends('testFactory')]
    public function testAlarmWithoutEnd()
    {
        self::$alarm->set(['id' => 'noend',
            'user' => 'john',
            'start' => self::$date,
            'methods' => ['notify'],
            'params' => [],
            'title' => 'This is an alarm without end.']);
        $list = self::$alarm->listAlarms('john', self::$end);
        $this->assertEquals(0, count($list));
    }
}
