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
use Horde\Serialize\Serializer;

/**
 * Desktop notification handler for webkit browsers.
 *
 * Implements both HandlerInterface (for direct usage) and PSR-14 event listener
 * (for event-driven usage via AlarmManager).
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */
class DesktopHandler implements HandlerInterface
{
    /**
     * @param callable $jsNotifyCallback Callback to push JS to the client
     * @param string|null $iconUrl Optional URL of an icon to display
     * @param \Psr\Log\LoggerInterface|null $logger Optional PSR-3 logger for error logging
     */
    public function __construct(
        private readonly mixed $jsNotifyCallback,
        private readonly ?string $iconUrl = null,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        if (!is_callable($jsNotifyCallback)) {
            throw new AlarmException('js_notify parameter must be callable');
        }
    }

    /**
     * PSR-14 event listener invocation.
     *
     * Handles AlarmTriggeredEvent by checking if the alarm has Desktop method.
     * Catches and logs errors defensively to prevent one bad listener from
     * stopping event propagation to other listeners.
     */
    public function __invoke(AlarmTriggeredEvent $event): void
    {
        // Only handle if alarm has desktop notification method
        if (!in_array(NotificationMethod::Desktop, $event->alarm->methods, true)) {
            return;
        }

        try {
            $this->sendDesktopNotification($event->alarm);
        } catch (\Throwable $e) {
            $this->logger?->error('Desktop handler failed', [
                'alarm_id' => $event->alarm->id,
                'user' => $event->alarm->user,
                'error' => $e->getMessage(),
            ]);
            // Don't rethrow - let other listeners process
        }
    }

    /**
     * Send notification for an alarm.
     *
     * For BC and testing - proxies to __invoke().
     */
    public function notify(AlarmConfig $alarm): void
    {
        $event = new AlarmTriggeredEvent($alarm);
        $this->__invoke($event);
    }

    /**
     * Internal method that actually sends the desktop notification.
     */
    private function sendDesktopNotification(AlarmConfig $alarm): void
    {
        $icon = $this->iconUrl ?? '';
        $title = Serializer::serialize($alarm->title, Serializer::JSON);
        $text = $alarm->text !== null
            ? Serializer::serialize($alarm->text, Serializer::JSON)
            : "''";

        $js = sprintf(
            'if(window.webkitNotifications)(function(){function show(){switch(window.webkitNotifications.checkPermission()){case 0:var notify=window.webkitNotifications.createNotification("%s",%s,%s);notify.show();(function(){notify.cancel()}).delay(5);break;case 1:window.webkitNotifications.requestPermission(function(){});break}}show()})()',
            $icon,
            $title,
            $text
        );

        call_user_func($this->jsNotifyCallback, $js);
    }

    public function reset(AlarmConfig $alarm): void
    {
        // No internal state to reset
    }

    public function getDescription(): string
    {
        return \Horde_Alarm_Translation::t("Desktop notification (with certain browsers)");
    }

    public function getParameters(): array
    {
        return [
            'js_notify' => new HandlerParameter(
                type: 'callback',
                description: 'Callback function to push JavaScript notifications',
                required: true,
            ),
            'icon' => new HandlerParameter(
                type: 'text',
                description: 'URL of an icon to display in notifications',
                required: false,
            ),
        ];
    }
}
