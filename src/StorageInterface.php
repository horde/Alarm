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
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */

namespace Horde\Alarm;

use Horde\Alarm\AlarmConfig;
use Horde\Alarm\AlarmException;
use Horde_Date;

/**
 * Defines the contract for alarm storage backends.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
interface StorageInterface
{
    /**
     * Returns a list of alarms from the backend.
     *
     * @param string|null $user Return alarms for this user, all users if null, or global alarms if empty string
     * @param Horde_Date $time The time when the alarms should be active
     * @return array<AlarmConfig> List of alarms
     * @throws AlarmException
     */
    public function listAlarms(?string $user, Horde_Date $time): array;

    /**
     * Returns a list of all global alarms from the backend.
     *
     * @return array<AlarmConfig> List of global alarms
     * @throws AlarmException
     */
    public function globalAlarms(): array;

    /**
     * Returns an alarm from the backend.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @return AlarmConfig
     * @throws AlarmException
     */
    public function get(string $id, string $user): AlarmConfig;

    /**
     * Stores an alarm in the backend.
     *
     * The alarm will be added if it doesn't exist, and updated otherwise.
     *
     * @param AlarmConfig $alarm The alarm configuration
     * @param bool $keepSnooze Whether to keep the snooze value and notification status unchanged
     * @throws AlarmException
     */
    public function set(AlarmConfig $alarm, bool $keepSnooze = false): void;

    /**
     * Updates internal alarm properties.
     *
     * Updates properties not determined by the application setting the alarm.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @param array<string, mixed> $internal Internal state data
     * @throws AlarmException
     */
    public function updateInternal(string $id, string $user, array $internal): void;

    /**
     * Returns whether an alarm exists.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @param string|null $instanceId Optional instance identifier for recurring alarms
     * @return bool True if the alarm exists
     */
    public function exists(string $id, string $user, ?string $instanceId = null): bool;

    /**
     * Delays (snoozes) an alarm for a certain period.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @param Horde_Date $snoozeUntil The time to snooze until
     * @throws AlarmException
     */
    public function snooze(string $id, string $user, Horde_Date $snoozeUntil): void;

    /**
     * Returns whether an alarm is snoozed.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @param Horde_Date $time The time to check against
     * @return bool True if the alarm is snoozed
     * @throws AlarmException
     */
    public function isSnoozed(string $id, string $user, Horde_Date $time): bool;

    /**
     * Dismisses an alarm completely.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user
     * @throws AlarmException
     */
    public function dismiss(string $id, string $user): void;

    /**
     * Deletes an alarm from the backend.
     *
     * @param string $id The alarm's unique id
     * @param string $user The alarm's user (empty string to delete for all users)
     * @throws AlarmException
     */
    public function delete(string $id, string $user): void;

    /**
     * Garbage collects old alarms.
     *
     * @throws AlarmException
     */
    public function gc(): void;

    /**
     * Initializes the backend storage.
     *
     * Creates necessary tables, files, or other backend-specific setup.
     *
     * @throws AlarmException
     */
    public function initialize(): void;
}
