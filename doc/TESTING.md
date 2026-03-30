# Testing Horde Alarm

## Quick Start

```bash
# Default: all unit tests (no DB required, clean output)
phpunit

# Modern unit tests only (PSR-4 src/, cleanest)
phpunit --testsuite=unit-modern

# Legacy unit tests only (PSR-0 lib/, has 2 notices)
phpunit --testsuite=unit

# SQLite integration tests (in-memory DB, has DB deprecations)
phpunit --testsuite=integration-sqlite

# Legacy integration tests (MySQL, PostgreSQL, Oracle)
phpunit --testsuite=integration-legacy
```

## Understanding Test Output

**Default run (`phpunit`):**
```
Tests: 78, Assertions: 185, PHPUnit Notices: 2, Incomplete: 1
```

- **PHPUnit Notices (2):** Legacy tests use `#[CoversNothing]` for integration-style testing
- **Incomplete (1):** `NullStorageTest::testUpdate` intentionally incomplete (legacy artifact)

**These are expected and non-blocking.** Core unit tests are clean.

## Test Organization

### Unit Tests (No External Dependencies)

**Default suite** (`phpunit` with no args):
- Runs both `unit` and `unit-modern` suites
- No database connections
- No external services
- Fast execution (~0.03s)
- Clean output (no skipped tests)

**Test directories:**
- `test/unit/` - Legacy PSR-0 lib/ tests
- `test/unit-modern/` - Modern PSR-4 src/ tests

**What's tested:**
- AlarmConfig value object
- AlarmManager orchestration
- AlarmTriggeredEvent behavior
- NotificationMethod enum
- NullStorage and ObjectStorage (in-memory)

### Integration Tests (Database Required)

#### SQLite Integration (`--testsuite=integration-sqlite`)

**Requirements:** None (uses :memory: database)

**Test directory:** `test/integration-modern/`

**What's tested:**
- SqlStorage with SQLite in-memory DB
- Full CRUD operations
- Query behavior
- Schema migrations

**Known issues:**
- 6 deprecation warnings from horde/db (upstream issue)

#### Legacy Integration (`--testsuite=integration-legacy`)

**Requirements:** Database credentials in environment or config

**Test directory:** `test/integration/`

**What's tested:**
- PSR-0 lib/ Horde_Alarm_Sql with various adapters:
  - MySQL (mysql, mysqli, pdo_mysql)
  - PostgreSQL (pdo_pgsql)
  - Oracle (oci8)
  - SQLite (pdo_sqlite)

**Configuration:**

Environment variables:
```bash
export HORDE_TEST_MYSQL='{"host":"localhost","username":"test","password":"test","dbname":"horde_test"}'
export HORDE_TEST_PGSQL='{"host":"localhost","username":"test","password":"test","dbname":"horde_test"}'
```

Or config file at `test/integration/conf.php`:
```php
<?php
return [
    'mysql' => [
        'host' => 'localhost',
        'username' => 'test',
        'password' => 'test',
        'dbname' => 'horde_test',
    ],
];
```

**Behavior:**
- Tests automatically skip if database not available
- No errors, just marks tests as skipped

## Test Coverage

```bash
# 78 unit tests (25 legacy + 53 modern)
phpunit

# 11 SQLite integration tests
phpunit --testsuite=integration-sqlite

# 72 legacy integration tests (most skipped without DB config)
phpunit --testsuite=integration-legacy
```

## Continuous Integration

**Recommended CI setup:**

```yaml
# Always run unit tests
- name: Unit Tests
  run: phpunit

# Run SQLite integration (always available)
- name: SQLite Integration
  run: phpunit --testsuite=integration-sqlite

# Optional: MySQL/PostgreSQL integration with services
- name: MySQL Integration
  env:
    HORDE_TEST_MYSQL: '{"host":"127.0.0.1","username":"root","password":"","dbname":"test"}'
  run: phpunit --testsuite=integration-legacy
```

## Development Workflow

### Writing Tests

**For new src/ code:**
1. Add unit tests to `test/unit-modern/` (no DB)
2. Add integration tests to `test/integration-modern/` if DB needed

**For legacy lib/ code:**
1. Add unit tests to `test/unit/` (no DB)
2. Add integration tests to `test/integration/` if DB needed

### Running Tests Locally

```bash
# Fast feedback loop (default)
phpunit

# Before committing (include SQLite integration)
phpunit --testsuite=unit-all && phpunit --testsuite=integration-sqlite

# Full test run (requires DB setup)
phpunit --testsuite=unit-all
phpunit --testsuite=integration-sqlite
phpunit --testsuite=integration-legacy
```

## Troubleshooting

### All tests pass but shows "OK, but there were issues!"

**Cause:** PHPUnit notices or incomplete tests

**Common notices:**
- Test doesn't test anything (empty test method)
- Incomplete test marked with `$this->markTestIncomplete()`

**Solution:** These are informational, not failures.

### Integration tests all skip

**Cause:** Database not configured

**Solution:**
1. For SQLite: None needed, uses :memory:
2. For MySQL/PostgreSQL: Set environment variables or create `test/integration/conf.php`

### Deprecation warnings from horde/db

**Cause:** Upstream issue in horde/db with PHP 8.4 callable syntax

**Solution:** These don't affect functionality. Will be fixed in horde/db.

## Test Statistics

- **Total tests:** 161
- **Unit tests:** 78 (default)
- **Integration tests:** 83
  - SQLite: 11
  - Legacy: 72 (most require external DB)
- **Total assertions:** 249
