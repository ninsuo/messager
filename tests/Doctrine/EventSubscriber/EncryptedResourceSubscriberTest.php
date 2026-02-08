<?php

namespace App\Tests\Doctrine\EventSubscriber;

use App\Doctrine\EventSubscriber\EncryptedResourceSubscriber;
use App\Tool\Encryption;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for EncryptedResourceSubscriber.
 *
 * These tests require an entity implementing EncryptedResourceInterface
 * with #[Encrypted] and #[BlindIndex] properties. Uncomment and adapt
 * once such an entity exists in the project.
 *
 * Original test cases from the source project:
 *
 * - testEncryptCreate: create entity with cleartext → DB stores encrypted value
 * - testEncryptUpdate: update cleartext → DB stores new encrypted value
 * - testEncryptClear: update without flush + clear → DB keeps old encrypted value
 * - testBlindIndexCreate: create entity → findOneBy blind index works
 * - testBlindIndexUpdate: update value → findOneBy with new value works
 * - testBlindIndexNull: set value to null → blind index column is also null
 * - testBlindIndexClear: update without flush + clear → old blind index still works
 */
class EncryptedResourceSubscriberTest extends KernelTestCase
{
    public function testSubscriberIsRegistered(): void
    {
        self::bootKernel();

        $subscriber = self::getContainer()->get(EncryptedResourceSubscriber::class);
        $this->assertInstanceOf(EncryptedResourceSubscriber::class, $subscriber);
    }

    public function testEncryptionServiceIsInjected(): void
    {
        self::bootKernel();

        $encryption = self::getContainer()->get(Encryption::class);
        $this->assertInstanceOf(Encryption::class, $encryption);
    }
}
