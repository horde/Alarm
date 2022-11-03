<?php
namespace Horde\Alarm\Test\Helper;
use Horde_Notification_Handler;

class NotificationFactory
{
    private $storage;

    public function __construct($storage)
    {
        $this->storage = $storage;
    }

    public function create()
    {
        return new Horde_Notification_Handler($this->storage);
    }
}
