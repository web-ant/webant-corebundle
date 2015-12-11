<?php
/**
 * This file is part of the WebAnt CoreBundle package.
 *
 * Yuri Kovalev <u@webant.ru>
 * Vladimir Daron <v@webant.ru>
 *
 */

namespace WebAnt\CoreBundle\Controller;

use WebAnt\CoreBundle\Util\CamelCase;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use JMS\Serializer\SerializationContext;


abstract class AbstractController extends FOSRestController
{
    protected $objectClass;
    protected $objectKey = 'id';

    /**
     * Создание обьекта
     *
     * @param  array $requestArray
     * @return Object
     */
    public function createObject($requestArray)
    {
        $em     = $this->getDoctrine()->getManager();
        $object = $this->arrayToObject($requestArray);

        try {
            $em->flush();
        } catch
        (\Exception $e) {
            throw new HttpException(400, 'Error create: ' . $e->getMessage());
        }

        return $object;
    }

    /**
     * Получить объект по ключу
     *
     * @param integer $keyValue
     * @param array  $findArray
     *
     * @return Object
     */
    public function getObject($keyValue = FALSE, $findArray = [])
    {
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy';
        ($keyValue === FALSE) ?: $findArray[$this->objectKey] = $keyValue;
        $object = $repository->$findFunction($findArray);
        if (!$object) {
            throw new HttpException(404, 'No object this key (' . $keyValue . ').');
        }

        return $object;
    }

    /**
     * Получить список объектов
     *
     * @param Request $request   - Запрос
     * @param array   $findArray - Массив параметров поиска
     *
     * @return array
     */
    public function getListObject(Request $request, $findArray = [])
    {
        $repository = $this->getObjectRepository();

        $reflect    = new \ReflectionClass($this->objectClass);
        $properties = $reflect->getProperties();
        $orderArray = [];
        $limit      = NULL;
        $offset     = NULL;

        foreach ($properties as $property) {
            if ($request->query->get($property->getName(), FALSE)) {
                $findArray[$property->getName()] = explode("|", $request->query->get($property->getName()));
            }
            if ($request->query->get("orderby") == $property->getName()) {
                $orderArray[$property->getName()] = 'ASC';
            } elseif ($request->query->get("orderbydesc") == $property->getName()) {
                $orderArray[$property->getName()] = 'DESC';
            }
        }
        if (preg_match('/^[0-9]+$/', $request->query->get("limit"))) {
            $limit = (int)$request->query->get("limit");
        }
        if (
            preg_match('/^[0-9]+$/', $request->query->get("start")) &&
            preg_match('/^[0-9]+$/', $request->query->get("limit"))
        ) {
            $offset = (int)$request->query->get("start");
        }

        $objects = $repository->findBy($findArray, $orderArray, $limit, $offset);
        if (count($objects) <= 0) {
            $response['items'] = [];
            $response['count'] = 0;

            return $response;
        }
        $count           = count($objects);
        $response['items'] = $objects;
        $response['count'] = $count;

        return $response;
    }

    /**
     *
     * Обновление информации об объекте
     *
     * @param array    $requestArray
     * @param integer  $keyValue
     *
     * @return Object
     */
    public function updateObject($requestArray, $keyValue)
    {

        $em           = $this->getDoctrine()->getManager();
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy';
        $object       = $repository->$findFunction($keyValue);

        if (!is_object($object)) {
            throw new HttpException(404, 'No object this key (' . $keyValue . ').');
        }

        $object = $this->arrayToObject($requestArray, $object);

        try {
            $em->flush();
        } catch
        (\Exception $e) {
            throw new HttpException(400, 'Error update: ' . $e->getMessage());
        }

        return $object;
    }

    /**
     *
     * Метод позволяет обновить данные объекта, если объект отсутствует то создается новый
     *
     * @param array   $requestArray
     * @param integer $keyValue
     *
     * @return Object
     */
    public function updateOrCreateObject($requestArray, $keyValue)
    {
        $em           = $this->getDoctrine()->getManager();
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy';
        $object       = $repository->$findFunction($keyValue);

        if (!is_object($object)) {
            $object = $this->arrayToObject($requestArray);
        } else {
            $object = $this->arrayToObject($requestArray, $object);
        }

        try {
            $em->flush();
        } catch
        (\Exception $e) {
            throw new HttpException(400, 'Error crate or update: ' . $e->getMessage());
        }

        return $object;
    }

    /**
     * Удалить объект
     *
     * @param integer $keyValue
     *
     * @return array
     */
    public function  deleteObject($keyValue)
    {
        $em           = $this->getDoctrine()->getManager();
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy' . ucfirst($this->objectKey);// Зачем конкатанация objectKey ?
        $object       = $repository->$findFunction((int)$keyValue);

        if (!is_object($object)) {
            throw new HttpException(404, 'No object this key (' . $keyValue . ').');
        }

        $em->remove($object);

        try {
            $em->flush();
        } catch (\Exception $e) {
            throw new HttpException(409, 'Error delete: ' . $e->getMessage());
        }

        return ['ms' => 'ok'];
    }

    /**
     * Заполнение объекта из массива
     *
     * @param array          $requestArray
     * @param boolean|Object $object
     *
     * @return Object
     */
    public function arrayToObject($requestArray, $object = FALSE)
    {
        if (!is_array($requestArray)) {
            throw new HttpException(400, 'Error object create');
        }

        $em         = $this->getDoctrine()->getManager();
        $reflect    = new \ReflectionClass($this->objectClass);
        $namespace  = $reflect->getNamespaceName();
        $properties = $reflect->getProperties();

        if (!$object)
            $object = new $this->objectClass();

        //устанавливаем значения
        foreach ($properties as $property) {
            $propertyName = CamelCase::fromCamelCase($property->getName());
            $value    = isset($requestArray[$propertyName]) ? $requestArray[$propertyName] : NULL;
            $property->setAccessible(TRUE);

            if (preg_match('/@var\s+([^\s]+)/', $property->getDocComment(), $matches)) {
                list(, $type) = $matches;

                if (class_exists($namespace . "\\" . $type)) {
                    $type = $namespace . "\\" . $type;
                }

                if ($type == '\DateTime' && !is_null($value) && !is_object($value)) {
                    $value = new \DateTime($value);
                }

                // нужно проверить с arrayCollection (value = [1,2,4])
                //если свойство объекта является объектом, то проверяем его существование
                if (class_exists($type) && !is_null($value) && $value != [] && !is_object($value)) {
                    $repository   = $em->getRepository($type);
                    $findFunction = 'findOneById';
                    $subObject    = $repository->$findFunction($value);
                    if (!$subObject) {
                        throw new HttpException(400, 'Not found object (' . $type . ').');
                    }

                    $property->setValue($object, $subObject);
                } else if (!is_null($value)) {
                    $property->setValue($object, $value);
                }
            }
        }
        //запуск валидатора
        $this->validate($object);

        $em->persist($object);

        return $object;
    }

    /**
     * Валидация обьекта (Entity)
     *
     * @param Object $object
     * @return boolean
     */
    protected function validate($object)
    {
        $validator = $this->get('validator');
        $errors    = $validator->validate($object);
        if (count($errors) > 0) {
            throw new HttpException(400, 'Bad request (' . print_r($errors->get(0)->getMessage(), true) . ').');
        }

        return TRUE;
    }

    /**
     * Получить отображание из группы $group
     *
     * @param object $obj
     * @param string $group
     *
     * @return view
     *
     */
    public function getObjectGroup($obj, $group)
    {
        $view    = $this->view();
        $context = SerializationContext::create()->enableMaxDepthChecks();
        $context->setGroups([$group]);
        $view->setSerializationContext($context);
        $view->setData($obj);

        return $view;
    }

    /**
     * Получение репозитория
     *
     * @return ObjectRepository
     */
    protected function getObjectRepository()
    {
        /**
         * @var EntityManager
         */
        $em = $this->getDoctrine()->getManager();

        return $em->getRepository($this->objectClass);
    }

    /**
     * Проверка на валидность JSON
     *
     * @param Request $request
     *
     * @return array
     */
    protected function checkJson(Request $request)
    {
        if ('json' !== $request->getContentType()) {
            throw new HttpException(400, 'Invalid content type');
        }
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || $request->getContent() == '') {
            throw new HttpException(400, 'Invalid content type');
        }

        return $data;
    }
}