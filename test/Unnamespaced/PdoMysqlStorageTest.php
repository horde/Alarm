<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Alarm
 * @subpackage UnitTests
 */
namespace Horde\Alarm\Test\Unnamespaced;
use PDO;
use Horde_Db_Adapter_Pdo_Mysql;

class PdoMysqlStorageTest extends SqlStorageTestBase
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pdo') ||
            !in_array('mysql', PDO::getAvailableDrivers())) {
            self::$reason = 'No pdo extension or no mysql PDO driver';
            return;
        }
        $config = self::getConfig('ALARM_SQL_PDO_MYSQL_TEST_CONFIG',
                                  __DIR__ . '/../../..');
        if ($config && !empty($config['alarm']['sql']['pdo_mysql'])) {
            self::$db = new Horde_Db_Adapter_Pdo_Mysql($config['alarm']['sql']['pdo_mysql']);
            parent::setUpBeforeClass();
        } else {
            self::$reason = 'No pdo_mysql configuration';
        }
    }
}
