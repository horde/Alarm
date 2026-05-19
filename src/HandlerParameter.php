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

/**
 * Value object representing a handler parameter configuration.
 *
 * Replaces the legacy parameter array structure.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
class HandlerParameter
{
    /**
     * @param string $type The parameter type (e.g., 'text', 'sound', 'email')
     * @param string $description Human-readable parameter description
     * @param bool $required Whether this parameter is required
     */
    public function __construct(
        public readonly string $type,
        public readonly string $description,
        public readonly bool $required = false,
    ) {}

    /**
     * Creates a HandlerParameter from legacy array structure.
     *
     * @param array<string, mixed> $data Legacy parameter array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'text',
            description: $data['desc'] ?? '',
            required: $data['required'] ?? false,
        );
    }

    /**
     * Converts to legacy array structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'desc' => $this->description,
            'required' => $this->required,
        ];
    }
}
