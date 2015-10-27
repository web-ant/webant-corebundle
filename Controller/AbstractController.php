<?php

namespace WebAnt\CoreBundle\Controller;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use JMS\Serializer\SerializationContext;


abstract class AbstractController extends FOSRestController
{


    protected        $objectClass;
    protected        $objectKey  = 'id';
    static protected $entityPath = 'WebAnt\BaseBundle\Entity\\';


    private function from_camel_case($input)
    {

        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);

        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
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
     * @return mixed
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

    protected function throwErrorIfNotValid($entity)
    {
        $validator = $this->get('validator');
        $errors    = $validator->validate($entity);
        if (count($errors) > 0) {
            throw new HttpException(400, 'Bad request (' . print_r($errors, true) . ').');
        }

        return true;
    }

    /**
     * Получить объект по ключу
     *
     *
     * @param string $keyValue
     * @param array  $findArray
     *
     * @return Object
     */
    public function getObject($keyValue, $findArray = [])
    {
        $repository                  = $this->getObjectRepository();
        $findFunction                = 'findOneBy';
        (!$keyValue)?: $findArray[$this->objectKey] = $keyValue;
        $object                      = $repository->$findFunction($findArray);
        if (!$object) {
            throw new HttpException(404, 'No object this key (' . $keyValue . ').');
        }

        return $object;
    }

    /**
     * Получить отображание из группы $group
     *
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
     * Получить список объектов
     *
     *
     * @param Request $request   - Запрос
     * @param array   $findArray - Массив параметров поиска
     *
     * @return Object
     *
     */
    public function getListObject(Request $request, $findArray = [])
    {
        $repository = $this->getObjectRepository();

        $reflect    = new \ReflectionClass($this->objectClass);
        $properties = $reflect->getProperties();
        $orderArray = [];
        $limit      = null;
        $offset     = null;

        foreach ($properties as $prop) {
            if ($request->query->get($prop->getName())) {
                $findArray[$prop->getName()] = explode("|", $request->query->get($prop->getName()));
            }
            if ($request->query->get("orderby") == $prop->getName()) {
                $orderArray[$prop->getName()] = 'ASC';
            } elseif ($request->query->get("orderbydesc") == $prop->getName()) {
                $orderArray[$prop->getName()] = 'DESC';
            }
        }
        if (preg_match('/^[0-9]+$/', $request->query->get("limit"))) {
            $limit = (int)$request->query->get("limit");
        }
        if (preg_match('/^[0-9]+$/', $request->query->get("start")) &&
            preg_match('/^[0-9]+$/', $request->query->get("limit"))
        ) {
            $offset = (int)$request->query->get("start");
        }

        $objects = $repository->findBy($findArray, $orderArray, $limit, $offset);
        if (!$objects) {
            $object['items'] = [];
            $object['count'] = 0;

            return $object;
        }
        $count           = count($repository->findBy($findArray));
        $object['items'] = $objects;
        $object['count'] = $count;

        return $object;
    }

    /**
     * Удалить объект
     *
     * @param string $keyValue
     * @param array  $arrayClass
     * @param array  $arrayField
     * @param array  $arrayCallBack
     * @param null   $beforeFunction
     * @param null   $afterFunction
     *
     * @return array
     */
    public function  deleteObject($keyValue, $arrayClass = [], $arrayField = [], $arrayCallBack = [], $beforeFunction = null, $afterFunction = null)
    {
        //если массивы не равны
        if (!(sizeof($arrayClass) === sizeof($arrayField) && sizeof($arrayClass) === sizeof($arrayCallBack))
        ) {
            throw new HttpException(400, 'Error');
        }

        $em           = $this->getDoctrine()->getManager();
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy' . ucfirst($this->objectKey);
        $object       = $repository->$findFunction($keyValue);

        if (!$object) {
            throw new HttpException(404, 'Objects not found');
        }

        if (!is_null($beforeFunction)) {
            call_user_func($beforeFunction, $object);
        }

        $object->Del = true;

        //ищем зависимые объекты
        $count_class = count($arrayClass);
        for ($i = 0; $i < $count_class; $i++) {
            $items = $em->getRepository($arrayClass[$i])->findBy([$arrayField[$i] => $keyValue]);
            if ($items) {
                $callBack = $arrayCallBack[$i];
                foreach ($items as $item) {
                    call_user_func($callBack, $em, $item, $object);
                }
            }
        }

        if ($object->Del) {
            $em->remove($object);
        }

        try {
            $em->flush();
            if (!is_null($afterFunction)) {
                call_user_func($afterFunction);
            }
        } catch
        (\Exception $e) {
            throw new HttpException(409, 'Error with Database ' . $e->getMessage());
        }

        return ['ms' => 'ok'];
    }

    public function createObject($requestArray, $arrayRequestName = [], $arrayClass = [], $arrayField = [])
    {


        //если массивы не равны
        if (!(sizeof($arrayRequestName) === sizeof($arrayClass) && sizeof($arrayClass) === sizeof($arrayField))) {
            throw new HttpException(400, 'Error');
        }

        for ($i = 0; $i < count($arrayRequestName); $i++) {
            $requestArray[$arrayField[$i]] = $requestArray[$arrayRequestName[$i]];
            unset($requestArray[$arrayRequestName[$i]]);
        }

        $em     = $this->getDoctrine()->getManager();
        $object = $this->arrayToObject($requestArray);

        try {
            $em->flush();
        } catch
        (\Exception $e) {

            throw new HttpException(400, 'Error with Database ' . $e->getMessage());
        }

        return $object;

    }

    public function updateOrCreateObject($requestArray, $keyValue, $arrayRequestName = [], $arrayClass = [], $arrayField = [])
    {
        //если массивы не равны
        if (!(sizeof($arrayRequestName) === sizeof($arrayClass) && sizeof($arrayClass) === sizeof($arrayField))) {
            throw new HttpException(400, 'Error');
        }

        for ($i = 0; $i < count($arrayRequestName); $i++) {
            $requestArray[$arrayField[$i]] = $requestArray[$arrayRequestName[$i]];
            unset($requestArray[$arrayRequestName[$i]]);
        }

        return $this->megaUpdateOrCreateObject($requestArray, $keyValue);
    }

    public function tempObject($requestArray, $arrayRequestName = [], $arrayClass = [], $arrayField = [])
    {
        $em = $this->getDoctrine()->getManager();
        //если массивы не равны
        if (!(sizeof($arrayRequestName) === sizeof($arrayClass) && sizeof($arrayClass) === sizeof($arrayField))) {
            throw new HttpException(400, 'Error');
        }


        $reflect    = new \ReflectionClass($this->objectClass);
        $properties = $reflect->getProperties();
        $object     = new $this->objectClass();

        //проверяем существование зависимых объектов
        for ($i = 0; $i < sizeof($arrayRequestName); $i++) {
            if (isset($requestArray[$arrayRequestName[$i]])) {
                if ($requestArray[$arrayRequestName[$i]] == 0) {
                    $objects[$arrayField[$i]] = 0;
                } else if (isset($requestArray[$arrayRequestName[$i]])) {
                    $repository   = $em->getRepository($arrayClass[$i]);
                    $findFunction = 'findOneById';
                    $subObject    = $repository->$findFunction($requestArray[$arrayRequestName[$i]]);

                    if (!$subObject) {
                        throw new HttpException(400, 'No object (' . $arrayField[$i] . ').');
                    }

                    $objects[$arrayField[$i]] = $subObject;
                }
            }
        }

        //устанавливаем значения
        foreach ($properties as $prop) {
            $setter       = 'set' . ucfirst($prop->getName());
            $prop_name    = $this->from_camel_case($prop->getName());
            $valueRequest = (isset($requestArray[$prop_name])) ? $requestArray[$prop_name] : null;//$request->get($prop->getName());
            $valueObject  = (isset($objects[$prop_name])) ? $objects[$prop_name] : null;


            if (isset($valueObject) && $reflect->hasMethod($setter)) {
                if ($valueObject === 0) {
                    $object->$setter(null);
                } else {
                    $object->$setter($valueObject);
                }
            } else if (isset($valueRequest) && $reflect->hasMethod($setter) && !in_array($prop->getName(), $arrayField)
            ) {
                $object->$setter($valueRequest);
            }
        }

        //запуск валидатора
        $this->throwErrorIfNotValid($object);

        $em->persist($object);

        return $object;

    }

    public function updateObject($requestArray, $keyValue, $arrayRequestName = [], $arrayClass = [], $arrayField = [], $beforeFunction = null, $afterFunction = null)
    {
        //если массивы не равны
        if (!(sizeof($arrayRequestName) === sizeof($arrayClass) && sizeof($arrayClass) === sizeof($arrayField))) {
            throw new HttpException(400, 'Error');
        }

        for ($i = 0; $i < count($arrayRequestName); $i++) {
            $requestArray[$arrayField[$i]] = $requestArray[$arrayRequestName[$i]];
            unset($requestArray[$arrayRequestName[$i]]);
        }

        return $this->megaUpdateObject($requestArray, $keyValue, $beforeFunction, $afterFunction);
    }

    /**
     *
     * Метод позволяет обновить данные объекта, если объект отсутствует то создается новый
     *
     * @param      $requestArray
     * @param      $keyValue
     * @param null $beforeFunction
     * @param null $afterFunction
     *
     * @return Object
     */
    public function megaUpdateOrCreateObject($requestArray, $keyValue, $beforeFunction = null, $afterFunction = null)
    {
        $em           = $this->getDoctrine()->getManager();
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy' . ucfirst($this->objectKey);
        $object       = $repository->$findFunction($keyValue);

        if (!is_null($beforeFunction)) {
            call_user_func($beforeFunction, $object);
        }
        if (!isset($object)) {
            $object = $this->arrayToObject($requestArray);
        } else {
            $object = $this->arrayToObject($requestArray, $object);
        }

        try {
            $em->flush();
            if (!is_null($afterFunction)) {
                call_user_func($afterFunction);
            }
        } catch
        (\Exception $e) {
            throw new HttpException(400, 'Error with Database ' . $e->getMessage());
        }

        return $object;
    }

    /**
     *
     * Обновление информации об объекте
     *
     * @param Array    $requestArray
     * @param integer  $keyValue
     * @param callback $beforeFunction
     * @param callback $afterFunction
     *
     * @return Object
     */
    public function megaUpdateObject($requestArray, $keyValue, $beforeFunction = null, $afterFunction = null)
    {

        $em           = $this->getDoctrine()->getManager();
        $repository   = $this->getObjectRepository();
        $findFunction = 'findOneBy' . ucfirst($this->objectKey);
        $object       = $repository->$findFunction($keyValue);

        if (!isset($object)) {
            throw new HttpException(404, 'No object this key (' . $keyValue . ').');
        }

        if (!is_null($beforeFunction)) {
            call_user_func($beforeFunction, $object);
        }

        $object = $this->arrayToObject($requestArray, $object);

        try {
            $em->flush();
            if (!is_null($afterFunction)) {
                call_user_func($afterFunction);
            }
        } catch
        (\Exception $e) {
            throw new HttpException(40, 'Error with Database ' . $e->getMessage());
        }

        return $object;
    }

    /**
     *
     * Заполнение объекта из массива
     *
     * @param Array       $requestArray
     * @param bool|Object $object
     *
     * @return Object
     */
    public function arrayToObject($requestArray, $object = false)
    {
        if (!is_array($requestArray)) {
            throw new HttpException(400, 'Error call method');
        }

        $em         = $this->getDoctrine()->getManager();
        $reflect    = new \ReflectionClass($this->objectClass);
        $namespace  = $reflect->getNamespaceName();
        $properties = $reflect->getProperties();

        if (!$object)
            $object = new $this->objectClass();
        //устанавливаем значения
        foreach ($properties as $prop) {
            $prop_name = $this->from_camel_case($prop->getName());
            $value     = isset($requestArray[$prop_name]) ? $requestArray[$prop_name] : null;
            $prop->setAccessible(true);
            if (preg_match('/@var\s+([^\s]+)/', $prop->getDocComment(), $matches)) {
                list(, $type) = $matches;

                if (class_exists($namespace . "\\" . $type)) {
                    $type = $namespace . "\\" . $type;
                }

                //если свойство объекта является объектом, то проверяем его существование
                if (class_exists($type) && !is_null($value) && !is_object($value)) {
                    $repository   = $em->getRepository($type);
                    $findFunction = 'findOneById';
                    $subObject    = $repository->$findFunction($value);
                    if (!$subObject) {
                        throw new HttpException(400, 'No object (' . $type . ').');
                    }

                    $prop->setValue($object, $subObject);
                } else if(!is_null($value)) {
                    if($type == '/DateTime') {
                        $value = new \DateTime($value);
                    }
                    $prop->setValue($object, $value);
                }
            }
        }
        //запуск валидатора
        $this->throwErrorIfNotValid($object);
        $em->persist($object);

        return $object;
    }
}