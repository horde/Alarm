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

use Horde\Alarm\NotificationMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationMethod::class)]
class NotificationMethodTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('notify', NotificationMethod::Notify->value);
        $this->assertEquals('mail', NotificationMethod::Mail->value);
        $this->assertEquals('desktop', NotificationMethod::Desktop->value);
    }

    public function testFromString(): void
    {
        $this->assertEquals(NotificationMethod::Notify, NotificationMethod::from('notify'));
        $this->assertEquals(NotificationMethod::Mail, NotificationMethod::from('mail'));
        $this->assertEquals(NotificationMethod::Desktop, NotificationMethod::from('desktop'));
    }

    public function testAllCases(): void
    {
        $cases = NotificationMethod::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(NotificationMethod::Notify, $cases);
        $this->assertContains(NotificationMethod::Mail, $cases);
        $this->assertContains(NotificationMethod::Desktop, $cases);
    }
}
