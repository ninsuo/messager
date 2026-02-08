<?php

namespace App\Tests\Repository;

use App\Entity\UnguessableCode;
use App\Repository\UnguessableCodeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class UnguessableCodeRepositoryTest extends KernelTestCase
{
    private UnguessableCodeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(UnguessableCodeRepository::class);
    }

    public function testSaveAndGet(): void
    {
        $code = new UnguessableCode();
        $code->setUuid(Uuid::v4()->toRfc4122());
        $code->setCode('testhash123');
        $code->setPurpose('test');
        $code->setContext(['foo' => 'bar']);
        $code->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        $code->setRemainingHits(3);

        $this->repository->save($code);

        $found = $this->repository->get('testhash123');

        $this->assertNotNull($found);
        $this->assertSame('testhash123', $found->getCode());
        $this->assertSame('test', $found->getPurpose());
        $this->assertSame(['foo' => 'bar'], $found->getContext());
        $this->assertSame(3, $found->getRemainingHits());
        $this->assertNotNull($found->getCreatedAt());
    }

    public function testRemove(): void
    {
        $code = new UnguessableCode();
        $code->setUuid(Uuid::v4()->toRfc4122());
        $code->setCode('to_remove');
        $code->setPurpose('test');
        $code->setContext([]);

        $this->repository->save($code);

        $found = $this->repository->get('to_remove');
        $this->assertNotNull($found);

        $this->repository->remove($found);

        $this->assertNull($this->repository->get('to_remove'));
    }

    public function testGetReturnsNullForUnknown(): void
    {
        $this->assertNull($this->repository->get('nonexistent'));
    }
}
