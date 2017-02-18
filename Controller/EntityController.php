<?php

namespace Dontdrinkandroot\RestBundle\Controller;

use Doctrine\Common\Util\Inflector;
use Dontdrinkandroot\RestBundle\Metadata\Annotation\Right;
use Dontdrinkandroot\RestBundle\Metadata\ClassMetadata;
use Dontdrinkandroot\RestBundle\Metadata\PropertyMetadata;
use Dontdrinkandroot\RestBundle\Service\CrudServiceInterface;
use Dontdrinkandroot\RestBundle\Service\DoctrineEntityRepositoryCrudService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityController extends Controller
{
    public function listAction(Request $request)
    {
        $page = $request->query->get('page', 1);
        $perPage = $request->query->get('perPage', 50);

        $this->assertListGranted();

        //TODO: Add pagination headers
        $result = $this->listEntities($page, $perPage);
        $entities = $result->getIterator()->getArrayCopy();

        $normalizer = $this->get('ddr_rest.normalizer');
        $content = $normalizer->normalize($entities, $this->parseIncludes($request));

        return new JsonResponse($content, Response::HTTP_OK);
    }

    public function postAction(Request $request)
    {
        $this->assertPostGranted();
        $entity = $this->parseRequest($request, null, $this->getEntityClass());
        $entity = $this->postProcessPostedEntity($entity);

        /** @var ValidatorInterface $validator */
        $validator = $this->get('validator');
        $errors = $validator->validate($entity);
        if ($errors->count() > 0) {
            return new JsonResponse($this->parseConstraintViolations($errors), Response::HTTP_BAD_REQUEST);
        }

        $entity = $this->createEntity($entity);

        $normalizer = $this->get('ddr_rest.normalizer');
        $content = $normalizer->normalize($entity);

        return new JsonResponse($content, Response::HTTP_CREATED);
    }

    public function getAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);
        $this->assertGetGranted($entity);

        $normalizer = $this->get('ddr_rest.normalizer');
        $content = $normalizer->normalize($entity, $this->parseIncludes($request));

        return new JsonResponse($content);
    }

    public function putAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);
        $this->assertPutGranted($entity);
        $entity = $this->parseRequest($request, $entity, $this->getEntityClass());
        $entity = $this->postProcessPuttedEntity($entity);

        /** @var ValidatorInterface $validator */
        $validator = $this->get('validator');
        $errors = $validator->validate($entity);
        if ($errors->count() > 0) {
            return new JsonResponse($this->parseConstraintViolations($errors), Response::HTTP_BAD_REQUEST);
        }

        $entity = $this->updateEntity($entity);

        $normalizer = $this->get('ddr_rest.normalizer');
        $content = $normalizer->normalize($entity);

        return new JsonResponse($content);
    }

    public function deleteAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);
        $this->assertDeleteGranted($entity);
        $this->getService()->remove($entity);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function listSubresourceAction(Request $request, $id)
    {
        $subresource = $this->getSubresource();
        $entity = $this->fetchEntity($id);
        $this->assertSubresourceListGranted($entity, $subresource);
        $result = $this->listSubresource(
            $entity,
            $subresource,
            $request->query->get('page', 1),
            $request->query->get('perPage', 50)
        );
        $view = $this->createViewFromListResult($result);
        $view->getContext()->addGroups($this->getSubresourceSerializationGroups($subresource));

        return $this->handleView($view);
    }

    public function postSubresourceAction(Request $request, $id)
    {
        $subresource = $this->getSubresource();
        $parent = $this->fetchEntity($id);
        $this->assertSubresourcePostGranted($parent, $subresource);
        $entity = $this->parseRequest($request, null, $this->getSubResourceEntityClass($subresource));
        $entity = $this->postProcessSubResourcePostedEntity($subresource, $entity, $parent);
        $errors = $this->validate($entity);
        if ($errors->count() > 0) {
            return $this->handleView($this->view($errors, Response::HTTP_BAD_REQUEST));
        }
        $entity = $this->saveSubResource($subresource, $entity);

        return $this->handleView($this->view($entity, Response::HTTP_CREATED));
    }

    /**
     * @return CrudServiceInterface
     */
    protected function getService()
    {
        $serviceId = $this->getServiceId();
        if (null === $serviceId) {
            $entityClass = $this->getEntityClass();
            if (null === $entityClass) {
                throw new \RuntimeException('No service or entity class given');
            }
            $entityManager = $this->get('doctrine.orm.entity_manager');

            return new DoctrineEntityRepositoryCrudService(
                $entityManager,
                $entityClass
            );
        } else {
            /** @var CrudServiceInterface $service */
            $service = $this->get($serviceId);

            return $service;
        }
    }

    protected function parseRequest(Request $request, $entity = null, $entityClass = null)
    {
        return $this->get('ddr.rest.parser.request')->parseEntity($request, $entityClass, $entity);
    }

    /**
     * @param object $entity
     *
     * @return object
     */
    protected function postProcessPostedEntity($entity)
    {
        return $entity;
    }

    /**
     * @param object $entity
     *
     * @return object
     */
    protected function postProcessPuttedEntity($entity)
    {
        return $entity;
    }

    /**
     * @param string $subresource
     * @param object $parent
     * @param object $entity
     *
     * @return object
     */
    protected function postProcessSubResourcePostedEntity($subresource, $entity, $parent)
    {
        return $entity;
    }

    protected function fetchEntity($id)
    {
        $entity = $this->getService()->findById($id);
        if (null === $entity) {
            throw new NotFoundHttpException();
        }

        return $entity;
    }

    protected function listEntities($page = 1, $perPage = 50)
    {
        $service = $this->getService();

        return $service->listPaginated($page, $perPage);
    }

    protected function createEntity($entity)
    {
        return $this->getService()->create($entity);
    }

    protected function updateEntity($entity)
    {
        return $this->getService()->update($entity);
    }

    protected function listSubresource($entity, $subresource, $page = 1, $perPage = 50)
    {
        $propertyAccessor = $this->container->get('property_accessor');

        return $propertyAccessor->getValue($entity, $subresource);
    }

    protected function getEntityClass()
    {
        return $this->getCurrentRequest()->attributes->get('_entityClass');
    }

    protected function getShortName()
    {
        return Inflector::tableize($this->getClassMetadata()->reflection->getShortName());
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
        $classMetadata = $this->getClassMetadata();
        $right = $classMetadata->getListRight();
        if (null === $right) {
            return;
        }

        $this->denyAccessUnlessGranted($right->attributes);
    }

    protected function assertPostGranted()
    {
        $classMetadata = $this->getClassMetadata();
        $right = $classMetadata->getPostRight();
        if (null === $right) {
            throw $this->createAccessDeniedException();
        }

        $this->denyAccessUnlessGranted($right->attributes);
    }

    protected function assertGetGranted($entity)
    {
        $classMetadata = $this->getClassMetadata();
        $right = $classMetadata->getGetRight();
        if (null === $right) {
            return;
        }

        $this->assertRightGranted($entity, $right);
    }

    protected function assertPutGranted($entity)
    {
        $classMetadata = $this->getClassMetadata();
        $right = $classMetadata->getPutRight();
        if (null === $right) {
            throw $this->createAccessDeniedException();
        }

        $this->assertRightGranted($entity, $right);
    }

    protected function assertDeleteGranted($entity)
    {
        $classMetadata = $this->getClassMetadata();
        $right = $classMetadata->getDeleteRight();
        if (null === $right) {
            throw $this->createAccessDeniedException();
        }

        $this->assertRightGranted($entity, $right);
    }

    protected function assertSubresourceListGranted($entity, $subresource)
    {
        $classMetadata = $this->getClassMetadata();
        /** @var PropertyMetadata $propertyMetadata */
        $propertyMetadata = $classMetadata->propertyMetadata[$subresource];
        $right = $propertyMetadata->getSubResourceListRight();
        if (null === $right) {
            return;
        }

        $this->assertRightGranted($entity, $right);
    }

    protected function assertSubresourcePostGranted($entity, $subresource)
    {
        $classMetadata = $this->getClassMetadata();
        /** @var PropertyMetadata $propertyMetadata */
        $propertyMetadata = $classMetadata->propertyMetadata[$subresource];
        $right = $propertyMetadata->getSubResourcePostRight();
        if (null === $right) {
            throw $this->createAccessDeniedException();
        }

        $this->assertRightGranted($entity, $right);
    }

    /**
     * @return ClassMetadata
     */
    protected function getClassMetadata()
    {
        $metaDataFactory = $this->get('ddr_rest.metadata.factory');
        /** @var ClassMetadata $classMetaData */
        $classMetaData = $metaDataFactory->getMetadataForClass($this->getEntityClass());

        return $classMetaData;
    }

    protected function getSubResourceEntityClass($subresource)
    {
        /** @var PropertyMetadata $propertyMetadata */
        $propertyMetadata = $this->getClassMetadata()->propertyMetadata[$subresource];

        return $propertyMetadata->getTargetClass();
    }

    protected function resolveSubject($entity, $propertyPath)
    {
        if ('this' === $propertyPath) {
            return $entity;
        }
        $propertyAccessor = $this->get('property_accessor');

        return $propertyAccessor->getValue($entity, $propertyPath);
    }

    /**
     * @param object $entity
     * @param Right  $right
     */
    protected function assertRightGranted($entity, Right $right)
    {
        $propertyPath = $right->propertyPath;
        if (null === $propertyPath) {
            $this->denyAccessUnlessGranted($right->attributes);
        } else {
            $subject = $this->resolveSubject($entity, $propertyPath);
            $this->denyAccessUnlessGranted($right->attributes, $subject);
        }
    }

    /**
     * @return string[]
     */
    protected function getSubresourceSerializationGroups($subresource)
    {
        return ['Default', 'ddr.rest.subresource', 'ddr.rest.' . $this->getShortName() . '.' . $subresource];
    }

    /**
     * @param string $subresource
     * @param object $entity
     *
     * @return
     */
    protected function saveSubResource($subresource, $entity)
    {
        return $this->getService()->save($entity);
    }

    /**
     * @return string|null
     */
    protected function getSubresource()
    {
        return $this->getCurrentRequest()->attributes->get('_subresource');
    }

    protected function parseIncludes(Request $request)
    {
        $includeString = $request->query->get('include');
        if (empty($includeString)) {
            return [];
        }

        return explode(',', $includeString);
    }

    private function parseConstraintViolations(ConstraintViolationListInterface $errors)
    {
        $data = [];
        /** @var ConstraintViolationInterface $error */
        foreach ($errors as $error) {
            $data[] = [
                'propertyPath' => $error->getPropertyPath(),
                'message'      => $error->getMessage(),
                'value'        => $error->getInvalidValue()
            ];
        }

        return $data;
    }
}
