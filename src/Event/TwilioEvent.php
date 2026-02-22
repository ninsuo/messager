<?php

namespace App\Event;

final class TwilioEvent
{
    public const MESSAGE_SENT = 'twilio.message_sent';
    public const MESSAGE_RECEIVED = 'twilio.message_received';
    public const MESSAGE_ERROR = 'twilio.message_error';
}
