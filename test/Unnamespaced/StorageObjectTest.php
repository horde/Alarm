<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Alarm
 * @subpackage UnitTests
 */
namespace Horde\Alarm\Test\Unnamespaced;

use Horde_Alarm;
use Horde_Alarm_Object;

class StorageObjectTest extends StorageTestBase
{
    public function testFactory()
    {
        self::$alarm = new Horde_Alarm_Object();
        $this->assertInstanceOf(Horde_Alarm_Object::class, self::$alarm);
    }
}
