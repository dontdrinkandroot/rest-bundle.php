<?php

namespace Dontdrinkandroot\RestBundle\Tests\Functional;

use Dontdrinkandroot\RestBundle\Security\AbstractAccessTokenAuthenticator;
use Dontdrinkandroot\RestBundle\Tests\Functional\DataFixtures\InheritedEntities;
use Dontdrinkandroot\RestBundle\Tests\Functional\DataFixtures\SecuredEntities;
use Dontdrinkandroot\RestBundle\Tests\Functional\DataFixtures\Users;
use Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity\AccessToken;
use Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity\InheritedEntity;
use Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity\SecuredEntity;
use Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity\SubResourceEntity;
use Symfony\Component\HttpFoundation\Response;

class SecuredEnvironmentTest extends FunctionalTestCase
{
    protected $environment = 'secured';

    public function testListUnauthorized()
    {
        $client = $this->makeClient();

        $response = $this->performGet($client, '/rest/secured');
        $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testList()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        $response = $this->performGet(
            $client,
            '/rest/secured',
            [],
            []
        );
        $content = $this->assertJsonResponse($response);

        $this->assertCount(2, $content);
    }

    public function testPostUnauthorized()
    {
        $client = $this->makeClient();
        $response = $this->performPost($client, '/rest/secured');
        $content = $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testPost()
    {
        $referenceRepository = $this->loadFixtures([Users::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        $response = $this->performPost(
            $client,
            '/rest/secured',
            [],
            [],
            [
                'integerField' => 23,
            ]
        );
        $content = $this->assertJsonResponse($response, Response::HTTP_CREATED);
        $this->assertHasKeyAndUnset('id', $content, true);
        $this->assertHasKeyAndUnset('uuid', $content, true);
        $this->assertContentEquals(
            [
                'dateField'      => null,
                'dateTimeField'  => null,
                'embeddedEntity' => [
                    'fieldString'  => null,
                    'fieldInteger' => null
                ],
                'integerField'   => 23,
                'timeField'      => null,
            ],
            $content,
            false
        );
    }

    public function testPostInvalid()
    {
        $referenceRepository = $this->loadFixtures([Users::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );
        $response = $this->performPost(
            $client,
            '/rest/secured',
            [],
            [],
            ['integerField' => 'thisisnointeger']
        );
        $content = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST, true);
        $this->assertContentEquals(
            [
                [
                    'propertyPath' => "integerField",
                    'message'      => "This value should be of type integer.",
                    'value'        => "thisisnointeger"
                ]
            ],
            $content,
            false
        );
    }

    public function testGetUnauthorized()
    {
        $referenceRepository = $this->loadFixtures([SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient();

        $entity = $referenceRepository->getReference('secured-entity-0');

        $response = $this->performGet(
            $client,
            sprintf('/rest/secured/%s', $entity->getId())
        );
        $content = $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testGet()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-0');

        $response = $this->performGet(
            $client,
            sprintf('/rest/secured/%s', $entity->getId()),
            [],
            []
        );
        $content = $this->assertJsonResponse($response);

        $expectedContent = [
            'id'             => $entity->getId(),
            'uuid'           => $entity->getUuid(),
            'dateTimeField'  => '2015-03-04 13:12:11',
            'dateField'      => '2016-01-02',
            'timeField'      => '03:13:37',
            'integerField'   => null,
            'embeddedEntity' => [
                'fieldString'  => null,
                'fieldInteger' => null
            ]
        ];

        $this->assertContentEquals($expectedContent, $content, false);
    }

    public function testDeleteUnauthorized()
    {
        $referenceRepository = $this->loadFixtures([SecuredEntities::class])->getReferenceRepository();
        $entity = $referenceRepository->getReference('secured-entity-0');
        $client = $this->makeClient();
        $response = $this->performDelete($client, sprintf('/rest/secured/%s', $entity->getId()));
        $content = $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testDelete()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $entity = $referenceRepository->getReference('secured-entity-0');
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );
        $response = $this->performDelete(
            $client,
            sprintf('/rest/secured/%s', $entity->getId()),
            [],
            []
        );
        $content = $this->assertJsonResponse($response, Response::HTTP_NO_CONTENT);

        $response = $this->performGet($client, sprintf('/rest/secured/%s', $entity->getId()));
        $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);
    }

    public function testPutUnauthorized()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient();

        $entity = $referenceRepository->getReference('secured-entity-0');

        /* No User */

        $response = $this->performPut(
            $client,
            sprintf('/rest/secured/%s', $entity->getId())
        );
        $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        /* Insufficient Privileges */

        $response = $this->performPut(
            $client,
            sprintf('/rest/secured/%s', $entity->getId()),
            [],
            []
        );
        $this->assertJsonResponse($response, Response::HTTP_FORBIDDEN);
    }

    public function testPut()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        $entity = $referenceRepository->getReference('secured-entity-0');

        $data = [
            'dateTimeField'  => '2011-02-03 04:05:06',
            'dateField'      => '2012-05-31',
            'timeField'      => '12:34:56',
            'embeddedEntity' => [
                'fieldString'  => 'haha',
                'fieldInteger' => 23
            ]
        ];

        $response = $this->performPut(
            $client,
            sprintf('/rest/secured/%s', $entity->getId()),
            [],
            [],
            $data
        );
        $content = $this->assertJsonResponse($response);

        $expectedContent = $data;
        $expectedContent['id'] = $entity->getId();
        $expectedContent['uuid'] = $entity->getUuid();
        $expectedContent['integerField'] = null;

        $this->assertContentEquals($expectedContent, $content, false);
    }

    public function testGetWithSubResources()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-0');

        $response = $this->performGet(
            $client,
            sprintf('/rest/secured/%s', $entity->getId()),
            ['include' => 'subResources,subResources._links'],
            []
        );
        $content = $this->assertJsonResponse($response);

        $this->assertCount(5, $content['subResources']);
        $id = $content['subResources'][0]['id'];
        $this->assertEquals(
            sprintf('http://localhost/rest/subresourceentities/%s', $id),
            $content['subResources'][0]['_links']['self']['href']
        );
    }

    public function testListSubResources()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-0');

        $response = $this->performGet(
            $client,
            sprintf('/rest/secured/%s/subresources', $entity->getId()),
            ['page' => 1, 'perPage' => 3],
            []
        );
        $content = $this->assertJsonResponse($response);
        $this->assertCount(3, $content);
        $this->assertPagination($response, 1, 3, 2, 5);
    }

    public function testAddSubResource()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-1');

        /** @var SubResourceEntity $subResourceEntity */
        $subResourceEntity = $referenceRepository->getReference('subresource-entity-11');

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        $response = $this->performPut(
            $client,
            sprintf('/rest/secured/%s/subresources/%s', $entity->getId(), $subResourceEntity->getId()),
            [],
            []
        );
        $this->assertJsonResponse($response, Response::HTTP_NO_CONTENT);

        $response = $this->performGet(
            $client,
            sprintf('/rest/secured/%s/subresources', $entity->getId()),
            [],
            []
        );
        $content = $this->assertJsonResponse($response);
        $this->assertCount(1, $content);
    }

    public function testAddParent()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();
        /** @var SecuredEntity $parent */
        $parent = $referenceRepository->getReference('secured-entity-1');

        /** @var SubResourceEntity $child */
        $child = $referenceRepository->getReference('subresource-entity-0');

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        $response = $this->performPut(
            $client,
            sprintf('/rest/subresourceentities/%s/parententity/%s', $child->getId(), $parent->getId()),
            [],
            []
        );
        $this->assertJsonResponse($response, Response::HTTP_NO_CONTENT, true);

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        $response = $this->performGet(
            $client,
            sprintf('/rest/subresourceentities/%s', $child->getId()),
            [],
            []
        );
        $content = $this->assertJsonResponse($response);

        $this->assertContentEquals(
            [
                'id'           => $child->getId(),
                'parentEntity' => [
                    'id'   => $parent->getId(),
                    'uuid' => $parent->getUuid()
                ],
                'text'         => null
            ],
            $content,
            false
        );
    }

    public function testRemoveSubResource()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-0');

        /** @var SubResourceEntity $subResourceEntity */
        $subResourceEntity = $referenceRepository->getReference('subresource-entity-2');

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        $response = $this->performDelete(
            $client,
            sprintf('/rest/secured/%s/subresources/%s', $entity->getId(), $subResourceEntity->getId()),
            [],
            []
        );
        $this->assertJsonResponse($response, Response::HTTP_NO_CONTENT);

        $response = $this->performGet(
            $client,
            sprintf('/rest/secured/%s/subresources', $entity->getId()),
            [],
            []
        );
        $content = $this->assertJsonResponse($response);
        $this->assertCount(4, $content);
    }

    public function testRemoveParent()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();

        /** @var SecuredEntity $parent */
        $parent = $referenceRepository->getReference('secured-entity-1');

        /** @var SubResourceEntity $child */
        $child = $referenceRepository->getReference('subresource-entity-2');

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        $response = $this->performDelete(
            $client,
            sprintf('/rest/subresourceentities/%s/parententity', $child->getId(), $parent->getId()),
            [],
            []
        );
        $this->assertJsonResponse($response, Response::HTTP_NO_CONTENT, true);

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );

        $response = $this->performGet(
            $client,
            sprintf('/rest/subresourceentities/%s', $child->getId()),
            [],
            []
        );
        $content = $this->assertJsonResponse($response);

        $this->assertContentEquals(
            [
                'id'           => $child->getId(),
                'parentEntity' => null,
                'text'         => null
            ],
            $content,
            false
        );
    }

    public function testSubResourcesListUnauthorized()
    {
        $client = $this->makeClient();
        $response = $this->performGet($client, '/rest/subresourceentities');
        $content = $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testSubResourcesList()
    {
        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW'   => 'user',
            ]
        );
        $response = $this->performGet(
            $client,
            '/rest/subresourceentities',
            [],
            []
        );
        $content = $this->assertJsonResponse($response);
        $this->assertCount(33, $content);
    }

    public function testPostSubresourceUnauthorized()
    {
        $referenceRepository = $this->loadFixtures([SecuredEntities::class])->getReferenceRepository();
        $client = $this->makeClient();

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-1');

        $response = $this->performPost($client, sprintf('/rest/secured/%s/subresources', $entity->getId()));
        $this->assertJsonResponse($response, Response::HTTP_UNAUTHORIZED);
    }

    public function testPostSubresource()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-1');

        $response = $this->performPost(
            $client,
            sprintf('/rest/secured/%s/subresources', $entity->getId()),
            [],
            [],
            ['text' => 'TestText']
        );
        $content = $this->assertJsonResponse($response, Response::HTTP_CREATED);
        $this->assertHasKeyAndUnset('id', $content);
        $this->assertContentEquals(
            [
                'parentEntity' => [
                    'id'   => $entity->getId(),
                    'uuid' => $entity->getUuid()
                ],
                'text'         => 'TestText'
            ],
            $content,
            false
        );
    }

    public function testPostSubresourceAsForm()
    {
        $referenceRepository = $this->loadFixtures([Users::class, SecuredEntities::class])->getReferenceRepository();

        $client = $this->makeClient(
            false,
            [
                'PHP_AUTH_USER' => 'admin',
                'PHP_AUTH_PW'   => 'admin',
            ]
        );

        /** @var SecuredEntity $entity */
        $entity = $referenceRepository->getReference('secured-entity-1');

        $response = $this->performPost(
            $client,
            sprintf('/rest/secured/%s/subresources', $entity->getId()),
            ['text' => 'TestText'],
            []
        );
        $content = $this->assertJsonResponse($response, Response::HTTP_CREATED);
        $this->assertHasKeyAndUnset('id', $content);
        $this->assertContentEquals(
            [
                'parentEntity' => [
                    'id'   => $entity->getId(),
                    'uuid' => $entity->getUuid()
                ],
                'text'         => 'TestText'
            ],
            $content,
            false
        );
    }

    public function testGetInheritedEntity()
    {
        $referenceRepository = $this->loadFixtures([InheritedEntities::class])->getReferenceRepository();

        $client = $this->makeClient();

        /** @var InheritedEntity $entity */
        $entity = $referenceRepository->getReference(InheritedEntities::INHERITED_ENTITY_0);
        $response = $this->performGet($client, sprintf('/rest/inheritedentities/%s', $entity->getId()));

        $content = $this->assertJsonResponse($response);
        $this->assertContentEquals(['id' => $entity->getId(), 'excludedFieldTwo' => 'two'], $content, false);
    }
}
