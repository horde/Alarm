<?php

declare(strict_types=1);

/**
 * Copyright 2007-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */

namespace Horde\Alarm;

use Horde_Date;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Horde\EventDispatcher\EventDispatcher;
use Horde\EventDispatcher\SimpleListenerProvider;

/**
 * Alarm manager that orchestrates alarm notifications using PSR-14 event dispatching.
 *
 * This class provides the orchestration layer for alarm notifications. It fetches
 * active alarms from storage and dispatches events to registered listeners (handlers).
 *
 * Supports three usage patterns:
 * 1. Standalone with internal listener provider (use addHandler())
 * 2. Shared dispatcher for framework integration (handlers registered externally)
 * 3. Custom listener provider (handlers registered in provided provider)
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */
class AlarmManager
{
    private readonly EventDispatcherInterface $dispatcher;
    private readonly mixed $loader;
    private readonly ?ListenerProviderInterface $internalListenerProvider;

    /**
     * @param StorageInterface $storage Storage backend for alarms
     * @param EventDispatcherInterface|null $dispatcher Optional shared PSR-14 event dispatcher (for framework integration)
     * @param ListenerProviderInterface|null $listenerProvider Optional listener provider (creates internal dispatcher if provided)
     * @param LoggerInterface $logger PSR-3 logger (defaults to NullLogger)
     * @param callable|null $loader Optional callback to preload alarms from applications
     */
    public function __construct(
        private readonly StorageInterface $storage,
        ?EventDispatcherInterface $dispatcher = null,
        ?ListenerProviderInterface $listenerProvider = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?callable $loader = null,
    ) {
        $this->loader = $loader;

        // Determine dispatcher setup
        if ($dispatcher !== null) {
            // Pattern 2: Use provided shared dispatcher
            $this->dispatcher = $dispatcher;
            $this->internalListenerProvider = null;
        } elseif ($listenerProvider !== null) {
            // Pattern 3: Create dispatcher from provided listener provider
            $this->dispatcher = new EventDispatcher($listenerProvider, $this->logger);
            $this->internalListenerProvider = null;
        } else {
            // Pattern 1: Create internal listener provider and dispatcher
            $this->internalListenerProvider = new SimpleListenerProvider();
            $this->dispatcher = new EventDispatcher($this->internalListenerProvider, $this->logger);
        }
    }

    /**
     * Register a handler as an event listener.
     *
     * Only works when using internal listener provider (no dispatcher/listenerProvider
     * passed to constructor). For external dispatchers, register handlers directly
     * with the listener provider.
     *
     * @param HandlerInterface $handler Handler to register as event listener
     * @throws AlarmException If using external dispatcher (can't add handlers)
     */
    public function addHandler(HandlerInterface $handler): void
    {
        if ($this->internalListenerProvider === null) {
            throw new AlarmException('Cannot add handlers when using external dispatcher or listener provider. Register handlers directly with the listener provider.');
        }

        $this->internalListenerProvider->addListener($handler);
    }

    /**
     * Notifies about active alarms by dispatching events.
     *
     * @param string|null $user Notify this user, all users if null, or guest users if empty string
     * @param Horde_Date|null $time The time to check for active alarms (defaults to now)
     * @param array<NotificationMethod> $exclude Don't dispatch events for these notification methods
     * @throws AlarmException If fetching alarms from storage fails
     */
    public function notify(?string $user = null, ?Horde_Date $time = null, array $exclude = []): void
    {
        // Call loader callback if provided
        if ($this->loader !== null) {
            try {
                ($this->loader)($user, $time);
            } catch (\Throwable $e) {
                $this->logger->error('Alarm loader failed', [
                    'user' => $user,
                    'error' => $e->getMessage(),
                ]);
                throw new AlarmException('Failed to load alarms', 0, $e);
            }
        }

        // Default to current time
        if ($time === null) {
            $time = new Horde_Date(time());
        }

        // Fetch active alarms
        try {
            $alarms = $this->storage->listAlarms($user, $time);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to list alarms', [
                'user' => $user,
                'time' => $time->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ]);
            throw new AlarmException('Failed to list alarms', 0, $e);
        }

        // Dispatch events for each alarm
        foreach ($alarms as $alarm) {
            // Skip if all methods are excluded
            $hasNonExcludedMethod = false;
            foreach ($alarm->methods as $method) {
                if (!in_array($method, $exclude, true)) {
                    $hasNonExcludedMethod = true;
                    break;
                }
            }

            if (!$hasNonExcludedMethod) {
                continue;
            }

            // Dispatch one event per alarm
            // Listeners check which methods they handle and whether excluded
            $event = new AlarmTriggeredEvent($alarm);
            $this->dispatcher->dispatch($event);
        }
    }

    /**
     * Returns a list of alarms from the backend.
     *
     * Convenience facade for storage->listAlarms(). Useful for administrative
     * interfaces, querying, and audit purposes independent of notification.
     *
     * @param string|null $user Return alarms for this user, all users if null, or global alarms if empty string
     * @param Horde_Date|null $time The time when the alarms should be active (defaults to now)
     * @return array<AlarmConfig> List of alarms
     * @throws AlarmException
     */
    public function listAlarms(?string $user = null, ?Horde_Date $time = null): array
    {
        if ($time === null) {
            $time = new Horde_Date(time());
        }
        return $this->storage->listAlarms($user, $time);
    }

    /**
     * Returns a list of all global alarms from the backend.
     *
     * Convenience facade for storage->globalAlarms(). Used by administrative
     * interfaces to display system-wide alarms.
     *
     * @return array<AlarmConfig> List of global alarms
     * @throws AlarmException
     */
    public function globalAlarms(): array
    {
        return $this->storage->globalAlarms();
    }

    /**
     * Returns an alarm from the backend.
     *
     * Convenience facade for storage->get(). Used for retrieving alarm details
     * for editing, export, or status checks.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @return AlarmConfig
     * @throws AlarmException
     */
    public function get(string $id, string $user): AlarmConfig
    {
        return $this->storage->get($id, $user);
    }

    /**
     * Returns whether an alarm exists.
     *
     * Convenience facade for storage->exists(). Used to check alarm state
     * before performing operations.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @param string|null $instanceId Optional instance identifier for recurring alarms
     * @return bool True if the alarm exists
     */
    public function exists(string $id, string $user, ?string $instanceId = null): bool
    {
        return $this->storage->exists($id, $user, $instanceId);
    }

    /**
     * Returns whether an alarm is snoozed.
     *
     * Convenience facade for storage->isSnoozed(). Used to check snooze state
     * for UI display or export purposes.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @param Horde_Date $time The time to check against
     * @return bool True if the alarm is snoozed
     * @throws AlarmException
     */
    public function isSnoozed(string $id, string $user, Horde_Date $time): bool
    {
        return $this->storage->isSnoozed($id, $user, $time);
    }
}
