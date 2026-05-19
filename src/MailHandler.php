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
use Horde\Alarm\StorageInterface;
use Horde_Mime_Mail;
use Horde_Mail_Transport;
use Horde_Mime_Exception;
use Horde_Alarm_Translation;
use Throwable;

/**
 * Email notification handler.
 *
 * Implements both HandlerInterface (for direct usage) and PSR-14 event listener
 * (for event-driven usage via AlarmManager).
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL-2.1-only
 * @package    Alarm
 */
class MailHandler implements HandlerInterface
{
    /**
     * @param object $identityFactory Factory that implements create() to get user identity
     * @param Horde_Mail_Transport $transport Mail transport instance
     * @param StorageInterface $storage Storage backend for updating internal state
     * @param \Psr\Log\LoggerInterface|null $logger Optional PSR-3 logger for error logging
     */
    public function __construct(
        private readonly object $identityFactory,
        private readonly Horde_Mail_Transport $transport,
        private readonly StorageInterface $storage,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        if (!method_exists($identityFactory, 'create')) {
            throw new AlarmException('Identity factory must implement create() method');
        }
    }

    /**
     * PSR-14 event listener invocation.
     *
     * Handles AlarmTriggeredEvent by checking if the alarm has Mail method.
     * Catches and logs errors defensively to prevent one bad listener from
     * stopping event propagation to other listeners.
     */
    public function __invoke(AlarmTriggeredEvent $event): void
    {
        // Only handle if alarm has mail notification method
        if (!in_array(NotificationMethod::Mail, $event->alarm->methods, true)) {
            return;
        }

        try {
            $this->sendMail($event->alarm);
        } catch (Throwable $e) {
            $this->logger?->error('Mail handler failed', [
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
     * Internal method that actually sends the email.
     */
    private function sendMail(AlarmConfig $alarm): void
    {
        // Check if already sent
        if (!empty($alarm->internal['mail']['sent'])) {
            return;
        }

        // Determine recipient email
        $email = $alarm->params['mail']['email'] ?? null;
        if ($email === null) {
            if ($alarm->user === '') {
                return;
            }
            $identity = $this->identityFactory->create($alarm->user);
            $email = $identity->getDefaultFromAddress(true);
        }

        try {
            $mail = new Horde_Mime_Mail([
                'Subject' => $alarm->title,
                'To' => $email,
                'From' => $email,
                'Auto-Submitted' => 'auto-generated',
                'X-Horde-Alarm' => $alarm->title,
            ]);

            if (isset($alarm->params['mail']['mimepart'])) {
                $mail->setBasePart($alarm->params['mail']['mimepart']);
            } elseif (empty($alarm->params['mail']['body'])) {
                $mail->setBody($alarm->text ?? '');
            } else {
                $mail->setBody($alarm->params['mail']['body']);
            }

            $mail->send($this->transport);
        } catch (Horde_Mime_Exception $e) {
            throw new AlarmException('Failed to send alarm email', previous: $e);
        }

        // Mark as sent
        $internal = $alarm->internal;
        $internal['mail']['sent'] = true;
        $this->storage->updateInternal($alarm->id, $alarm->user, $internal);
    }

    public function reset(AlarmConfig $alarm): void
    {
        $internal = $alarm->internal;
        $internal['mail']['sent'] = false;
        $this->storage->updateInternal($alarm->id, $alarm->user, $internal);
    }

    public function getDescription(): string
    {
        return Horde_Alarm_Translation::t("Email");
    }

    public function getParameters(): array
    {
        return [
            'email' => new HandlerParameter(
                type: 'text',
                description: Horde_Alarm_Translation::t("Email address (optional)"),
                required: false,
            ),
        ];
    }
}
