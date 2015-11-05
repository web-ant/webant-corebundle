<?php
/**
 * Created by PhpStorm.
 * User: u.kovalev
 * Date: 15.10.15
 * Time: 19:15
 */
namespace WebAnt\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * QueryLogs
 *
 * @ORM\Table(name="query_logs")
 * @ORM\Entity
 */
class QueryLogs
{

    public function __construct()
    {
        $this->dateCreate = new \DateTime();
    }

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \WebAnt\UserBundle\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="WebAnt\UserBundle\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_user", referencedColumnName="id", onDelete="SET NULL")
     * })
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="action", type="string", nullable=false)
     */
    private $action;

    /**
     * @var string
     *
     * @ORM\Column(name="controller", type="string", nullable=false)
     */
    private $controller;

    /**
     * @var integer
     *
     * @ORM\Column(name="http_code_response", type="integer", nullable=false)
     */
    private $httpCodeResponse;

    /**
     * @var string
     *
     * @ORM\Column(name="method", type="string", nullable=false)
     */
    private $method;

    /**
     * @var integer
     *
     * @ORM\Column(name="request_time", type="integer", nullable=false)
     */
    private $requestTime;

    /**
     * @var /DateTime
     *
     * @ORM\Column(name="date_create", type="datetime")
     */
    private $dateCreate;

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param mixed $dateCreate
     */
    public function setDateCreate($dateCreate)
    {
        $this->dateCreate = $dateCreate;
    }

    /**
     * @return mixed
     */
    public function getDateCreate()
    {
        return $this->dateCreate;
    }

    /**
     * @param int $httpCodeResponse
     */
    public function setHttpCodeResponse($httpCodeResponse)
    {
        $this->httpCodeResponse = $httpCodeResponse;
    }

    /**
     * @return int
     */
    public function getHttpCodeResponse()
    {
        return $this->httpCodeResponse;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param int $object
     */
    public function setObject($object)
    {
        $this->object = $object;
    }

    /**
     * @return int
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param \WebAnt\UserBundle\Entity\User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return \WebAnt\UserBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param int $requestTime
     */
    public function setRequestTime($requestTime)
    {
        $this->requestTime = $requestTime;
    }

    /**
     * @return int
     */
    public function getResponse()
    {
        return $this->response;
    }
}