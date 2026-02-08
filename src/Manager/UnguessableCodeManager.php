<?php

namespace App\Manager;

use App\Entity\UnguessableCode;
use App\Repository\UnguessableCodeRepository;
use App\Tool\Hash;
use App\Tool\Random;
use Symfony\Component\Uid\Uuid;

class UnguessableCodeManager
{
    public function __construct(
        private readonly UnguessableCodeRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function generate(
        string $purpose,
        array $context = [],
        ?int $expiresInSeconds = 86400,
        ?int $maxHitCount = 1,
    ): string {
        $secret = Random::hexadecimalBytes(UnguessableCode::SECRET_LENGTH);

        $entity = new UnguessableCode();
        $entity->setUuid(Uuid::v4()->toRfc4122());
        $entity->setCode(Hash::hash($secret));
        $entity->setPurpose($purpose);
        $entity->setContext($context);

        if (null !== $expiresInSeconds && $expiresInSeconds > 0) {
            $entity->setExpiresAt(
                (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresInSeconds)),
            );
        }

        if (null !== $maxHitCount && $maxHitCount > 0) {
            $entity->setRemainingHits($maxHitCount);
        }

        $this->repository->save($entity);

        return $secret;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $purpose, string $secret, bool $onlyValidate = false): array
    {
        $entity = $this->repository->get(Hash::hash($secret));

        if (null === $entity) {
            throw new \RuntimeException('Code not found.');
        }

        if ($this->hasExpired($entity)) {
            throw new \RuntimeException('Code has expired.');
        }

        if ($purpose !== $entity->getPurpose()) {
            throw new \RuntimeException('Invalid purpose.');
        }

        if ($this->hasNoRemainingHits($entity, $onlyValidate)) {
            throw new \RuntimeException('No remaining hits.');
        }

        return $entity->getContext();
    }

    public function invalidate(string $secret): void
    {
        $entity = $this->repository->get(Hash::hash($secret));

        if ($entity) {
            $this->repository->remove($entity);
        }
    }

    private function hasExpired(UnguessableCode $entity): bool
    {
        if ($entity->hasExpired()) {
            $this->repository->remove($entity);

            return true;
        }

        return false;
    }

    private function hasNoRemainingHits(UnguessableCode $entity, bool $onlyValidate): bool
    {
        if (null === $entity->getRemainingHits()) {
            return false;
        }

        if ($entity->hasNoRemainingHits()) {
            $this->repository->remove($entity);

            return true;
        }

        if (!$onlyValidate) {
            $entity->setRemainingHits($entity->getRemainingHits() - 1);
            $this->repository->save($entity);
        }

        return false;
    }
}
