<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Persistence;

use ReflectionException;

final class ProxyFactory
{
    public function __construct(
        private readonly EntityManager $em,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function getProxy(string $className, mixed $id): object
    {
        $metadata = $this->em->getClassMetadata($className);
        $refl = $metadata->reflClass;
        $em = $this->em;

        $proxy = $refl->newLazyGhost(static function (object $object) use ($em, $className, $id): void {
            $em->getUnitOfWork()->getEntityPersister($className)->loadInto($object, $id);
        });

        $idProp = $metadata->getReflProperty((string) $metadata->identifier);
        $idProp->setRawValueWithoutLazyInitialization($proxy, $id);
        $idProp->skipLazyInitialization($proxy);

        return $proxy;
    }

    public function isUninitialized(object $entity): bool
    {
        return new \ReflectionClass($entity)->isUninitializedLazyObject($entity);
    }

    public function initialize(object $entity): void
    {
        $refl = new \ReflectionClass($entity);
        if ($refl->isUninitializedLazyObject($entity)) {
            $refl->initializeLazyObject($entity);
        }
    }
}