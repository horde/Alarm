<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Alarm
 * @subpackage UnitTests
 */
namespace Horde\Alarm\Storage;
use \Horde_Alarm_Object;
use \PDO;

class ObjectTest extends BaseTestCase
{
    public function testFactory()
    {
        self::$alarm = new Horde_Alarm_Object();
        $this->markTestIncomplete();
    }
}
