# Horde Subsystems: Alarm vs Notification vs Log

The Horde framework provides three distinct subsystems for different messaging and event needs.
Understanding when to use each is critical for correct implementation.

## Quick Reference

| Subsystem | Purpose | Storage | When To Use |
|-----------|---------|---------|-------------|
| **Alarm** | Time-based scheduling | Persistent (SQL/Object) | Scheduled reminders, future notifications |
| **Notification** | UI feedback messages | Temporary (session) | Status/error messages for current request |
| **Log** | Audit trail | Durable (files/syslog) | Debugging, monitoring, compliance |

## Decision Tree

```
Need persistent record for debugging/audit? Data is relevant for administrators and support? Use horde/log!
Need message displayed to user now? Use horde/notification!
Need reminder about specific future event? Message is targeted at users, guests or everybody? Use horde/alarm!
```

---

## Alarm (horde/alarm)

**Purpose:** Schedule notifications to fire at specific future times.

**Architecture:**
- Modern: PSR-4 namespace (`Horde\Alarm`), PHP 8.1+, strict types
- Legacy: PSR-0 (`Horde_Alarm_*`), PHP 7.4+
- Event-driven: PSR-14 dispatching (src/) or handler registry (lib/)
- Storage: Persistent SQL or in-memory object storage

**Core Concepts:**
- Time-based triggers (start/end/snooze)
- User-specific alarms
- Multiple notification methods (mail, desktop, notify)
- Optional recurring patterns

**When To Use:**
- Meeting reminders
- Task deadlines
- Calendar event notifications
- Any time-based "do X at future time Y"

**Example:**
```php
use Horde\Alarm\AlarmManager;
use Horde\Alarm\SqlStorage;
use Horde\Alarm\AlarmConfig;
use Horde\Alarm\NotificationMethod;

$storage = new SqlStorage($db);
$manager = new AlarmManager($storage);
$manager->addHandler(new MailHandler($identityFactory, $transport, $storage));

$storage->set(new AlarmConfig(
    id: 'meeting-reminder',
    user: 'john@example.com',
    start: new Horde_Date('2026-04-01 14:00:00'),
    methods: [NotificationMethod::Mail],
    title: 'Team Meeting in Conference Room A'
));

// Later, when alarm fires (cron job, worker process)
$manager->notify('john@example.com');
```

**Key Files:**
- `src/AlarmManager.php` - Orchestrates notifications via PSR-14 events
- `src/SqlStorage.php` - SQL-backed persistent alarm storage
- `src/MailHandler.php` - Email notification handler
- `lib/Alarm.php` - Legacy PSR-0 API

---

## Notification (horde/notification)

**Purpose:** Display transient messages to users in the current HTTP request/session.

**Architecture:**
- Legacy: PSR-0 (`Horde_Notification_*`), no PSR-4 version yet
- Subject-observer pattern with listeners
- Storage: Session-based message stack
- Lifecycle: Messages expire after display or session end

**Core Concepts:**
- Event types (status, error, warning, info)
- Session-scoped message queue
- Subject-observer with listeners that render messages
- Decorators for special handling (Log, Alarm integration)

**When To Use:**
- "File saved successfully" after form submission
- "Invalid password" error feedback
- "3 items deleted" confirmation
- Any immediate UI feedback in current request

**Example:**
```php
use Horde_Notification_Handler;

$notifier = new Horde_Notification_Handler();

// Push messages
$notifier->push('File saved successfully', 'horde.success');
$notifier->push('Invalid email format', 'horde.error');

// Display in UI (usually in template)
$notifier->notify(['listeners' => 'status']);
```

**Key Files:**
- `lib/Notification/Handler.php` - Message stack and dispatcher
- `lib/Notification/Listener/Status.php` - Default message renderer
- `lib/Notification/Event.php` - Message event objects
- `lib/Notification/Decorator/Alarm.php` - Integrates with Alarm subsystem

---

## Log (horde/log)

**Purpose:** Persistent structured logging for debugging, monitoring, and compliance.

**Architecture:**
- Dual API: PSR-3 modern (`Psr\Log\LoggerInterface`) + legacy (`Horde_Log_Logger`)
- Multiple handlers (file, syslog, systemd journal, null)
- Persistent storage with configurable retention
- Structured context data support

**Core Concepts:**
- PSR-3 log levels (debug, info, notice, warning, error, critical, alert, emergency)
- Handler-based output routing
- Structured context (arrays with additional data)
- Filters for conditional logging

**When To Use:**
- Debugging application flow
- Recording errors and exceptions
- Audit trails for compliance
- Performance monitoring
- Security event tracking

**Example:**
```php
use Psr\Log\LoggerInterface;

// PSR-3 modern API
$logger->info('User logged in', ['user' => 'john', 'ip' => '192.168.1.1']);
$logger->error('Payment failed', ['amount' => 99.99, 'error' => $exception]);

// Legacy API
use Horde_Log_Logger;
use Horde_Log_Handler_Stream;

$logger = new Horde_Log_Logger();
$logger->addHandler(new Horde_Log_Handler_Stream('/var/log/app.log'));
$logger->info('User logged in');
```

**Key Files:**
- PSR-3 integration via `Psr\Log\LoggerInterface`
- `lib/Log/Logger.php` - Legacy logger implementation
- `lib/Log/Handler/*` - Output handlers (stream, syslog, etc.)

---

## Integration Between Subsystems

### Alarm + Log

Alarm handlers use PSR-3 logging for error reporting:

```php
$manager = new AlarmManager($storage, logger: $psrLogger);
// Handler failures logged automatically
```

### Notification + Alarm

Notification can decorate with Alarm to display alarm-triggered messages:

```php
use Horde_Notification_Handler;
use Horde_Notification_Decorator_Alarm;

$notifier = new Horde_Notification_Handler(
    new Horde_Notification_Decorator_Alarm($alarm)
);
// Active alarms appear as notification messages
```

### Notification + Log

Notification can decorate with Log to record displayed messages:

```php
use Horde_Notification_Handler;
use Horde_Notification_Decorator_Log;

$notifier = new Horde_Notification_Handler(
    new Horde_Notification_Decorator_Log($logger)
);
// All notifications logged automatically
```

---

## Comparison Matrix

| Feature | Alarm | Notification | Log |
|---------|-------|--------------|-----|
| **Timing** | Future | Immediate | Any time |
| **Persistence** | Yes (database) | No (session) | Yes (files/syslog) |
| **User-facing** | Yes (via handlers) | Yes (UI messages) | No (developer/ops) |
| **Structured data** | AlarmConfig object | Event objects | PSR-3 context arrays |
| **Multiple recipients** | Per-user filtering | Current session only | N/A |
| **Requires action** | Trigger at scheduled time | Display in UI | Write to log |
| **PSR compliance** | PSR-14 events | None | PSR-3 logging |

---

## Common Patterns

### Pattern 1: User Action Feedback

**Bad:** Using Alarm for immediate feedback
```php
// ❌ Don't do this - Alarm is for future times
$alarm->set(['start' => now(), 'title' => 'File saved']);
```

**Good:** Using Notification for immediate feedback
```php
// ✅ Correct - Notification for current request
$notifier->push('File saved successfully', 'horde.success');
```

### Pattern 2: Scheduled Reminders

**Bad:** Using Notification for future events
```php
// ❌ Don't do this - Notification is session-based
$notifier->push('Meeting in 2 hours', 'horde.warning');
```

**Good:** Using Alarm for scheduled notifications
```php
// ✅ Correct - Alarm for future time
$storage->set(new AlarmConfig(
    id: 'meeting',
    user: 'john',
    start: new Horde_Date('+2 hours'),
    methods: [NotificationMethod::Mail],
    title: 'Meeting reminder'
));
```

### Pattern 3: Debugging Production Issues

**Bad:** Using Notification for diagnostic info
```php
// ❌ Don't do this - Notification is for user messages
$notifier->push('SQL query took 5.2s', 'horde.warning');
```

**Good:** Using Log for diagnostic info
```php
// ✅ Correct - Log for developer/ops data
$logger->warning('Slow query detected', [
    'duration' => 5.2,
    'query' => $sql,
    'user' => $user
]);
```

---

## Migration Notes

### From lib/ to src/ Alarm

See `doc/UPGRADING.md` for complete migration guide.

**Key changes:**
- Arrays → AlarmConfig value objects
- Strings → NotificationMethod enums
- Handler registry → PSR-14 event listeners
- Optional PSR-3 logging support

### Notification Modernization

**Status:** No PSR-4 version available yet (as of 2026-03-30)

Currently only PSR-0 API available. Continue using:
```php
use Horde_Notification_Handler;
```

### Log PSR-3 Migration

**Both APIs available:**
- PSR-3 modern: `Psr\Log\LoggerInterface`
- Legacy: `Horde_Log_Logger`

Prefer PSR-3 for new code. Legacy API remains for backward compatibility.

---

## Further Reading

- **Alarm:** `doc/UPGRADING.md` - PSR-0 to PSR-4 migration guide
- **Alarm:** `doc/INTEGRATIONTEST.md` - Integration testing setup
- **Notification:** `horde/Notification` repository documentation
- **Log:** PSR-3 specification at https://www.php-fig.org/psr/psr-3/
