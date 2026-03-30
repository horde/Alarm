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
use Horde_Notification_Handler;

/**
 * Inline notification handler using Horde_Notification system.
 *
 * Implements both HandlerInterface (for direct usage) and PSR-14 event listener
 * (for event-driven usage via AlarmManager).
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */
class NotifyHandler implements HandlerInterface
{
    /**
     * @var array<string, bool> Tracks which sounds have been played
     */
    private array $soundsPlayed = [];

    /**
     * @param object $notificationFactory Factory that implements create() and returns a Horde_Notification_Handler
     * @param \Psr\Log\LoggerInterface|null $logger Optional PSR-3 logger for error logging
     */
    public function __construct(
        private readonly object $notificationFactory,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        if (!method_exists($notificationFactory, 'create')) {
            throw new AlarmException('Notification factory must implement create() method');
        }
    }

    /**
     * PSR-14 event listener invocation.
     *
     * Handles AlarmTriggeredEvent by checking if the alarm has Notify method.
     * Catches and logs errors defensively to prevent one bad listener from
     * stopping event propagation to other listeners.
     */
    public function __invoke(AlarmTriggeredEvent $event): void
    {
        // Only handle if alarm has notify notification method
        if (!in_array(NotificationMethod::Notify, $event->alarm->methods, true)) {
            return;
        }

        try {
            $this->sendNotification($event->alarm);
        } catch (\Throwable $e) {
            $this->logger?->error('Notify handler failed', [
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
     * Internal method that actually sends the notification.
     */
    private function sendNotification(AlarmConfig $alarm): void
    {
        $notification = $this->notificationFactory->create();
        if (!($notification instanceof Horde_Notification_Handler)) {
            throw new AlarmException('Notification factory must return Horde_Notification_Handler instance');
        }

        $notification->push($alarm->title, 'horde.alarm', ['alarm' => $alarm->toArray()]);

        // Handle sound notification
        $sound = $alarm->params['notify']['sound'] ?? null;
        if ($sound !== null && !isset($this->soundsPlayed[$sound])) {
            $notification->attach('audio');
            $notification->push($sound, 'audio');
            $this->soundsPlayed[$sound] = true;
        }
    }

    public function reset(AlarmConfig $alarm): void
    {
        // No internal state to reset in storage
        // Sound tracking is per-request only
    }

    public function getDescription(): string
    {
        return \Horde_Alarm_Translation::t("Inline");
    }

    public function getParameters(): array
    {
        return [
            'sound' => new HandlerParameter(
                type: 'sound',
                description: \Horde_Alarm_Translation::t("Play a sound?"),
                required: false,
            ),
        ];
    }
}
