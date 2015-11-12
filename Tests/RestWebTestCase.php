<?php
/**
 * Created by PhpStorm.
 * User: juster
 * Date: 1/24/15
 * Time: 12:28 AM
 */

namespace WebAnt\CoreBundle\Tests\Controller;

use Liip\FunctionalTestBundle\Test\WebTestCase;

abstract class RestWebTestCase extends WebTestCase
{

    /**
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    protected $client;

    /*
     * @var
     */
    protected $limit;

    /**
     * @var ArrayCollection
     */
    protected $entityCollection;

    /**
     *
     */
    protected $entityValue;

    /**
     * @var string Which repository to load, overriden by derived class
     */
    protected $className;

    /**
     * @var array Fixture classes to load on setup
     */
    protected $fixtures = array();

    public function setUp()
    {
        $this->client = static::createClient();

        $this->getContainer()->get('doctrine')->getManager()->getConnection()
             ->query(sprintf('SET FOREIGN_KEY_CHECKS=0'));
        $this->loadFixtures($this->getFixtures());
        $this->getContainer()->get('doctrine')->getManager()->getConnection()
             ->query(sprintf('SET FOREIGN_KEY_CHECKS=1'));

        $lastFixturs = $this->getFixtures()[count($this->getFixtures()) - 1];

        $this->entityCollection = $lastFixturs::$collection;
        foreach ($this->entityCollection->getValues() as $obj) {
            $this->entityValue[get_class($obj)][] = $obj;
        }
        $this->limit = count($this->entityValue[$this->className]);

    }

    public function getFixtures()
    {
        return $this->fixtures;
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
     * @param       $url
     * @param       $method
     * @param array $param
     * @param int   $code
     * @param null  $obj
     *
     * @return mixed
     */
    protected function JsonRequestTest($url, $method, $param = array(), $code = 200, $obj = null)
    {
        $route = $this->getUrl($url, $param);

        if (is_null($obj)) {
            $this->client->request($method, $route);

        } else {
            $this->client->request($method, $route, array(),
                array(),
                array('CONTENT_TYPE' => 'application/json'),
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
    protected function getTest($url, $param = array(), $code = 200)
    {
        return $this->JsonRequestTest($url, "GET", $param, $code);
    }

    protected function delTest($url, $param = array(), $code = 200)
    {
        return $this->JsonRequestTest($url, "DELETE", $param, $code);
    }

    protected function postTest($url, $obj, $param = array(), $code = 200)
    {
        return $this->JsonRequestTest($url, "POST", $param, $code, $obj);
    }

    protected function putTest($url, $obj, $param = array(), $code = 200)
    {
        return $this->JsonRequestTest($url, "PUT", $param, $code, $obj);
    }
} 