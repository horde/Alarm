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
use Horde_Date;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlarmConfig::class)]
class AlarmConfigTest extends TestCase
{
    public function testConstructor(): void
    {
        $start = new Horde_Date(time());
        $config = new AlarmConfig(
            id: 'test1',
            user: 'john',
            start: $start,
            title: 'Test Alarm',
        );

        $this->assertEquals('test1', $config->id);
        $this->assertEquals('john', $config->user);
        $this->assertEquals($start, $config->start);
        $this->assertEquals('Test Alarm', $config->title);
        $this->assertNull($config->end);
        $this->assertNull($config->text);
        $this->assertNull($config->snooze);
        $this->assertEquals([], $config->methods);
        $this->assertEquals([], $config->params);
        $this->assertEquals([], $config->internal);
        $this->assertNull($config->instanceId);
    }

    public function testFromArrayMinimal(): void
    {
        $start = new Horde_Date(time());
        $data = [
            'id' => 'test2',
            'user' => 'jane',
            'start' => $start,
            'methods' => [],
            'params' => [],
            'title' => 'Test',
        ];

        $config = AlarmConfig::fromArray($data);

        $this->assertEquals('test2', $config->id);
        $this->assertEquals('jane', $config->user);
        $this->assertEquals($start, $config->start);
        $this->assertEquals('Test', $config->title);
    }

    public function testFromArrayComplete(): void
    {
        $start = new Horde_Date('2026-04-01 10:00:00');
        $end = new Horde_Date('2026-04-01 11:00:00');
        $snooze = new Horde_Date('2026-04-01 10:05:00');

        $data = [
            'id' => 'test3',
            'user' => 'bob',
            'start' => $start,
            'end' => $end,
            'methods' => ['notify', 'mail'],
            'params' => ['notify' => ['sound' => 'beep.wav']],
            'title' => 'Complete Test',
            'text' => 'Full description',
            'snooze' => $snooze,
            'internal' => ['mail' => ['sent' => true]],
            'instanceid' => 'recurring-1',
        ];

        $config = AlarmConfig::fromArray($data);

        $this->assertEquals('test3', $config->id);
        $this->assertEquals('bob', $config->user);
        $this->assertEquals($start, $config->start);
        $this->assertEquals($end, $config->end);
        $this->assertCount(2, $config->methods);
        $this->assertEquals(NotificationMethod::Notify, $config->methods[0]);
        $this->assertEquals(NotificationMethod::Mail, $config->methods[1]);
        $this->assertEquals(['notify' => ['sound' => 'beep.wav']], $config->params);
        $this->assertEquals('Complete Test', $config->title);
        $this->assertEquals('Full description', $config->text);
        $this->assertEquals($snooze, $config->snooze);
        $this->assertEquals(['mail' => ['sent' => true]], $config->internal);
        $this->assertEquals('recurring-1', $config->instanceId);
    }

    public function testToArray(): void
    {
        $start = new Horde_Date('2026-04-01 10:00:00');
        $end = new Horde_Date('2026-04-01 11:00:00');

        $config = new AlarmConfig(
            id: 'test4',
            user: 'alice',
            start: $start,
            end: $end,
            methods: [NotificationMethod::Desktop],
            params: ['desktop' => ['icon' => 'icon.png']],
            title: 'Array Test',
            text: 'Description text',
        );

        $array = $config->toArray();

        $this->assertEquals('test4', $array['id']);
        $this->assertEquals('alice', $array['user']);
        $this->assertEquals($start, $array['start']);
        $this->assertEquals($end, $array['end']);
        $this->assertEquals(['desktop'], $array['methods']);
        $this->assertEquals(['desktop' => ['icon' => 'icon.png']], $array['params']);
        $this->assertEquals('Array Test', $array['title']);
        $this->assertEquals('Description text', $array['text']);
    }

    public function testWithMethod(): void
    {
        $start = new Horde_Date(time());
        $config = new AlarmConfig(
            id: 'test5',
            user: 'user1',
            start: $start,
            title: 'Original',
        );

        $modified = $config->with(['title' => 'Modified']);

        $this->assertEquals('Original', $config->title);
        $this->assertEquals('Modified', $modified->title);
        $this->assertEquals('test5', $modified->id);
        $this->assertEquals('user1', $modified->user);
    }

    public function testGlobalAlarm(): void
    {
        $start = new Horde_Date(time());
        $config = new AlarmConfig(
            id: 'global1',
            user: '',
            start: $start,
            title: 'Global Alarm',
        );

        $this->assertEquals('', $config->user);
    }

    public function testRoundTripConversion(): void
    {
        $start = new Horde_Date('2026-04-01 14:30:00');
        $original = [
            'id' => 'roundtrip',
            'user' => 'testuser',
            'start' => $start,
            'methods' => ['notify', 'mail', 'desktop'],
            'params' => ['test' => 'value'],
            'title' => 'Round Trip',
            'text' => 'Test text',
            'internal' => ['data' => 'internal'],
            'instanceid' => 'inst-1',
        ];

        $config = AlarmConfig::fromArray($original);
        $result = $config->toArray();

        $this->assertEquals($original['id'], $result['id']);
        $this->assertEquals($original['user'], $result['user']);
        $this->assertEquals($original['start'], $result['start']);
        $this->assertEquals($original['methods'], $result['methods']);
        $this->assertEquals($original['params'], $result['params']);
        $this->assertEquals($original['title'], $result['title']);
        $this->assertEquals($original['text'], $result['text']);
        $this->assertEquals($original['internal'], $result['internal']);
        $this->assertEquals($original['instanceid'], $result['instanceid']);
    }
}
