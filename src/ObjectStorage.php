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
 * In-memory alarm storage backend.
 *
 * Stores alarms in an object instance. Data is not persisted across requests.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
class ObjectStorage implements StorageInterface
{
    /**
     * @var array<AlarmConfig>
     */
    private array $alarms = [];

    public function listAlarms(?string $user, Horde_Date $time): array
    {
        $result = [];

        foreach ($this->alarms as $alarm) {
            // Skip dismissed alarms
            $dismissed = $alarm->internal['dismissed'] ?? false;
            if ($dismissed) {
                continue;
            }

            // Check snooze status
            $activationTime = $alarm->snooze ?? $alarm->start;
            if ($activationTime->compareDateTime($time) > 0) {
                continue;
            }

            // Check end time
            if ($alarm->end !== null && $alarm->end->compareDateTime($time) < 0) {
                continue;
            }

            // Check user filter
            if ($user !== null && $alarm->user !== $user) {
                continue;
            }

            $result[] = $alarm;
        }

        usort($result, $this->compareAlarms(...));
        return $result;
    }

    public function globalAlarms(): array
    {
        $result = [];

        foreach ($this->alarms as $alarm) {
            if ($alarm->user === '') {
                $result[] = $alarm;
            }
        }

        return $result;
    }

    public function get(string $id, string $user): AlarmConfig
    {
        $alarm = $this->findAlarm($id, $user);
        if ($alarm === null) {
            throw new AlarmException('Alarm not found');
        }
        return $alarm;
    }

    public function set(AlarmConfig $alarm, bool $keepSnooze = false): void
    {
        // If recurring alarm with new instanceId, delete the old entry
        if ($alarm->instanceId !== null
            && !$this->exists($alarm->id, $alarm->user, $alarm->instanceId)) {
            $this->delete($alarm->id, $alarm->user);
        }

        $existingIndex = $this->findAlarmIndex($alarm->id, $alarm->user);

        if ($existingIndex !== null) {
            // Update existing alarm
            $existing = $this->alarms[$existingIndex];

            if ($keepSnooze) {
                // Preserve snooze and internal state
                $alarm = new AlarmConfig(
                    id: $alarm->id,
                    user: $alarm->user,
                    start: $alarm->start,
                    end: $alarm->end,
                    methods: $alarm->methods,
                    params: $alarm->params,
                    title: $alarm->title,
                    text: $alarm->text,
                    snooze: $existing->snooze,
                    internal: $existing->internal,
                    instanceId: $alarm->instanceId,
                );
            } else {
                // Clear snooze
                $alarm = new AlarmConfig(
                    id: $alarm->id,
                    user: $alarm->user,
                    start: $alarm->start,
                    end: $alarm->end,
                    methods: $alarm->methods,
                    params: $alarm->params,
                    title: $alarm->title,
                    text: $alarm->text,
                    snooze: null,
                    internal: $alarm->internal,
                    instanceId: $alarm->instanceId,
                );
            }

            $this->alarms[$existingIndex] = $alarm;
        } else {
            // Add new alarm
            $this->alarms[] = $alarm;
        }
    }

    public function updateInternal(string $id, string $user, array $internal): void
    {
        $index = $this->findAlarmIndex($id, $user);
        if ($index === null) {
            throw new AlarmException('Alarm not found');
        }

        $alarm = $this->alarms[$index];
        $this->alarms[$index] = new AlarmConfig(
            id: $alarm->id,
            user: $alarm->user,
            start: $alarm->start,
            end: $alarm->end,
            methods: $alarm->methods,
            params: $alarm->params,
            title: $alarm->title,
            text: $alarm->text,
            snooze: $alarm->snooze,
            internal: $internal,
            instanceId: $alarm->instanceId,
        );
    }

    public function exists(string $id, string $user, ?string $instanceId = null): bool
    {
        return $this->findAlarm($id, $user, $instanceId) !== null;
    }

    public function snooze(string $id, string $user, Horde_Date $snoozeUntil): void
    {
        $index = $this->findAlarmIndex($id, $user);
        if ($index === null) {
            throw new AlarmException('Alarm not found');
        }

        $alarm = $this->alarms[$index];
        $this->alarms[$index] = new AlarmConfig(
            id: $alarm->id,
            user: $alarm->user,
            start: $alarm->start,
            end: $alarm->end,
            methods: $alarm->methods,
            params: $alarm->params,
            title: $alarm->title,
            text: $alarm->text,
            snooze: $snoozeUntil,
            internal: $alarm->internal,
            instanceId: $alarm->instanceId,
        );
    }

    public function isSnoozed(string $id, string $user, Horde_Date $time): bool
    {
        $alarm = $this->findAlarm($id, $user);
        if ($alarm === null) {
            return false;
        }

        $dismissed = $alarm->internal['dismissed'] ?? false;
        if ($dismissed) {
            return true;
        }

        if ($alarm->snooze !== null && $alarm->snooze->compareDateTime($time) >= 0) {
            return true;
        }

        return false;
    }

    public function dismiss(string $id, string $user): void
    {
        $index = $this->findAlarmIndex($id, $user);
        if ($index === null) {
            return;
        }

        $alarm = $this->alarms[$index];
        $internal = $alarm->internal;
        $internal['dismissed'] = true;

        $this->alarms[$index] = new AlarmConfig(
            id: $alarm->id,
            user: $alarm->user,
            start: $alarm->start,
            end: $alarm->end,
            methods: $alarm->methods,
            params: $alarm->params,
            title: $alarm->title,
            text: $alarm->text,
            snooze: $alarm->snooze,
            internal: $internal,
            instanceId: $alarm->instanceId,
        );
    }

    public function delete(string $id, string $user): void
    {
        $this->alarms = array_values(
            array_filter(
                $this->alarms,
                fn(AlarmConfig $alarm) => !($alarm->id === $id && $alarm->user === $user)
            )
        );
    }

    public function gc(): void
    {
        // No-op for in-memory storage
    }

    public function initialize(): void
    {
        // No-op for in-memory storage
    }

    /**
     * Finds an alarm by id, user, and optional instance id.
     */
    private function findAlarm(string $id, string $user, ?string $instanceId = null): ?AlarmConfig
    {
        foreach ($this->alarms as $alarm) {
            if ($alarm->id === $id && $alarm->user === $user) {
                if ($instanceId === null || $alarm->instanceId === $instanceId) {
                    return $alarm;
                }
            }
        }
        return null;
    }

    /**
     * Finds the array index of an alarm.
     */
    private function findAlarmIndex(string $id, string $user, ?string $instanceId = null): ?int
    {
        foreach ($this->alarms as $index => $alarm) {
            if ($alarm->id === $id && $alarm->user === $user) {
                if ($instanceId === null || $alarm->instanceId === $instanceId) {
                    return $index;
                }
            }
        }
        return null;
    }

    /**
     * Compares two alarms for chronological sorting.
     */
    private function compareAlarms(AlarmConfig $a, AlarmConfig $b): int
    {
        $cmp = $a->start->compareDateTime($b->start);
        if ($cmp !== 0) {
            return $cmp;
        }

        if ($a->end === null) {
            return -1;
        }
        if ($b->end === null) {
            return 1;
        }

        return $a->end->compareDateTime($b->end);
    }
}
