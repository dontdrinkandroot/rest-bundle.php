<?php

namespace Dontdrinkandroot\RestBundle\Controller;

use Dontdrinkandroot\RestBundle\Service\Normalizer;
use Dontdrinkandroot\RestBundle\Service\RestRequestParserInterface;
use Dontdrinkandroot\Service\CrudServiceInterface;
use Metadata\MetadataFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Philip Washington Sorst <philip@sorst.net>
 */
abstract class AbstractCrudServiceRestResourceController extends AbstractRestResourceController
{
    public function __construct(
        RestRequestParserInterface $requestParser,
        Normalizer $normalizer,
        ValidatorInterface $validator,
        RequestStack $requestStack,
        MetadataFactoryInterface $metadataFactory,
        PropertyAccessorInterface $propertyAccessor
    ) {
        parent::__construct(
            $requestParser,
            $normalizer,
            $validator,
            $requestStack,
            $metadataFactory,
            $propertyAccessor
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function listEntities(int $page = 1, int $perPage = 50)
    {
        return $this->getService()->findAllPaginated($page, $perPage);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchEntity($id)
    {
        $entity = $this->getService()->find($id);
        if (null === $entity) {
            throw new NotFoundHttpException();
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    protected function createEntity($entity)
    {
        return $this->getService()->create($entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function updateEntity($entity)
    {
        return $this->getService()->update($entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeEntity($entity)
    {
        $this->getService()->remove($entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function listSubresource($entity, string $subresource, int $page = 1, int $perPage = 50)
    {
        return $this->getService()->findAssociationPaginated($entity, $subresource, $page, $perPage);
    }

    /**
     * {@inheritdoc}
     */
    protected function buildAssociation($parent, string $subresource, $entity)
    {
        return $this->getService()->createAssociation($parent, $subresource, $entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function createAssociation($associatedEntity)
    {
        return $this->getService()->create($associatedEntity);
    }

    /**
     * {@inheritdoc}
     */
    protected function addAssociation($parent, string $subresource, $subId)
    {
        $this->getService()->addAssociation($parent, $subresource, $subId);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeAssociation($parent, string $subresource, $subId = null)
    {
        $this->getService()->removeAssociation($parent, $subresource, $subId);
    }

    protected function getServiceId()
    {
        return $this->getCurrentRequest()->attributes->get('_service');
    }

    /**
     * @return CrudServiceInterface
     */
    abstract protected function getService();
}
