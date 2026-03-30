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

use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Db_Adapter_Pdo_Sqlite;

#[CoversNothing]
class PdoSqliteStorageTest extends SqlStorageTestBase
{
    public static function setUpBeforeClass(): void
    {
        try {
            self::$db = new Horde_Db_Adapter_Pdo_Sqlite([
                'dbname' => ':memory:',
                'charset' => 'utf-8'
            ]);
            parent::setUpBeforeClass();
        } catch (\Exception $e) {
            self::$reason = 'Sqlite not available: ' . $e->getMessage();
        }
    }
}
