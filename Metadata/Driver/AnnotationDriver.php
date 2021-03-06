<?php
namespace Dontdrinkandroot\RestBundle\Metadata\Driver;

use Doctrine\Common\Annotations\Reader;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\Excluded;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\Includable;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\Postable;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\Puttable;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\RootResource;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\SubResource;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\Virtual;
use Dontdrinkandroot\RestBundle\Metadata\ClassMetadata;
use Dontdrinkandroot\RestBundle\Metadata\PropertyMetadata;
use Metadata\Driver\DriverInterface;

/**
 * @author Philip Washington Sorst <philip@sorst.net>
 */
class AnnotationDriver implements DriverInterface
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var DriverInterface
     */
    private $doctrineDriver;

    public function __construct(Reader $reader, DriverInterface $doctrineDriver)
    {
        $this->reader = $reader;
        $this->doctrineDriver = $doctrineDriver;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass(\ReflectionClass $class)
    {
        /** @var ClassMetadata $ddrRestClassMetadata */
        $ddrRestClassMetadata = $this->doctrineDriver->loadMetadataForClass($class);
        if (null === $ddrRestClassMetadata) {
            $ddrRestClassMetadata = new ClassMetadata($class->getName());
        }

        /** @var RootResource $restResourceAnnotation */
        $restResourceAnnotation = $this->reader->getClassAnnotation($class, RootResource::class);
        if (null !== $restResourceAnnotation) {

            $ddrRestClassMetadata->setRestResource(true);

            if (null !== $restResourceAnnotation->namePrefix) {
                $ddrRestClassMetadata->setNamePrefix($restResourceAnnotation->namePrefix);
            }

            if (null !== $restResourceAnnotation->pathPrefix) {
                $ddrRestClassMetadata->setPathPrefix($restResourceAnnotation->pathPrefix);
            }

            if (null !== $restResourceAnnotation->controller) {
                $ddrRestClassMetadata->setController($restResourceAnnotation->controller);
            }

            $ddrRestClassMetadata->idField = $restResourceAnnotation->idField;

            if (null !== $restResourceAnnotation->methods) {
                $methods = [];
                $methodAnnotations = $restResourceAnnotation->methods;
                foreach ($methodAnnotations as $methodAnnotation) {
                    $methods[$methodAnnotation->name] = $methodAnnotation;
                }
                $ddrRestClassMetadata->setMethods($methods);
            }

            if (null !== $restResourceAnnotation->methods) {
                $ddrRestClassMetadata->setMethods($restResourceAnnotation->methods);
            }
        }

        foreach ($class->getProperties() as $reflectionProperty) {

            $propertyMetadata = $ddrRestClassMetadata->getPropertyMetadata($reflectionProperty->getName());
            if (null === $propertyMetadata) {
                $propertyMetadata = new PropertyMetadata($class->getName(), $reflectionProperty->getName());
            }

            /** @var Puttable $puttable */
            if (null !== $puttable = $this->reader->getPropertyAnnotation($reflectionProperty, Puttable::class)) {
                $propertyMetadata->setPuttable($puttable);
            }

            /** @var Postable $postable */
            if (null !== $postable = $this->reader->getPropertyAnnotation($reflectionProperty, Postable::class)) {
                $propertyMetadata->setPostable($postable);
            }

            /** @var Includable $includable */
            $includable = $this->reader->getPropertyAnnotation($reflectionProperty, Includable::class);
            if (null !== $includable) {
                $this->parseIncludable($propertyMetadata, $includable);
            }

            $excluded = $this->reader->getPropertyAnnotation($reflectionProperty, Excluded::class);
            if (null !== $excluded) {
                $propertyMetadata->setExcluded(true);
            }

            /** @var SubResource $subResourceAnnotation */
            $subResourceAnnotation = $this->reader->getPropertyAnnotation($reflectionProperty, SubResource::class);
            if (null !== $subResourceAnnotation) {

                $propertyMetadata->setSubResource(true);

                if (null !== $subResourceAnnotation->path) {
                    $propertyMetadata->setSubResourcePath($subResourceAnnotation->path);
                }

                if (null !== $subResourceAnnotation->methods) {
                    $methods = [];
                    $methodAnnotations = $subResourceAnnotation->methods;
                    foreach ($methodAnnotations as $methodAnnotation) {
                        $methods[$methodAnnotation->name] = $methodAnnotation;
                    }
                    $propertyMetadata->setMethods($methods);
                }
            }

            $ddrRestClassMetadata->addPropertyMetadata($propertyMetadata);
        }

        foreach ($class->getMethods() as $reflectionMethod) {
            /** @var Virtual $virtualAnnotation */
            $virtualAnnotation = $this->reader->getMethodAnnotation($reflectionMethod, Virtual::class);
            if (null !== $virtualAnnotation) {

                $name = $this->methodToPropertyName($reflectionMethod);

                $propertyMetadata = $ddrRestClassMetadata->getPropertyMetadata($name);
                if (null === $propertyMetadata) {
                    $propertyMetadata = new PropertyMetadata($class->getName(), $name);
                }
                $propertyMetadata->setVirtual(true);

                /** @var Includable|null $includableAnnotation */
                if (null !== $includable = $this->reader->getMethodAnnotation(
                        $reflectionMethod,
                        Includable::class
                    )
                ) {
                    $this->parseIncludable($propertyMetadata, $includable);
                }

                $ddrRestClassMetadata->addPropertyMetadata($propertyMetadata);
            }
        }

        return $ddrRestClassMetadata;
    }

    private function methodToPropertyName(\ReflectionMethod $reflectionMethod): string
    {
        $name = $reflectionMethod->getName();
        if (0 === strpos($name, 'get')) {
            return lcfirst(substr($name, 3));
        }

        if (0 === strpos($name, 'is')) {
            return lcfirst(substr($name, 2));
        }

        if (0 === strpos($name, 'has')) {
            return lcfirst(substr($name, 3));
        }

        return $name;
    }

    public function parseIncludable(PropertyMetadata $propertyMetadata, Includable $includableAnnotation): void
    {
        $paths = $includableAnnotation->paths;
        if (null === $paths) {
            $paths = [$propertyMetadata->name];
        }
        $propertyMetadata->setIncludable(true);
        $propertyMetadata->setIncludablePaths($paths);
    }
}
