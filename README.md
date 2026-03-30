# Horde Alarm

Modern alarm/notification system for PHP 8.1+.

## Usage

```php
use Horde\Alarm\AlarmConfig;
use Horde\Alarm\AlarmManager;
use Horde\Alarm\SqlStorage;
use Horde\Alarm\MailHandler;
use Horde\Alarm\NotificationMethod;

// Storage backend
$storage = new SqlStorage($db);
$storage->set(new AlarmConfig(
    id: 'meeting',
    user: 'john@example.com',
    start: new Horde_Date('2026-04-01 14:00'),
    methods: [NotificationMethod::Mail],
    title: 'Team Meeting'
));

// Alarm manager with handlers
$manager = new AlarmManager($storage);
$manager->addHandler(new MailHandler($identityFactory, $transport, $storage));
$manager->notify('john@example.com');
```

## Features

- **Type-safe:** Full PHP 8.1 strict types, enums, readonly properties
- **Modern:** PSR-4 namespace, constructor promotion, value objects
- **Flexible:** Multiple storage backends (memory, SQL) and notification methods
- **Tested:** 142 tests, 217 assertions, full PHPUnit 11+ compatibility

## Install

```bash
composer require horde/alarm
```

## Documentation

- `doc/ALERTS.md` - Alarm vs Notification vs Log subsystems
- `doc/TESTING.md` - Test organization and running tests
- `doc/INTEGRATIONTEST.md` - Running integration tests
- `doc/UPGRADING.md` - Migration guide from lib/ 2.x PSR-0 to src/ 3.0 PSR-4

## License

LGPL-2.1-only
