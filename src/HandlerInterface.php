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

/**
 * Defines the contract for alarm notification handlers.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Alarm
 */
interface HandlerInterface
{
    /**
     * Notifies about an alarm.
     *
     * @param AlarmConfig $alarm The alarm to notify about
     * @throws Exception\AlarmException
     */
    public function notify(AlarmConfig $alarm): void;

    /**
     * Resets the internal status of the handler.
     *
     * Called when an alarm is updated without keepsnooze, so that
     * notifications are sent again.
     *
     * @param AlarmConfig $alarm The alarm being reset
     */
    public function reset(AlarmConfig $alarm): void;

    /**
     * Returns a human readable description of the handler.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Returns user-configurable parameters for the handler.
     *
     * @return array<string, HandlerParameter>
     */
    public function getParameters(): array;
}
