<?php


namespace Dontdrinkandroot\RestBundle\Controller;

use Dontdrinkandroot\DoctrineBundle\Controller\EntityControllerInterface;
use Dontdrinkandroot\Entity\EntityInterface;
use Dontdrinkandroot\Repository\OrmEntityRepository;
use Dontdrinkandroot\Utils\StringUtils;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractEntityController extends BaseController implements EntityControllerInterface
{

    protected $routePrefix = null;

    protected $pathPrefix = null;

    /**
     * @param Request $request
     *
     * @return Response
     * @throws \Exception
     */
    public function listAction(Request $request)
    {
        $user = $this->getUser();
        $this->checkListActionAuthorization($user);

        $page = $request->query->get('page', 1);
        $perPage = $request->query->get('perpage', 10);

        $paginatedEntities = $this->getRepository()->findPaginatedBy($page, $perPage);
        $entities = $paginatedEntities->getResults();
        $pagination = $paginatedEntities->getPagination();

        $view = $this->view($entities);
        $this->addPaginationHeaders($pagination, $view);

        $serializationContext = $view->getSerializationContext();
        $serializationContext = $this->configureListActionSerializiationContext($serializationContext);
        $view->setSerializationContext($serializationContext);

        return $this->handleView($view);
    }

    /**
     * @param Request $request
     * @param mixed   $id
     *
     * @return Response
     */
    public function detailAction(Request $request, $id)
    {
        $entity = $this->fetchEntity($id);

        $user = $this->getUser();
        $this->checkDetailActionAuthorization($user, $entity);

        $view = $this->view($entity);

        $serializationContext = $view->getSerializationContext();
        $serializationContext = $this->configureDetailActionSerializiationContext($serializationContext);
        $view->setSerializationContext($serializationContext);

        return $this->handleView($view);
    }

    /**
     * @param Request $request
     * @param mixed|null|string $id
     *
     * @return Response
     */
    public function editAction(Request $request, $id = null)
    {
        $create = (null === $id);

        $user = $this->getUser();
        $originalEntity = null;
        if ($create) {
            $this->checkCreateActionAuthorization($user);
        } else {
            $originalEntity = $this->fetchEntity($id);
            $this->checkUpdateActionAuthorization($user, $originalEntity);
        }

        $entity = $this->deserializeRequestContent($request, $this->getEntityClass());
        $entity = $this->postProcessDeserializedEntity($entity, $originalEntity);

        $errors = $this->validate($entity);
        if (count($errors) > 0) {
            $view = $this->view($errors, Response::HTTP_BAD_REQUEST);

            return $this->handleView($view);
        }

        if ($create) {
            $entity = $this->getRepository()->persist($entity);
        } else {
            $entity = $this->getRepository()->merge($entity);
        }

        $status = $create ? Response::HTTP_CREATED : Response::HTTP_OK;

        $view = $this->view($entity, $status);

        if ($create) {
            $view->setHeader(
                'Location',
                $this->generateUrl($this->getDetailRoute(), ['id' => $entity->getId()], true)
            );
        }

        return $this->handleView($view);
    }

    /**
     * @param Request $request
     * @param mixed   $id
     *
     * @return Response
     * @throws \Exception
     */
    public function deleteAction(Request $request, $id)
    {
        $user = $this->getUser();
        $entity = $this->fetchEntity($id);
        $this->checkDeleteActionAuthorization($user, $entity);

        $this->getRepository()->remove($entity);
        $view = $this->view();
        $view->setStatusCode(Response::HTTP_NO_CONTENT);

        return $this->handleView($view);
    }

    /**
     * @param string|null $routePrefix
     */
    public function setRoutePrefix($routePrefix)
    {
        $this->routePrefix = $routePrefix;
    }

    /**
     * @param string|null $pathPrefix
     */
    public function setPathPrefix($pathPrefix)
    {
        $this->pathPrefix = $pathPrefix;
    }

    /**
     * @param $id
     *
     * @return EntityInterface
     */
    protected function fetchEntity($id)
    {
        $entity = $this->getRepository()->find($id);
        if (null === $entity) {
            throw new NotFoundHttpException();
        }

        return $entity;
    }

    /**
     * @return OrmEntityRepository
     */
    protected function getRepository()
    {
        return $this->getDoctrine()->getRepository($this->getEntityClass());
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutePrefix()
    {
        if (null !== $this->routePrefix) {
            return $this->routePrefix;
        }

        list($bundle, $entityName) = $this->extractBundleAndEntityName();

        $prefix = str_replace('Bundle', '', $bundle);
        $prefix = $prefix . '.' . $entityName;
        $prefix = str_replace('\\', '.', $prefix);
        $prefix = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $prefix));

        return $prefix . '.rest';
    }

    /**
     * {@inheritdoc}
     */
    public function getPathPrefix()
    {
        if (null !== $this->pathPrefix) {
            return $this->pathPrefix;
        }

        list($bundle, $entityName) = $this->extractBundleAndEntityName();

        return '/' . strtolower($entityName) . '/';
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function extractBundleAndEntityName()
    {
        $shortName = $this->getEntityShortName();
        $parts = explode(':', $shortName);
        if (2 !== count($parts)) {
            throw new \Exception(sprintf('Expecting entity class to be "Bundle:Entity", %s given', $shortName));
        }

        return $parts;
    }

    /**
     * @return string
     */
    protected function getDetailRoute()
    {
        return $this->getRoutePrefix() . ".detail";
    }

    /**
     * @return string
     */
    protected function getEntityShortName()
    {
        $entityClass = $this->getEntityClass();
        $entityClassParts = explode('\\', $entityClass);

        $bundle = $this->findBundle($entityClassParts);
        $className = $entityClassParts[count($entityClassParts) - 1];

        $shortName = $bundle . ':' . $className;

        return $shortName;
    }

    /**
     * @param array $entityClassParts
     *
     * @return string
     */
    private function findBundle(array $entityClassParts)
    {
        foreach ($entityClassParts as $part) {
            if (StringUtils::endsWith($part, 'Bundle')) {
                return $part;
            }
        }

        throw new \RuntimeException('No Bundle found in namespace');
    }

    /**
     * @param EntityInterface $entity
     * @param EntityInterface $originalEntity
     *
     * @return EntityInterface
     */
    protected function postProcessDeserializedEntity(EntityInterface $entity, EntityInterface $originalEntity = null)
    {
        return $entity;
    }

    /**
     * @param SerializationContext $serializationContext
     *
     * @return SerializationContext
     */
    protected function configureListActionSerializiationContext(SerializationContext $serializationContext)
    {
        return $serializationContext;
    }

    /**
     * @param SerializationContext $serializationContext
     *
     * @return SerializationContext
     */
    protected function configureDetailActionSerializiationContext(SerializationContext $serializationContext)
    {
        return $serializationContext;
    }

    /**
     * @param $user
     */
    protected function checkListActionAuthorization($user)
    {
    }

    /**
     * @param                 $user
     * @param EntityInterface $entity
     */
    protected function checkDetailActionAuthorization($user, EntityInterface $entity)
    {
    }

    /**
     * @param $user
     */
    protected function checkCreateActionAuthorization($user)
    {
    }

    /**
     * @param                 $user
     * @param EntityInterface $entity
     */
    protected function checkUpdateActionAuthorization($user, EntityInterface $entity)
    {
    }

    /**
     * @param                 $user
     * @param EntityInterface $entity
     */
    protected function checkDeleteActionAuthorization($user, EntityInterface $entity)
    {
    }

    /**
     * @return string
     */
    protected abstract function getEntityClass();


}