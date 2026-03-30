<?php

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Alarm
 * @subpackage UnitTests
 */

namespace Horde\Alarm\Test\Unnamespaced;

use PHPUnit\Framework\Attributes\Depends;
use Horde_Log_Logger;
use Horde_Log_Handler_Cli;
use Horde_Db_Migration_Migrator;
use Horde_Alarm_Sql;
use Horde_Date;
use PEAR_Config;

abstract class SqlStorageTestBase extends StorageTestBase
{
    protected static $db;
    protected static $migrator;
    protected static $reason;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $logger = new Horde_Log_Logger(new Horde_Log_Handler_Cli());
        //self::$db->setLogger($logger);
        $dir = __DIR__ . '/../../migration/Horde/Alarm';
        if (!is_dir($dir)) {
            error_reporting(E_ALL & ~E_DEPRECATED);
            $dir = PEAR_Config::singleton()
                ->get('data_dir', null, 'pear.horde.org')
                . '/Horde_Alarm/migration';
            error_reporting(E_ALL | E_STRICT);
        }
        self::$migrator = new Horde_Db_Migration_Migrator(
            self::$db,
            null,//$logger,
            ['migrationsPath' => $dir,
                'schemaTableName' => 'horde_alarm_schema_info']
        );
        self::$migrator->up();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$migrator) {
            self::$migrator->down();
        }
        if (self::$db) {
            self::$db->disconnect();
        }
        self::$db = self::$migrator = null;
    }

    public function setUp(): void
    {
        if (!self::$db) {
            $this->markTestSkipped(self::$reason);
        }
        parent::setUp();
    }

    /**
     * Get test configuration from environment variable or config file.
     */
    protected static function getConfig(string $envVar, string $basePath): ?array
    {
        // Check environment variable
        if ($config = getenv($envVar)) {
            return json_decode($config, true);
        }

        // Check config file
        $configFile = $basePath . '/conf.php';
        if (file_exists($configFile)) {
            return include $configFile;
        }

        return null;
    }

    public function testFactory()
    {
        self::$alarm = new Horde_Alarm_Sql(['db' => self::$db, 'charset' => 'UTF-8']);
        $this->assertInstanceOf(Horde_Alarm_Sql::class, self::$alarm);
        self::$alarm->initialize();
        self::$alarm->gc(true);
    }

    #[Depends('testFactory')]
    public function testSetWithInstanceId()
    {
        $now = time();
        $date = new Horde_Date($now);
        $end = new Horde_Date($now + 3600);
        $hash = ['id' => '123',
            'user' => 'john',
            'start' => $date,
            'end' => $end,
            'methods' => [],
            'params' => [],
            'title' => 'This is the first instance',
            'instanceid' => '03052014'];

        self::$alarm->set($hash);
        $alarm = self::$alarm->get('123', 'john');
        $this->assertEquals('123', $alarm['id']);
        $this->assertEquals('This is the first instance', $alarm['title']);
        $hash['instanceid'] = '03062014';
        $hash['title'] = 'This is the second instance';
        self::$alarm->set($hash);
        $alarm = self::$alarm->get('123', 'john');
        $this->assertEquals('123', $alarm['id']);
        $this->assertEquals('This is the second instance', $alarm['title']);

        // clean
        self::$alarm->delete('123', 'john');
    }
}
