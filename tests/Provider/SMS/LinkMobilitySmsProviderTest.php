<?php

namespace App\Tests\Provider\SMS;

use App\Provider\SMS\LinkMobilitySmsProvider;
use App\Service\LinkMobilityClient;
use PHPUnit\Framework\TestCase;

class LinkMobilitySmsProviderTest extends TestCase
{
    public function testSendDelegatesToClientWithSmsServiceId(): void
    {
        $client = $this->createMock(LinkMobilityClient::class);
        $client->expects($this->once())
            ->method('send')
            ->with('+33612345678', 'Hello SMS', 42)
            ->willReturn(['msg_id' => 'sms-123']);

        $provider = new LinkMobilitySmsProvider($client, 42);

        $result = $provider->send('+33612345678', 'Hello SMS');

        $this->assertSame('sms-123', $result);
    }

    public function testSendReturnsNullWhenNoMsgId(): void
    {
        $client = $this->createMock(LinkMobilityClient::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturn([]);

        $provider = new LinkMobilitySmsProvider($client, 42);

        $result = $provider->send('+33612345678', 'Hello SMS');

        $this->assertNull($result);
    }
}
