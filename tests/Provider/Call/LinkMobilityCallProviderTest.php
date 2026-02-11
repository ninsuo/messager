<?php

namespace App\Tests\Provider\Call;

use App\Provider\Call\LinkMobilityCallProvider;
use App\Service\LinkMobilityClient;
use PHPUnit\Framework\TestCase;

class LinkMobilityCallProviderTest extends TestCase
{
    public function testSendDelegatesToClientWithRepeatedContent(): void
    {
        $expected = implode('. Je répète. ', array_fill(0, 5, 'Voice message content'));

        $client = $this->createMock(LinkMobilityClient::class);
        $client->expects($this->once())
            ->method('send')
            ->with('+33612345678', $expected, 99)
            ->willReturn(['msg_id' => 'voice-456']);

        $provider = new LinkMobilityCallProvider($client, 99);

        $result = $provider->send('+33612345678', [], 'Voice message content');

        $this->assertSame('voice-456', $result);
    }

    public function testSendWithNullContentRepeatsEmptyString(): void
    {
        $expected = implode('. Je répète. ', array_fill(0, 5, ''));

        $client = $this->createMock(LinkMobilityClient::class);
        $client->expects($this->once())
            ->method('send')
            ->with('+33612345678', $expected, 99)
            ->willReturn(['msg_id' => 'voice-789']);

        $provider = new LinkMobilityCallProvider($client, 99);

        $result = $provider->send('+33612345678');

        $this->assertSame('voice-789', $result);
    }

    public function testSendReturnsNullWhenNoMsgId(): void
    {
        $client = $this->createMock(LinkMobilityClient::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturn([]);

        $provider = new LinkMobilityCallProvider($client, 99);

        $result = $provider->send('+33612345678', [], 'Content');

        $this->assertNull($result);
    }
}
