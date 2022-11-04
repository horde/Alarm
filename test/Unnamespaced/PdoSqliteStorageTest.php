<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Alarm
 * @subpackage UnitTests
 *
 */
namespace Horde\Alarm\Test\Unnamespaced;
use Horde_Test_Factory_Db;
use Horde_Test_Exception;

class PdoSqliteStorageTest extends SqlStorageTestBase
{
    public static function setUpBeforeClass(): void
    {
        $factory_db = new Horde_Test_Factory_Db();

        try {
            self::$db = $factory_db->create();
            parent::setUpBeforeClass();
        } catch (Horde_Test_Exception $e) {
            self::$reason = 'Sqlite not available.';
        }
    }
}
