<?php

namespace App\Event;

final class TwilioEvent
{
    public const MESSAGE_SENT = 'twilio.message_sent';
    public const MESSAGE_RECEIVED = 'twilio.message_received';
    public const MESSAGE_ERROR = 'twilio.message_error';

    public const CALL_ESTABLISHED = 'twilio.call_established';
    public const CALL_KEY_PRESSED = 'twilio.call_key_pressed';
    public const CALL_RECEIVED = 'twilio.call_received';
    public const CALL_ERROR = 'twilio.call_error';
    public const CALL_ANSWERING_MACHINE = 'twilio.call_answering_machine';

}
