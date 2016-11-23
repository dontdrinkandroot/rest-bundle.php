<?php

namespace Dontdrinkandroot\RestBundle\Controller;

use Dontdrinkandroot\Entity\EntityInterface;
use Dontdrinkandroot\Entity\UuidEntityInterface;
use Dontdrinkandroot\FullStackTestBundle\Entity\BlogPost;
use Dontdrinkandroot\Pagination\PaginatedResult;
use Dontdrinkandroot\Repository\UuidEntityRepositoryInterface;
use Dontdrinkandroot\Service\EntityService;
use Dontdrinkandroot\Service\EntityServiceInterface;
use Dontdrinkandroot\Service\UuidEntityService;
use Dontdrinkandroot\Service\UuidEntityServiceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntityController extends DdrRestController
{
    public function listAction(Request $request)
    {
        $this->assertListGranted();
        $result = $this->listEntities($request->query->get('page', 1), $request->query->get('perPage', 50));
        $view = $this->createViewFromListResult($result);
        $view->getContext()->addGroups(['Default', 'ddr.rest.list']);

        return $this->handleView($view);
    }

    public function postAction(Request $request)
    {
        $this->assertPostGranted();
        $entity = $this->parseRequest($request);
        $entity = $this->postProcessPostedEntity($entity);
        $errors = $this->validate($entity);
        if ($errors->count() > 0) {
            return $this->handleView($this->view($errors, Response::HTTP_BAD_REQUEST));
        }
        $entity = $this->getService()->save($entity);

        return $this->handleView($this->view($entity, Response::HTTP_CREATED));
    }

    public function getAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);
        $this->assertGetGranted($entity);
        $view = $this->view($entity);
        $view->getContext()->addGroups(['Default', 'ddr.rest.get']);

        return $this->handleView($view);
    }

    public function putAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);
        $this->assertPutGranted($entity);
        $entity = $this->parseRequest($request, $entity);
        $entity = $this->postProcessPuttedEntity($entity);
        $errors = $this->validate($entity);
        if ($errors->count() > 0) {
            return $this->handleView($this->view($errors, Response::HTTP_BAD_REQUEST));
        }
        $entity = $this->getService()->save($entity);

        return $this->handleView($this->view($entity));
    }

    public function deleteAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);
        $this->assertDeleteGranted($entity);
        $this->getService()->remove($entity);

        return $this->handleView($this->view(null, Response::HTTP_NO_CONTENT));
    }

    public function listSubresourceAction(Request $request, $id)
    {
        $subresource = $this->getCurrentRequest()->attributes->get('_subresource');
        $entity = $this->fetchEntity($id);
        $this->assertSubresourceListGranted($entity, $subresource);
        $result = $this->listSubresource(
            $entity,
            $subresource,
            $request->query->get('page', 1),
            $request->query->get('perPage', 50)
        );
        $view = $this->createViewFromListResult($result);

        return $this->handleView($view);
    }

    /**
     * @return EntityServiceInterface|UuidEntityServiceInterface
     */
    protected function getService()
    {
        $serviceId = $this->getServiceId();
        if (null === $serviceId) {
            $entityClass = $this->getEntityClass();
            if (null === $entityClass) {
                throw new \RuntimeException('No service or entity class given');
            }
            $repository = $this->get('doctrine')->getRepository($entityClass);
            if ($repository instanceof UuidEntityRepositoryInterface) {
                return new EntityService($repository);
            } else {
                return new UuidEntityService($repository);
            }
        } else {
            /** @var EntityServiceInterface|UuidEntityServiceInterface $service */
            $service = $this->get($serviceId);

            return $service;
        }
    }

    protected function parseRequest(Request $request, EntityInterface $entity = null)
    {
        return $this->get('ddr.rest.parser.request')->parseEntity(
            $request,
            $this->getService()->getEntityClass(),
            $entity
        );
    }

    /**
     * @param EntityInterface $entity
     *
     * @return EntityInterface
     */
    protected function postProcessPostedEntity(EntityInterface $entity)
    {
        return $entity;
    }

    /**
     * @param EntityInterface $entity
     *
     * @return EntityInterface
     */
    protected function postProcessPuttedEntity(EntityInterface $entity)
    {
        return $entity;
    }

    protected function fetchEntity($id)
    {
        if (is_a($this->getEntityClass(), UuidEntityInterface::class, true)) {
            return $this->getService()->fetchByUuid($id);
        } else {
            return $this->getService()->fetchById($id);
        }
    }

    protected function listEntities($page = 1, $perPage = 50)
    {
        $service = $this->getService();

        return $service->listPaginated(1, 10);
    }

    protected function listSubresource(EntityInterface $entity, $subresource, $page = 1, $perPage = 50)
    {
        $propertyAccessor = $this->container->get('property_accessor');

        return $propertyAccessor->getValue($entity, $subresource);
    }

    protected function isUuid($id)
    {
        return preg_match('/' . UuidEntityInterface::VALID_UUID_PATTERN . '/', $id);
    }

    protected function getEntityClass()
    {
        return $this->getCurrentRequest()->attributes->get('_entityClass');
    }

    protected function getServiceId()
    {
        return $this->getCurrentRequest()->attributes->get('_service');
    }

    protected function getCurrentRequest()
    {
        return $this->get('request_stack')->getCurrentRequest();
    }

    protected function assertListGranted()
    {
        /* Hook */
    }

    protected function assertPostGranted()
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
    }

    protected function assertGetGranted(EntityInterface $entity)
    {
        /* Hook */
    }

    protected function assertPutGranted(EntityInterface $entity)
    {
        $this->assertPostGranted();
    }

    protected function assertDeleteGranted(EntityInterface $entity)
    {
        /* Hook */
    }

    protected function assertSubresourceListGranted($entity, $subresource)
    {
        /* Hook */
    }

    /**
     * @param array|PaginatedResult $result
     *
     * @return View
     */
    protected function createViewFromListResult($result)
    {
        if ($result instanceof PaginatedResult) {
            $view = $this->view($result->getResults());
            $this->addPaginationHeaders($result->getPagination(), $view);

            return $view;
        }

        return $this->view($result);
    }
}