<?php
/**
 * Created by PhpStorm.
 * User: juster
 * Date: 1/24/15
 * Time: 12:28 AM
 */

namespace WebAnt\ClientBundle\Tests;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Nelmio\Alice\Fixtures;

abstract class RestWebTestCase extends WebTestCase
{

    /**
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;

    /**
     * @var
     */
    protected $firstEntity;

    /**
     * @var array
     */
    protected $entityCollection = [];

    /**
     * @var array
     */
    protected $entityValue;

    /**
     * @var string Which repository to load, overriden by derived class
     */
    protected $className;

    /**
     * @var array Alice fixture files to load on setup
     */
    protected $fixtureFiles = [];

    public function setUp()
    {
        $this->client           = static::createClient();
        $this->entityCollection = $this->loadFixtureFiles($this->getFixtures());


        foreach ($this->entityCollection as $obj) {
            $this->entityValue[get_class($obj)][] = $obj;
        }

        if(!empty($this->className) && !empty($this->entityValue[$this->className])){
            $this->firstEntity = $this->entityValue[$this->className][0];
        }

    }

    public function getFixtures()
    {
        return $this->fixtureFiles;
    }

    protected function assertJsonResponse($response, $statusCode = 200)
    {
        $this->assertEquals(
            $statusCode, $response->getStatusCode(),
            $response->getContent()
        );
        $this->assertTrue(
            $response->headers->contains('Content-Type', 'application/json'),
            $response->headers
        );
    }

    /**
     * Стандартный тест проверки rest api
     *
     * @param       $url
     * @param       $method
     * @param array $param
     * @param int   $code
     * @param null  $obj
     *
     * @return mixed
     */
    protected function JsonRequestTest($url, $method, $param = [], $code = 200, $obj = null)
    {
        $route = $this->getUrl($url, $param);

        if (is_null($obj)) {
            $this->client->request($method, $route);

        } else {
            $this->client->request($method, $route, [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($obj));
        }
        $response = $this->client->getResponse();
        $this->assertJsonResponse($response, $code);

        $content = $response->getContent();
        $decoded = json_decode($content);

        return $decoded;
    }

    /**
     * @param string $url   - URL для get запроса
     * @param array  $param - Параметры запроса
     * @param int    $code  - Код ответа
     *
     * @return mixed
     */
    protected function getTest($url, $param = [], $code = 200)
    {
        return $this->JsonRequestTest($url, "GET", $param, $code);
    }

    protected function delTest($url, $param = [], $code = 200)
    {
        return $this->JsonRequestTest($url, "DELETE", $param, $code);
    }

    protected function postTest($url, $obj, $param = [], $code = 200)
    {
        return $this->JsonRequestTest($url, "POST", $param, $code, $obj);
    }

    protected function putTest($url, $obj, $param = [], $code = 200)
    {
        return $this->JsonRequestTest($url, "PUT", $param, $code, $obj);
    }
} 