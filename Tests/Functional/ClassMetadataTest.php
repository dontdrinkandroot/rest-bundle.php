<?php

namespace Dontdrinkandroot\RestBundle\Tests\Functional;

use Dontdrinkandroot\RestBundle\Metadata\ClassMetadata;
use Dontdrinkandroot\RestBundle\Metadata\PropertyMetadata;
use Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity\SubResourceEntity;
use Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity\User;
use Metadata\MetadataFactory;

class ClassMetadataTest extends FunctionalTestCase
{
    public function testUser()
    {
        /** @var MetadataFactory $metadataFactory */
        $metadataFactory = self::bootKernel(['environment' => 'secured'])->getContainer()->get(
            'test.Dontdrinkandroot\RestBundle\Metadata\RestMetadataFactory'
        );
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $metadataFactory->getMetadataForClass(User::class);

        $methods = $classMetadata->getMethods();
        $this->assertCount(2, $methods);

        $method = $methods['GET'];
        $this->assertEquals('GET', $method->name);
        $this->assertNull($method->right);
        $this->assertEquals(['supervisor'], $method->defaultIncludes);

        $method = $methods['POST'];
        $this->assertEquals('POST', $method->name);
        $this->assertEquals(['ROLE_ADMIN'], $method->right->attributes);
    }

    public function testUserSerialization()
    {
        /** @var MetadataFactory $metadataFactory */
        $metadataFactory = self::bootKernel(['environment' => 'secured'])->getContainer()->get(
            'test.Dontdrinkandroot\RestBundle\Metadata\RestMetadataFactory'
        );
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $metadataFactory->getMetadataForClass(User::class);

        $serialized = $classMetadata->serialize();

        $unserializedClassMetadata = new ClassMetadata(User::class);
        $unserializedClassMetadata->unserialize($serialized);

        $this->assertEquals($classMetadata, $unserializedClassMetadata);
    }

    public function testSubResourceEntity()
    {
        /** @var MetadataFactory $metadataFactory */
        $metadataFactory = self::bootKernel(['environment' => 'secured'])->getContainer()->get(
            'test.Dontdrinkandroot\RestBundle\Metadata\RestMetadataFactory'
        );
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $metadataFactory->getMetadataForClass(SubResourceEntity::class);

        //defaultIncludes: ["creator"];

        /** @var PropertyMetadata $propertyMetadata */
        $propertyMetadata = $classMetadata->propertyMetadata['creator'];
        $this->assertTrue($propertyMetadata->isIncludable());
        $this->assertTrue($propertyMetadata->isPostable());
        $this->assertTrue($propertyMetadata->getPostable()->byReference);
        $this->assertTrue($propertyMetadata->isPuttable());
        $this->assertTrue($propertyMetadata->getPuttable()->byReference);
    }

    public function testSubResourceEntitySerialization()
    {
        /** @var MetadataFactory $metadataFactory */
        $metadataFactory = self::bootKernel(['environment' => 'secured'])->getContainer()->get(
            'test.Dontdrinkandroot\RestBundle\Metadata\RestMetadataFactory'
        );
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $metadataFactory->getMetadataForClass(SubResourceEntity::class);

        $serialized = $classMetadata->serialize();

        $unserializedClassMetadata = new ClassMetadata(SubResourceEntity::class);
        $unserializedClassMetadata->unserialize($serialized);

        $this->assertEquals($classMetadata, $unserializedClassMetadata);
    }
}
