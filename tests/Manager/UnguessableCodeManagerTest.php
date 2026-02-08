<?php

namespace App\Tests\Manager;

use App\Manager\UnguessableCodeManager;
use App\Repository\UnguessableCodeRepository;
use App\Tool\Hash;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UnguessableCodeManagerTest extends KernelTestCase
{
    private UnguessableCodeManager $manager;
    private UnguessableCodeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->manager = self::getContainer()->get(UnguessableCodeManager::class);
        $this->repository = self::getContainer()->get(UnguessableCodeRepository::class);
    }

    public function testGenerateReturnsSecret(): void
    {
        $secret = $this->manager->generate('test', ['hello' => 'world']);

        $this->assertSame(64, strlen($secret));
    }

    public function testGenerateAndGet(): void
    {
        $secret = $this->manager->generate('test', ['hello' => 'world']);

        $context = $this->manager->get('test', $secret);

        $this->assertSame(['hello' => 'world'], $context);
    }

    public function testGetWithInvalidPurpose(): void
    {
        $secret = $this->manager->generate('test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid purpose.');

        $this->manager->get('wrong', $secret);
    }

    public function testGetNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code not found.');

        $this->manager->get('test', 'nonexistent_secret_that_does_not_exist_at_all_in_db_anywhere_ever');
    }

    public function testGetWithExpiredCode(): void
    {
        $secret = $this->manager->generate('test');

        $entity = $this->repository->get(Hash::hash($secret));
        $this->assertNotNull($entity);
        $entity->setExpiresAt((new \DateTimeImmutable())->modify('-1 hour'));
        $this->repository->save($entity);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code has expired.');

        $this->manager->get('test', $secret);
    }

    public function testGetWithNoRemainingHits(): void
    {
        $secret = $this->manager->generate('test', maxHitCount: 1);

        $this->manager->get('test', $secret);

        $this->expectException(\RuntimeException::class);

        $this->manager->get('test', $secret);
    }

    public function testOnlyValidateDoesNotConsumeHit(): void
    {
        $secret = $this->manager->generate('test', maxHitCount: 1);

        $this->manager->get('test', $secret, onlyValidate: true);
        $this->manager->get('test', $secret, onlyValidate: true);

        $context = $this->manager->get('test', $secret);

        $this->assertSame([], $context);
    }

    public function testInvalidate(): void
    {
        $secret = $this->manager->generate('test');

        $this->manager->invalidate($secret);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code not found.');

        $this->manager->get('test', $secret);
    }

    public function testEntityStoredInDatabase(): void
    {
        $secret = $this->manager->generate('test', ['key' => 'value']);

        $entity = $this->repository->get(Hash::hash($secret));

        $this->assertNotNull($entity);
        $this->assertSame(Hash::hash($secret), $entity->getCode());
        $this->assertSame('test', $entity->getPurpose());
        $this->assertNotNull($entity->getExpiresAt());
        $this->assertFalse($entity->hasExpired());
        $this->assertNotNull($entity->getRemainingHits());
        $this->assertFalse($entity->hasNoRemainingHits());
    }
}
