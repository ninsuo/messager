<?php

namespace App\Tool;

class Reflection
{
    /**
     * @return array<string, \ReflectionProperty>
     */
    public static function getAllProperties(string|object $className): array
    {
        if (\is_object($className)) {
            $className = $className::class;
        }

        $class = new \ReflectionClass($className);
        $properties = [];

        do {
            foreach ($class->getProperties() as $property) {
                if (!isset($properties[$property->getName()])) {
                    $properties[$property->getName()] = $property;
                }
            }
        } while ($class = $class->getParentClass());

        return $properties;
    }
}
