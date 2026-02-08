<?php

namespace App\Doctrine\EventSubscriber;

use App\Attribute\BlindIndex;
use App\Attribute\Encrypted;
use App\Contract\EncryptedResourceInterface;
use App\Tool\Encryption;
use App\Tool\Reflection;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Partially copied from https://github.com/integr8rs/DoctrineEncryptBundle
 *
 * Transparently encrypts/decrypts entity properties marked with #[Encrypted]
 * and maintains blind indexes for properties marked with #[BlindIndex].
 *
 * The owning entity must implement EncryptedResourceInterface.
 */
#[AsDoctrineListener(Events::postUpdate)]
#[AsDoctrineListener(Events::preUpdate)]
#[AsDoctrineListener(Events::postLoad)]
#[AsDoctrineListener(Events::onFlush)]
#[AsDoctrineListener(Events::preFlush)]
#[AsDoctrineListener(Events::postFlush)]
#[AsDoctrineListener(Events::onClear)]
class EncryptedResourceSubscriber
{
    public const ENCRYPTION_MARKER = '<ENC>';

    private int $decryptCounter = 0;
    private int $encryptCounter = 0;

    /** @var array<string, array<int, array<string, array<string, string>>>> */
    private array $cachedDecryptions = [];

    /** @var array<string, array<string, \ReflectionProperty>> */
    private array $cachedClassProperties = [];

    /** @var array<string, bool> */
    private array $cachedClassPropertiesAreEmbedded = [];

    /** @var array<string, bool> */
    private array $cachedClassPropertiesAreEncrypted = [];

    /** @var array<string, ?BlindIndex> */
    private array $cachedClassPropertiesAreBlindIndexes = [];

    /** @var array<string, bool> */
    private array $cachedClassesContainAnEncryptProperty = [];

    public function __construct(
        private readonly Encryption $encryption,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof EncryptedResourceInterface) {
            $this->processFields($entity, $args->getObjectManager(), false);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof EncryptedResourceInterface) {
            $this->processFields($entity, $args->getObjectManager(), true);
        }
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof EncryptedResourceInterface) {
            $this->processFields($entity, $args->getObjectManager(), false);
        }
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $objectManager = $args->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entityName => $entityArray) {
            if (isset($this->cachedDecryptions[$entityName])) {
                foreach ($entityArray as $instance) {
                    $this->processFields($instance, $objectManager, true);
                }
            }
        }

        $this->cachedDecryptions = [];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $objectManager = $args->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof EncryptedResourceInterface) {
                $encryptCounterBefore = $this->encryptCounter;
                $this->processFields($entity, $objectManager, true);
                if ($this->encryptCounter > $encryptCounterBefore) {
                    $classMetadata = $objectManager->getClassMetadata($entity::class);
                    $unitOfWork->recomputeSingleEntityChangeSet($classMetadata, $entity);
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $objectManager = $args->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entityMap) {
            foreach ($entityMap as $entity) {
                if ($entity instanceof EncryptedResourceInterface) {
                    $this->processFields($entity, $objectManager, false);
                }
            }
        }
    }

    public function onClear(OnClearEventArgs $onClearEventArgs): void
    {
        $this->cachedDecryptions = [];
        $this->decryptCounter = 0;
        $this->encryptCounter = 0;
    }

    private function processFields(
        EncryptedResourceInterface $entity,
        EntityManagerInterface $entityManager,
        bool $isEncryptOperation,
    ): void {
        if (!$this->containsEncryptProperties($entity)) {
            return;
        }

        $realClass = $entity::class;
        $properties = $this->getClassProperties($realClass);

        foreach ($properties as $refProperty) {
            if ($isEncryptOperation && $attribute = $this->getBlindIndex($refProperty)) {
                $pac = PropertyAccess::createPropertyAccessor();
                $currentPropValue = $pac->getValue($entity, $attribute->getField());

                if (!$currentPropValue) {
                    $pac->setValue($entity, $refProperty->getName(), null);
                }

                if ($currentPropValue && !\str_ends_with((string) $currentPropValue, self::ENCRYPTION_MARKER)) {
                    $pac->setValue($entity, $refProperty->getName(), $this->encryption->blindHash($currentPropValue, $realClass));
                }
            }
        }

        foreach ($properties as $refProperty) {
            if ($this->isPropertyAnEmbeddedMapping($refProperty)) {
                $this->handleEmbeddedAnnotation($entity, $entityManager, $refProperty, $isEncryptOperation);
                continue;
            }

            if ($this->isPropertyEncrypted($refProperty)) {
                $rootEntityName = $entityManager->getClassMetadata($entity::class)->rootEntityName;

                $pac = PropertyAccess::createPropertyAccessor();
                $value = $pac->getValue($entity, $refProperty->getName());

                if (!$isEncryptOperation && $value && \str_ends_with((string) $value, self::ENCRYPTION_MARKER)) {
                    $this->decryptCounter++;
                    $currentPropValue = $this->encryption->decrypt(substr((string) $value, 0, -strlen(self::ENCRYPTION_MARKER)), $entity->getUuid());
                    $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                    $this->cachedDecryptions[$rootEntityName][\spl_object_id($entity)][$refProperty->getName()][$currentPropValue] = $value;
                }

                if ($isEncryptOperation && $value) {
                    if (isset($this->cachedDecryptions[$rootEntityName][\spl_object_id($entity)][$refProperty->getName()][$value])) {
                        $pac->setValue($entity, $refProperty->getName(), $this->cachedDecryptions[$rootEntityName][\spl_object_id($entity)][$refProperty->getName()][$value]);
                    } elseif (!\str_ends_with((string) $value, self::ENCRYPTION_MARKER)) {
                        $this->encryptCounter++;
                        $currentPropValue = $this->encryption->encrypt($value, $entity->getUuid()).self::ENCRYPTION_MARKER;
                        $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                    }
                }
            }
        }
    }

    private function handleEmbeddedAnnotation(
        object $entity,
        EntityManagerInterface $entityManager,
        \ReflectionProperty $embeddedProperty,
        bool $isEncryptOperation = true,
    ): void {
        $propName = $embeddedProperty->getName();
        $pac = PropertyAccess::createPropertyAccessor();
        $embeddedEntity = $pac->getValue($entity, $propName);

        if ($embeddedEntity instanceof EncryptedResourceInterface) {
            $this->processFields($embeddedEntity, $entityManager, $isEncryptOperation);
        }
    }

    /**
     * @return array<string, \ReflectionProperty>
     */
    private function getClassProperties(string $className): array
    {
        if (array_key_exists($className, $this->cachedClassProperties)) {
            return $this->cachedClassProperties[$className];
        }

        $this->cachedClassProperties[$className] = Reflection::getAllProperties($className);

        return $this->cachedClassProperties[$className];
    }

    private function isPropertyAnEmbeddedMapping(\ReflectionProperty $refProperty): bool
    {
        $key = $refProperty->getDeclaringClass()->getName().$refProperty->getName();

        if (!array_key_exists($key, $this->cachedClassPropertiesAreEmbedded)) {
            $this->cachedClassPropertiesAreEmbedded[$key] = (bool) $this->getAttribute($refProperty, Embedded::class);
        }

        return $this->cachedClassPropertiesAreEmbedded[$key];
    }

    private function isPropertyEncrypted(\ReflectionProperty $refProperty): bool
    {
        $key = $refProperty->getDeclaringClass()->getName().$refProperty->getName();

        if (!array_key_exists($key, $this->cachedClassPropertiesAreEncrypted)) {
            $this->cachedClassPropertiesAreEncrypted[$key] = (bool) $this->getAttribute($refProperty, Encrypted::class);
        }

        return $this->cachedClassPropertiesAreEncrypted[$key];
    }

    private function getBlindIndex(\ReflectionProperty $refProperty): ?BlindIndex
    {
        $key = $refProperty->getDeclaringClass()->getName().$refProperty->getName();

        if (!array_key_exists($key, $this->cachedClassPropertiesAreBlindIndexes)) {
            $this->cachedClassPropertiesAreBlindIndexes[$key] = $this->getAttribute($refProperty, BlindIndex::class);
        }

        return $this->cachedClassPropertiesAreBlindIndexes[$key];
    }

    private function containsEncryptProperties(object $entity): bool
    {
        $realClass = $entity::class;

        if (array_key_exists($realClass, $this->cachedClassesContainAnEncryptProperty)) {
            return $this->cachedClassesContainAnEncryptProperty[$realClass];
        }

        $this->cachedClassesContainAnEncryptProperty[$realClass] = false;

        $properties = $this->getClassProperties($realClass);

        foreach ($properties as $refProperty) {
            if ($this->isPropertyAnEmbeddedMapping($refProperty)) {
                $pac = PropertyAccess::createPropertyAccessor();
                $embeddedEntity = $pac->getValue($entity, $refProperty->getName());
                if ($embeddedEntity instanceof EncryptedResourceInterface
                    && $this->containsEncryptProperties($embeddedEntity)) {
                    $this->cachedClassesContainAnEncryptProperty[$realClass] = true;
                }
            } else {
                if ($this->isPropertyEncrypted($refProperty)) {
                    $this->cachedClassesContainAnEncryptProperty[$realClass] = true;
                }
            }
        }

        return $this->cachedClassesContainAnEncryptProperty[$realClass];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $name
     *
     * @return ?T
     */
    private function getAttribute(\ReflectionProperty $reflector, string $name): ?object
    {
        if ($attributes = $reflector->getAttributes($name, \ReflectionAttribute::IS_INSTANCEOF)) {
            return reset($attributes)->newInstance();
        }

        return null;
    }
}
