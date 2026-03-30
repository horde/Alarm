# Upgrading Horde Alarm

## Upgrading to 3.0 (PSR-4)

Horde Alarm 3.0 introduces a modern PSR-4 implementation alongside the legacy PSR-0 API.

### Two APIs Available

**Legacy (lib/)** - PSR-0, backward compatible
**Modern (src/)** - PSR-4, PHP 8.1+, strict types

Both work. Choose based on your needs.

## Quick Migration

### Before (lib/)
```php
use Horde_Alarm_Object;

$alarm = new Horde_Alarm_Object();
$alarm->set([
    'id' => 'meeting',
    'user' => 'john',
    'start' => new Horde_Date(time()),
    'methods' => ['mail'],
    'params' => [],
    'title' => 'Meeting',
]);
```

### After (src/)
```php
use Horde\Alarm\ObjectStorage;
use Horde\Alarm\AlarmConfig;
use Horde\Alarm\NotificationMethod;

$storage = new ObjectStorage();
$storage->set(new AlarmConfig(
    id: 'meeting',
    user: 'john',
    start: new Horde_Date(time()),
    methods: [NotificationMethod::Mail],
    title: 'Meeting',
));
```

## Key Changes

### 1. Namespace

```php
// Old
use Horde_Alarm_Sql;
use Horde_Alarm_Handler_Mail;

// New
use Horde\Alarm\SqlStorage;
use Horde\Alarm\MailHandler;
```

### 2. Arrays → Value Objects

```php
// Old: array with 12+ keys
$alarm = [
    'id' => 'x',
    'user' => 'john',
    'start' => $date,
    'end' => null,
    'methods' => ['mail'],
    'params' => [],
    'title' => 'Title',
    'text' => null,
    'snooze' => null,
    'internal' => [],
];

// New: typed object
$alarm = new AlarmConfig(
    id: 'x',
    user: 'john',
    start: $date,
    methods: [NotificationMethod::Mail],
    title: 'Title',
);
```

### 3. Strings → Enums

```php
// Old
$methods = ['notify', 'mail', 'desktop'];

// New
use Horde\Alarm\NotificationMethod;
$methods = [
    NotificationMethod::Notify,
    NotificationMethod::Mail,
    NotificationMethod::Desktop,
];
```

### 4. Constructor Arrays → Parameters

```php
// Old
$storage = new Horde_Alarm_Sql([
    'db' => $db,
    'table' => 'alarms',
]);

// New
$storage = new SqlStorage(
    db: $db,
    tableName: 'alarms',
);
```

### 5. Storage Classes Renamed

| Old | New |
|-----|-----|
| `Horde_Alarm_Null` | `NullStorage` |
| `Horde_Alarm_Object` | `ObjectStorage` |
| `Horde_Alarm_Sql` | `SqlStorage` |

### 6. Handler Classes Renamed

| Old | New |
|-----|-----|
| `Horde_Alarm_Handler_Notify` | `NotifyHandler` |
| `Horde_Alarm_Handler_Mail` | `MailHandler` |
| `Horde_Alarm_Handler_Desktop` | `DesktopHandler` |

### 7. Exception Class

```php
// Old
use Horde_Alarm_Exception;

// New
use Horde\Alarm\AlarmException;
```

### 8. Architecture: PSR-14 Event-Driven

**lib/ (PSR-0)** - Bundled orchestration with handler registry:
```php
$alarm = new Horde_Alarm_Sql(['db' => $db, 'logger' => $logger]);
$alarm->addHandler('mail', $mailHandler);
$alarm->addHandler('notify', $notifyHandler);
$alarm->notify($user);
```

**src/ (PSR-4)** - PSR-14 event dispatching with three usage patterns:

**Pattern 1: Standalone (simple, like lib/)**
```php
use Horde\Alarm\AlarmManager;
use Horde\Alarm\MailHandler;

$storage = new SqlStorage($db);
$manager = new AlarmManager($storage, logger: $logger);

// Register handlers with addHandler()
$manager->addHandler(new MailHandler($identityFactory, $transport, $storage, $logger));
$manager->addHandler(new NotifyHandler($notificationFactory, $logger));

$manager->notify($user);
```

**Pattern 2: Shared Dispatcher (framework integration)**
```php
use Horde\Alarm\AlarmManager;

// Shared dispatcher used by multiple components
$storage = new SqlStorage($db);
$manager = new AlarmManager($storage, $sharedDispatcher, $logger);

// Handlers registered elsewhere in shared listener provider
$sharedListenerProvider->addListener(new MailHandler(...));

$manager->notify($user);
```

**Pattern 3: Custom Listener Provider**
```php
use Horde\Alarm\AlarmManager;

$storage = new SqlStorage($db);
$customProvider = new MyListenerProvider();
$manager = new AlarmManager($storage, listenerProvider: $customProvider, logger: $logger);

// Register handlers with custom provider
$customProvider->addListener(new MailHandler(...));

$manager->notify($user);
```

**Key differences:**
- **lib/**: Custom handler registry, exceptions caught per-handler
- **src/**: PSR-14 event dispatching, handlers are defensive listeners
- **lib/**: `getErrors()` method collects failures (unused by any Horde app)
- **src/**: Errors logged via PSR-3, no collection

**Backward compatibility note:** lib/'s `getErrors()` method (added 2.1.0) is not in src/ - never used by any Horde application.

## PHP Requirements

- **lib/ (PSR-0):** PHP ^7.4 || ^8
- **src/ (PSR-4):** PHP ^8.1

## Dependencies

### Dependency Comparison: lib/ (PSR-0) vs src/ (PSR-4)

| Package | lib/ Uses | src/ Uses | Notes |
|---------|-----------|-----------|-------|
| **horde/date** | `Horde_Date` (PSR-0) | `Horde_Date` (PSR-0) | Both use PSR-0 - PSR-4 incomplete |
| **horde/exception** | `Horde_Exception_Wrapped` (PSR-0) | `Horde\Exception\Wrapped` (PSR-4) | src/ uses modern PSR-4 |
| **horde/translation** | `Horde_Translation` (PSR-0) | `Horde\Translation` (PSR-4) | src/ uses modern PSR-4 |
| **horde/serialize** | Not used | `Horde\Serialize\Serializer` (PSR-4) | src/ only (DesktopHandler) |
| **horde/db** | `Horde_Db_Adapter` (PSR-0) | `Horde\Db\Adapter` (PSR-4) | src/ uses modern PSR-4 |
| **horde/mail** | `Horde_Mail_Transport` (PSR-0) | `Horde_Mail_Transport` (PSR-0) | Both use PSR-0 - no PSR-4 yet |
| **horde/mime** | `Horde_Mime_Mail` (PSR-0) | `Horde_Mime_Mail` (PSR-0) | Both use PSR-0 - no PSR-4 yet |
| **horde/notification** | `Horde_Notification_Handler` (PSR-0) | `Horde_Notification_Handler` (PSR-0) | Both use PSR-0 - no PSR-4 yet |
| **horde/eventdispatcher** | Not used | PSR-14 dispatcher (PSR-4) | src/ only (AlarmManager) |
| **horde/log** | `Horde_Log_Logger` (PSR-0) | Not used | lib/ only - src/ uses PSR-3 |
| **psr/log** | Not used | PSR-3 `LoggerInterface` | src/ only (suggested) |

**Key differences:**
- **lib/**: Uses PSR-0 classes exclusively, includes optional `Horde_Log_Logger` support
- **src/**: Uses PSR-4 where available (`Horde\Exception`, `Horde\Translation`, `Horde\Db`, `Horde\Serialize`), falls back to PSR-0 for incomplete/unavailable packages

## Migration Strategies

### Strategy 1: Gradual (Recommended)

Keep using lib/ in production, adopt src/ for new code:

```php
// Existing code - unchanged
$oldAlarm = new Horde_Alarm_Object();

// New features - modern API
$newStorage = new Horde\Alarm\ObjectStorage();
```

Both APIs work side-by-side.

### Strategy 2: Hybrid

Use modern storage with legacy alarm format:

```php
$storage = new Horde\Alarm\SqlStorage($db);

// Convert between formats
$legacyArray = $modernAlarm->toArray();
$modernObject = AlarmConfig::fromArray($legacyArray);
```

### Strategy 3: Full Migration

Switch entire codebase to src/:

1. Update use statements
2. Replace array literals with `AlarmConfig` constructors
3. Replace string methods with `NotificationMethod` enums
4. Update exception catches

## Breaking Changes

### None for lib/

The lib/ API is **100% backward compatible**. All existing code continues to work.

### New API (src/) Differences

1. **Strict types** - wrong types throw TypeError
2. **Readonly properties** - can't modify AlarmConfig after creation (use `with()`)
3. **Required parameters** - some parameters must be provided explicitly
4. **No magic** - stricter, more predictable behavior

## Testing

Both APIs have full test coverage:

```bash
# Test legacy lib/
phpunit11 --testsuite=unit

# Test modern src/
phpunit11 --testsuite=unit-modern

# Test all
phpunit11
```

## When to Migrate

**Migrate when:**
- Starting a new project
- Doing a major refactor
- Adopting PHP 8.1+
- Want better IDE support

**Stay on lib/ when:**
- Legacy codebase on PHP 7.4
- No time for migration
- Risk-averse deployment

## Complete Example

### Full lib/ Code

```php
<?php
$db = new Horde_Db_Adapter_Pdo_Mysql($config);
$alarm = new Horde_Alarm_Sql(['db' => $db]);

$alarm->set([
    'id' => 'reminder-1',
    'user' => 'john@example.com',
    'start' => new Horde_Date('2026-04-01 14:00:00'),
    'end' => new Horde_Date('2026-04-01 15:00:00'),
    'methods' => ['mail', 'notify'],
    'params' => [
        'mail' => ['email' => 'john@example.com'],
        'notify' => ['sound' => 'beep.wav'],
    ],
    'title' => 'Team Meeting',
    'text' => 'Conference Room A',
]);

$active = $alarm->listAlarms('john@example.com', new Horde_Date(time()));
```

### Same in src/

```php
<?php
declare(strict_types=1);

use Horde\Alarm\AlarmManager;
use Horde\Alarm\SqlStorage;
use Horde\Alarm\AlarmConfig;
use Horde\Alarm\NotificationMethod;
use Horde\Alarm\MailHandler;
use Horde\Alarm\NotifyHandler;
use Horde\Db\Adapter\Pdo\Mysql;

$db = new Mysql($config);
$storage = new SqlStorage($db);

$storage->set(new AlarmConfig(
    id: 'reminder-1',
    user: 'john@example.com',
    start: new Horde_Date('2026-04-01 14:00:00'),
    end: new Horde_Date('2026-04-01 15:00:00'),
    methods: [
        NotificationMethod::Mail,
        NotificationMethod::Notify,
    ],
    params: [
        'mail' => ['email' => 'john@example.com'],
        'notify' => ['sound' => 'beep.wav'],
    ],
    title: 'Team Meeting',
    text: 'Conference Room A',
));

// Alarm manager with handlers (standalone pattern)
$manager = new AlarmManager($storage, logger: $logger);
$manager->addHandler(new MailHandler($identityFactory, $transport, $storage, $logger));
$manager->addHandler(new NotifyHandler($notificationFactory, $logger));

// Trigger notifications for active alarms
$manager->notify('john@example.com');
```

## Benefits of Migration

- **Type safety** - catch errors at dev time, not runtime
- **IDE support** - autocomplete, refactoring, type hints
- **Cleaner code** - less boilerplate, clearer intent
- **Modern features** - enums, readonly, named parameters
- **Better testing** - strict types expose edge cases

## Support

- **lib/ support:** Maintained indefinitely for backward compatibility
- **src/ development:** All new features go here

Both APIs will be supported long-term, but new development focuses on src/.

---

## Older Versions

### Upgrading to 2.2.9

**getErrors()**

The keys of the returned error list contain the alarm ID now, suffixed by a NUL character and some alarm method suffix.

### Upgrading to 2.2

**get(), set()**

Added the 'instanceid' member to the alarm hash for recurring alarms.

**exists()**

Added the $instanceid parameter.

### Upgrading to 2.1

**getErrors()**

This method has been added to retrieve notification errors.
