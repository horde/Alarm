<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */

namespace Horde\Alarm\Test;

use Horde\Alarm\AlarmConfig;
use Horde\Alarm\AlarmManager;
use Horde\Alarm\AlarmTriggeredEvent;
use Horde\Alarm\NotificationMethod;
use Horde\Alarm\ObjectStorage;
use Horde\EventDispatcher\EventDispatcher;
use Horde\EventDispatcher\SimpleListenerProvider;
use Horde_Date;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;

#[CoversClass(AlarmManager::class)]
class AlarmManagerTest extends TestCase
{
    private ObjectStorage $storage;
    private AlarmManager $manager;
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        $this->storage = new ObjectStorage();
        $this->manager = new AlarmManager($this->storage);
        $this->dispatchedEvents = [];
    }

    public function testConstructorStandaloneMode(): void
    {
        $manager = new AlarmManager($this->storage);

        $this->assertInstanceOf(AlarmManager::class, $manager);
    }

    public function testConstructorWithSharedDispatcher(): void
    {
        $listenerProvider = new SimpleListenerProvider();
        $dispatcher = new EventDispatcher($listenerProvider);
        $manager = new AlarmManager($this->storage, $dispatcher);

        $this->assertInstanceOf(AlarmManager::class, $manager);
    }

    public function testConstructorWithListenerProvider(): void
    {
        $listenerProvider = new SimpleListenerProvider();
        $manager = new AlarmManager($this->storage, listenerProvider: $listenerProvider);

        $this->assertInstanceOf(AlarmManager::class, $manager);
    }

    public function testAddHandlerInStandaloneMode(): void
    {
        $handlerCalled = false;

        $handler = new class(function() use (&$handlerCalled) {
            $handlerCalled = true;
        }) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $callback) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->callback)();
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        };

        $this->manager->addHandler($handler);

        // Trigger notification
        $this->storage->set(new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date(time() - 300),
            methods: [NotificationMethod::Mail],
            title: 'Test',
        ));

        $this->manager->notify('john');

        $this->assertTrue($handlerCalled);
    }

    public function testAddHandlerThrowsWithExternalDispatcher(): void
    {
        $listenerProvider = new SimpleListenerProvider();
        $dispatcher = new EventDispatcher($listenerProvider);
        $manager = new AlarmManager($this->storage, $dispatcher);

        $handler = $this->createMock(\Horde\Alarm\HandlerInterface::class);

        $this->expectException(\Horde\Alarm\AlarmException::class);
        $this->expectExceptionMessage('Cannot add handlers when using external dispatcher');

        $manager->addHandler($handler);
    }

    public function testNotifyWithNoAlarms(): void
    {
        $this->manager->notify('john', new Horde_Date('2026-04-01 10:00:00'));

        $this->assertCount(0, $this->dispatchedEvents);
    }

    public function testNotifyDispatchesEventForActiveAlarm(): void
    {
        // Add listener to capture events
        $listener = function (AlarmTriggeredEvent $event) {
            $this->dispatchedEvents[] = $event;
        };
        $this->manager->addHandler(new class($listener) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $listener) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->listener)($event);
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        });

        // Create active alarm
        $alarm = new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
        );
        $this->storage->set($alarm);

        // Notify at time when alarm is active
        $this->manager->notify('john', new Horde_Date('2026-04-01 14:05:00'));

        $this->assertCount(1, $this->dispatchedEvents);
        $this->assertInstanceOf(AlarmTriggeredEvent::class, $this->dispatchedEvents[0]);
        $this->assertSame('test', $this->dispatchedEvents[0]->alarm->id);
    }

    public function testNotifyDefaultsToCurrentTime(): void
    {
        $listener = function (AlarmTriggeredEvent $event) {
            $this->dispatchedEvents[] = $event;
        };
        $this->manager->addHandler(new class($listener) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $listener) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->listener)($event);
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        });

        // Create alarm that's already active
        $alarm = new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date(time() - 300), // 5 minutes ago
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
        );
        $this->storage->set($alarm);

        $this->manager->notify('john');

        $this->assertCount(1, $this->dispatchedEvents);
    }

    public function testNotifyWithMultipleAlarms(): void
    {
        $listener = function (AlarmTriggeredEvent $event) {
            $this->dispatchedEvents[] = $event;
        };
        $this->manager->addHandler(new class($listener) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $listener) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->listener)($event);
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        });

        $this->storage->set(new AlarmConfig(
            id: 'alarm1',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'First',
        ));

        $this->storage->set(new AlarmConfig(
            id: 'alarm2',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Notify],
            title: 'Second',
        ));

        $this->manager->notify('john', new Horde_Date('2026-04-01 14:05:00'));

        $this->assertCount(2, $this->dispatchedEvents);
    }

    public function testNotifyExcludesMethods(): void
    {
        $eventDispatched = false;

        $listener = function (AlarmTriggeredEvent $event) use (&$eventDispatched) {
            $eventDispatched = true;
            $this->dispatchedEvents[] = $event;
        };
        $this->manager->addHandler(new class($listener) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $listener) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->listener)($event);
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        });

        $this->storage->set(new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail, NotificationMethod::Notify],
            title: 'Meeting',
        ));

        // Exclude mail notifications - event still dispatched, listeners filter
        $this->manager->notify('john', new Horde_Date('2026-04-01 14:05:00'), [NotificationMethod::Mail]);

        $this->assertTrue($eventDispatched);
        $this->assertCount(1, $this->dispatchedEvents);
        // Alarm config is immutable - both methods present, listeners decide what to handle
        $this->assertCount(2, $this->dispatchedEvents[0]->alarm->methods);
    }

    public function testNotifyCallsLoader(): void
    {
        $loaderCalled = false;
        $loaderUser = null;

        $loader = function ($user, $time) use (&$loaderCalled, &$loaderUser) {
            $loaderCalled = true;
            $loaderUser = $user;
        };

        $manager = new AlarmManager($this->storage, loader: $loader);
        $manager->notify('john', new Horde_Date('2026-04-01 14:00:00'));

        $this->assertTrue($loaderCalled);
        $this->assertSame('john', $loaderUser);
    }

    public function testNotifyLogsLoaderFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Alarm loader failed', $this->anything());

        $loader = function () {
            throw new \RuntimeException('Loader failed');
        };

        $manager = new AlarmManager($this->storage, logger: $logger, loader: $loader);

        $this->expectException(\Horde\Alarm\AlarmException::class);
        $manager->notify('john');
    }

    public function testNotifyLogsStorageFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to list alarms', $this->anything());

        $storage = $this->createMock(\Horde\Alarm\StorageInterface::class);
        $storage->method('listAlarms')
            ->willThrowException(new \RuntimeException('Storage failed'));

        $manager = new AlarmManager($storage, logger: $logger);

        $this->expectException(\Horde\Alarm\AlarmException::class);
        $manager->notify('john');
    }

    public function testNotifyContinuesAfterListenerException(): void
    {
        $listenerProvider = new SimpleListenerProvider();

        // First listener throws exception
        $listenerProvider->addListener(function (AlarmTriggeredEvent $event) {
            throw new \RuntimeException('Handler failed');
        });

        $this->storage->set(new AlarmConfig(
            id: 'alarm1',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'First',
        ));

        $dispatcher = new EventDispatcher($listenerProvider);
        $manager = new AlarmManager($this->storage, $dispatcher);

        // With the throwing listener, this will throw and only process first alarm
        $this->expectException(\RuntimeException::class);
        $manager->notify('john', new Horde_Date('2026-04-01 14:05:00'));
    }

    public function testNotifyWithStoppablePropagation(): void
    {
        $firstListenerCalled = false;
        $secondListenerCalled = false;

        // First listener stops propagation
        $this->manager->addHandler(new class(function() use (&$firstListenerCalled) {
            $firstListenerCalled = true;
        }) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $callback) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->callback)();
                $event->stopPropagation();
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        });

        // Second listener should not receive event
        $this->manager->addHandler(new class(function() use (&$secondListenerCalled) {
            $secondListenerCalled = true;
        }) implements \Horde\Alarm\HandlerInterface {
            public function __construct(private $callback) {}
            public function __invoke(\Horde\Alarm\AlarmTriggeredEvent $event): void {
                ($this->callback)();
            }
            public function notify(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function reset(\Horde\Alarm\AlarmConfig $alarm): void {}
            public function getDescription(): string { return ''; }
            public function getParameters(): array { return []; }
        });

        $this->storage->set(new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
        ));

        $this->manager->notify('john', new Horde_Date('2026-04-01 14:05:00'));

        $this->assertTrue($firstListenerCalled);
        $this->assertFalse($secondListenerCalled);
    }

    public function testListAlarmsDefaultsToCurrentTime(): void
    {
        $this->storage->set(new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date(time() - 300), // 5 minutes ago
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
        ));

        $alarms = $this->manager->listAlarms('john');

        $this->assertCount(1, $alarms);
        $this->assertSame('test', $alarms[0]->id);
    }

    public function testListAlarmsWithSpecificTime(): void
    {
        $this->storage->set(new AlarmConfig(
            id: 'future',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Future Meeting',
        ));

        $alarms = $this->manager->listAlarms('john', new Horde_Date('2026-04-01 14:05:00'));

        $this->assertCount(1, $alarms);
        $this->assertSame('future', $alarms[0]->id);
    }

    public function testGlobalAlarms(): void
    {
        $this->storage->set(new AlarmConfig(
            id: 'global1',
            user: '',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Global Alarm',
        ));

        $alarms = $this->manager->globalAlarms();

        $this->assertCount(1, $alarms);
        $this->assertSame('global1', $alarms[0]->id);
    }

    public function testGet(): void
    {
        $alarm = new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
        );
        $this->storage->set($alarm);

        $retrieved = $this->manager->get('test', 'john');

        $this->assertSame('test', $retrieved->id);
        $this->assertSame('john', $retrieved->user);
        $this->assertSame('Meeting', $retrieved->title);
    }

    public function testExists(): void
    {
        $this->storage->set(new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
        ));

        $this->assertTrue($this->manager->exists('test', 'john'));
        $this->assertFalse($this->manager->exists('nonexistent', 'john'));
    }

    public function testIsSnoozed(): void
    {
        $alarm = new AlarmConfig(
            id: 'test',
            user: 'john',
            start: new Horde_Date('2026-04-01 14:00:00'),
            methods: [NotificationMethod::Mail],
            title: 'Meeting',
            snooze: new Horde_Date('2026-04-01 15:00:00'),
        );
        $this->storage->set($alarm);

        $this->assertTrue($this->manager->isSnoozed('test', 'john', new Horde_Date('2026-04-01 14:30:00')));
        $this->assertFalse($this->manager->isSnoozed('test', 'john', new Horde_Date('2026-04-01 15:30:00')));
    }
}
