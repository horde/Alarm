<?php

declare(strict_types=1);

/**
 * Copyright 2010-2026 The Horde Project (http://www.horde.org/)
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
 * Null alarm storage backend.
 *
 * This backend does not store any alarms and always returns empty results.
 * Useful for testing or disabling alarm functionality.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
class NullStorage implements StorageInterface
{
    public function listAlarms(?string $user, Horde_Date $time): array
    {
        return [];
    }

    public function globalAlarms(): array
    {
        return [];
    }

    public function get(string $id, string $user): AlarmConfig
    {
        throw new AlarmException('Alarm not found');
    }

    public function set(AlarmConfig $alarm, bool $keepSnooze = false): void
    {
        // No-op
    }

    public function updateInternal(string $id, string $user, array $internal): void
    {
        // No-op
    }

    public function exists(string $id, string $user, ?string $instanceId = null): bool
    {
        return false;
    }

    public function snooze(string $id, string $user, Horde_Date $snoozeUntil): void
    {
        // No-op
    }

    public function isSnoozed(string $id, string $user, Horde_Date $time): bool
    {
        return false;
    }

    public function dismiss(string $id, string $user): void
    {
        // No-op
    }

    public function delete(string $id, string $user): void
    {
        // No-op
    }

    public function gc(): void
    {
        // No-op
    }

    public function initialize(): void
    {
        // No-op
    }
}
