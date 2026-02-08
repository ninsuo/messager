<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/*

Usage example:

$this->validate($customer, [
    new BlindUniqueEntity(
        [
            'organisationId',
            'externalId',
        ],
        [
            'deletedAt' => null,
            'toRemoveAt' => null,
        ],
        'external_id',
        groups: [CustomerValidationGroup::EXTERNAL_ID]
    ),
    new BlindUniqueEntity(
        [
            'organisationId',
            'email',
        ],
        [
            'deletedAt' => null,
            'toRemoveAt' => null,
        ],
        'email',
        groups: [CustomerValidationGroup::EMAIL]
    ),
]);

*/

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BlindUniqueEntity extends Constraint
{
    /**
     * @param array<string>       $fields
     * @param array<string,mixed> $conditions
     */
    public function __construct(
        public array $fields,
        public array $conditions = [],
        public string $errorPath = '',
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
