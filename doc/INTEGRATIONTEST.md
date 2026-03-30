# Integration Testing Guide

This document explains how to run the full integration test suite with database backends.

## Quick Start

```bash
# Run all tests (60 integration tests will skip without DB config)
phpunit11

# Run only unit tests (no DB needed)
phpunit11 --testsuite=unit
phpunit11 --testsuite=unit-modern

# Run integration tests (requires DB config)
phpunit11 --testsuite=integration
```

## Test Suites

**unit** (25 tests) - Legacy PSR-0 lib/ tests, no external dependencies
**unit-modern** (45 tests) - Modern PSR-4 src/ tests, includes SQLite in-memory
**integration** (72 tests) - Database backend tests, requires config

## SQLite Tests (Always Run)

SQLite tests run automatically with in-memory database:

```bash
phpunit11 test/integration/PdoSqliteStorageTest.php
# 12 tests, 36 assertions - no config needed
```

## MySQL/PostgreSQL/Oracle Tests (Optional)

Integration tests for MySQL, PostgreSQL, and Oracle require database configuration.

### Configuration Methods

**Option 1: Environment Variable**

```bash
export ALARM_SQL_PDO_MYSQL_TEST_CONFIG='{
  "alarm": {
    "sql": {
      "pdo_mysql": {
        "host": "localhost",
        "username": "test",
        "password": "test",
        "dbname": "alarm_test"
      }
    }
  }
}'

phpunit11 test/integration/PdoMysqlStorageTest.php
```

**Option 2: Config File**

Create `test/conf.php`:

```php
<?php
return [
    'alarm' => [
        'sql' => [
            'pdo_mysql' => [
                'host' => 'localhost',
                'username' => 'test',
                'password' => 'test',
                'dbname' => 'alarm_test',
            ],
            'pdo_pgsql' => [
                'host' => 'localhost',
                'username' => 'test',
                'password' => 'test',
                'dbname' => 'alarm_test',
            ],
        ],
    ],
];
```

**Note:** `test/conf.php` is gitignored - safe for local credentials.

## Database Setup

### MySQL

```bash
mysql -u root -p
```

```sql
CREATE DATABASE alarm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'test'@'localhost' IDENTIFIED BY 'test';
GRANT ALL ON alarm_test.* TO 'test'@'localhost';
FLUSH PRIVILEGES;
```

### PostgreSQL

```bash
psql -U postgres
```

```sql
CREATE DATABASE alarm_test;
CREATE USER test WITH PASSWORD 'test';
GRANT ALL PRIVILEGES ON DATABASE alarm_test TO test;
```

### Table Schema

The test suite automatically creates the required table:

```sql
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
);
```

## Test Coverage by Backend

| Backend | Tests | Config Required | Auto-runs |
|---------|-------|-----------------|-----------|
| **SQLite** | 12 | No | ✅ Yes |
| **MySQL (PDO)** | 12 | Yes | ❌ Skips |
| **PostgreSQL (PDO)** | 12 | Yes | ❌ Skips |
| **MySQL (mysqli)** | 12 | Yes | ❌ Skips |
| **MySQL (ext/mysql)** | 12 | Yes | ❌ Skips |
| **Oracle (oci8)** | 12 | Yes | ❌ Skips |

## Expected Test Results

### Without Database Config (Default)

```
Tests: 142, Assertions: 217
├─ Passed: 82
├─ Skipped: 60 (integration tests without DB)
└─ Incomplete: 1 (legacy test intentionally marked incomplete)
```

### With Full Database Config

```
Tests: 142, Assertions: ~300+
├─ Passed: 141
├─ Skipped: 0
└─ Incomplete: 1 (legacy test intentionally marked incomplete)
```

## Troubleshooting

### "No pdo_mysql configuration"

Integration test skipped - this is expected without database config.

### "SQLSTATE[HY000] [2002] Connection refused"

Database not running. Start MySQL/PostgreSQL:

```bash
# MySQL
sudo systemctl start mysql

# PostgreSQL
sudo systemctl start postgresql
```

### "Access denied for user"

Check credentials in `test/conf.php` or environment variable.

### Deprecation Warnings

6 deprecations from `horde/db` dependency are expected and don't affect functionality:

```
Callables of the form ["Horde\Db\Adapter\Pdo\Sqlite", "parent::execute"] are deprecated
```

This is a horde/db issue, not horde/alarm. Our code has 0 deprecations.

## Running Specific Backend Tests

```bash
# SQLite only (always works)
phpunit11 test/integration/PdoSqliteStorageTest.php

# MySQL PDO
phpunit11 test/integration/PdoMysqlStorageTest.php

# PostgreSQL PDO
phpunit11 test/integration/PdoPgsqlStorageTest.php

# MySQL mysqli
phpunit11 test/integration/MysqliStorageTest.php

# Oracle
phpunit11 test/integration/Oci8StorageTest.php
```

## CI/CD Integration

For automated testing, use SQLite (no config needed):

```bash
# Fast: unit tests only
phpunit11 --testsuite=unit --testsuite=unit-modern

# Comprehensive: includes SQLite integration
phpunit11 --exclude-group=requires-external-db
```

Or set up test databases in CI:

```yaml
# GitHub Actions example
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: alarm_test
  postgres:
    image: postgres:14
    env:
      POSTGRES_PASSWORD: postgres
      POSTGRES_DB: alarm_test
```

## Performance

- **Unit tests:** ~0.01s (fast, no I/O)
- **SQLite tests:** ~0.03s (in-memory)
- **MySQL/PostgreSQL tests:** ~0.1s per backend (network I/O)

Total with all backends: ~0.5s
