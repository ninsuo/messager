<?php

namespace App\Validator;

use App\Attribute\BlindIndex;
use App\Contract\EncryptedResourceInterface;
use App\Tool\Encryption;
use App\Tool\Reflection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class BlindUniqueEntityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly Encryption $encryption,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BlindUniqueEntity) {
            throw new UnexpectedTypeException($constraint, BlindUniqueEntity::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof EncryptedResourceInterface) {
            throw new UnexpectedTypeException($value, EncryptedResourceInterface::class);
        }

        $properties = Reflection::getAllProperties($value);

        $values = [];
        foreach ($constraint->fields as $field) {
            if (!array_key_exists($field, $properties)) {
                throw new \LogicException(sprintf('The field "%s" does not exist in the entity "%s".', $field, $value::class));
            }

            $values[$field] = $properties[$field]->getValue($value);
        }

        foreach ($properties as $field => $property) {
            $attributes = $property->getAttributes(BlindIndex::class);
            if (0 === count($attributes)) {
                continue;
            }

            // Passed constraint fields contain non-blind properties ([email])
            // We need to replace these field names by their associated blind names,
            // and replace their values by their blind values.
            //
            // $attribute->getField() => name of the field to blindify (email)
            // $field => name of the field containing blind value (blindEmail)

            /** @var BlindIndex $attribute */
            $attribute = reset($attributes)->newInstance();
            if (array_key_exists($attribute->getField(), $values)) {
                $values[$field] = $this->encryption->blindHash($values[$attribute->getField()], $value::class);
                unset($values[$attribute->getField()]);
            }
        }

        $entity = $this->doctrine
            ->getManagerForClass($value::class)
            ?->getRepository($value::class)
            ?->findOneBy(array_merge($values, $constraint->conditions));

        if (null !== $entity && $entity->getId() !== $value->getId()) {
            $this->context
                ->buildViolation('violations.common.already_used')
                ->atPath($constraint->errorPath)
                ->addViolation();
        }
    }
}
