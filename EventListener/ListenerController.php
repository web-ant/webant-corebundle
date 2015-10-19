<?php
/**
 * Created by PhpStorm.
 * User: u.kovalev
 * Date: 19.10.15
 * Time: 11:50
 */

namespace WebAnt\CoreBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use WebAnt\CoreBundle\Entity\QueryLogs;
use Doctrine\ORM\EntityManager;

class ListenerController
{
    protected $token_storage;

    public function __construct(TokenStorageInterface $token_storage, EntityManager $em)
    {
        $this->token_storage = $token_storage;
        $this->em = $em;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $this->addLogs($event,false);
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $this->addLogs($event);
        $event->setResponse($event->getResponse());
    }

    private function addLogs($event,$status = true) {

        $queryLogs = new QueryLogs();

        $request = $event->getRequest();
        $controller = explode('::',$request->attributes->get('_controller'));

        $user = $this->token_storage->getToken()->getUser();
        if($user != "anon."){
            $queryLogs->setUser($user);
        }


        $queryLogs->setAction($controller[1]);

        //exception response
        if($status) {
            $datalogs['http_code_response'] = $event->getResponse()->getStatusCode();
        } else {
            $datalogs['http_code_response'] = $event->getException()->getStatusCode();
        }

        $queryLogs->setHttpCodeResponse($datalogs['http_code_response']);
        $queryLogs->setMethod($request->getMethod());

        $queryLogs->setRequestTime($request->server->get('REQUEST_TIME'));

        $this->em->persist($queryLogs);
        $this->em->flush();
        dump($queryLogs);exit;
    }
}