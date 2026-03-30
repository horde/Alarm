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

use Horde_Date;

/**
 * Value object representing an alarm configuration.
 *
 * Replaces the legacy array structure with a typed, immutable object.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
class AlarmConfig
{
    /**
     * @param string $id Unique alarm identifier
     * @param string $user The alarm's user (empty string for global alarms)
     * @param Horde_Date $start The alarm start date/time
     * @param Horde_Date|null $end Optional alarm end date/time
     * @param array<NotificationMethod> $methods Notification methods to use
     * @param array<string, mixed> $params Parameters for notification methods
     * @param string $title The alarm title
     * @param string|null $text Optional alarm description
     * @param Horde_Date|null $snooze Optional snooze time (next notification time)
     * @param array<string, mixed> $internal Internal state data
     * @param string|null $instanceId Instance identifier for recurring alarms
     */
    public function __construct(
        public readonly string $id,
        public readonly string $user,
        public readonly Horde_Date $start,
        public readonly ?Horde_Date $end = null,
        public readonly array $methods = [],
        public readonly array $params = [],
        public readonly string $title = '',
        public readonly ?string $text = null,
        public readonly ?Horde_Date $snooze = null,
        public readonly array $internal = [],
        public readonly ?string $instanceId = null,
    ) {
    }

    /**
     * Creates an AlarmConfig from a legacy array structure.
     *
     * @param array<string, mixed> $data Legacy alarm array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            user: $data['user'] ?? '',
            start: $data['start'] ?? new Horde_Date(time()),
            end: $data['end'] ?? null,
            methods: isset($data['methods']) ? self::parseMethodsArray($data['methods']) : [],
            params: $data['params'] ?? [],
            title: $data['title'] ?? '',
            text: $data['text'] ?? null,
            snooze: $data['snooze'] ?? null,
            internal: $data['internal'] ?? [],
            instanceId: $data['instanceid'] ?? null,
        );
    }

    /**
     * Converts alarm config to legacy array structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'user' => $this->user,
            'start' => $this->start,
            'methods' => array_map(fn(NotificationMethod $m) => $m->value, $this->methods),
            'params' => $this->params,
            'title' => $this->title,
            'internal' => $this->internal,
        ];

        if ($this->end !== null) {
            $data['end'] = $this->end;
        }
        if ($this->text !== null) {
            $data['text'] = $this->text;
        }
        if ($this->snooze !== null) {
            $data['snooze'] = $this->snooze;
        }
        if ($this->instanceId !== null) {
            $data['instanceid'] = $this->instanceId;
        }

        return $data;
    }

    /**
     * Creates a modified copy with updated properties.
     *
     * @param array<string, mixed> $changes Properties to change
     * @return self
     */
    public function with(array $changes): self
    {
        $current = $this->toArray();
        $merged = array_merge($current, $changes);
        return self::fromArray($merged);
    }

    /**
     * Parses methods array into NotificationMethod enums.
     *
     * @param array<string> $methods
     * @return array<NotificationMethod>
     */
    private static function parseMethodsArray(array $methods): array
    {
        return array_map(
            fn(string $method) => NotificationMethod::from($method),
            $methods
        );
    }
}
