<?php

namespace App\Tests\Validator;

use App\Validator\BlindUniqueEntity;
use App\Validator\BlindUniqueEntityValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for BlindUniqueEntityValidator.
 *
 * These tests require an entity implementing EncryptedResourceInterface
 * with #[BlindIndex] properties. Uncomment and adapt once such an entity
 * exists in the project.
 *
 * Original test cases from the source project:
 *
 * - testValidNotExists: validate entity with unique blind value → no violations
 * - testValidIsMyself: validate existing entity against itself → no violations
 * - testInvalidIsTaken: validate entity with duplicate blind value → 1 violation
 * - testWithConditions: validate with extra conditions filtering out matches → no violations
 */
class BlindUniqueEntityValidatorTest extends KernelTestCase
{
    public function testValidatorIsRegistered(): void
    {
        self::bootKernel();

        $validator = self::getContainer()->get(BlindUniqueEntityValidator::class);
        $this->assertInstanceOf(BlindUniqueEntityValidator::class, $validator);
    }

    public function testConstraintTargetsClass(): void
    {
        $constraint = new BlindUniqueEntity(['email']);
        $this->assertEquals('class', $constraint->getTargets());
    }

    public function testConstraintFields(): void
    {
        $constraint = new BlindUniqueEntity(['email', 'organisationId'], ['deletedAt' => null], 'email');
        $this->assertEquals(['email', 'organisationId'], $constraint->fields);
        $this->assertEquals(['deletedAt' => null], $constraint->conditions);
        $this->assertEquals('email', $constraint->errorPath);
    }
}
