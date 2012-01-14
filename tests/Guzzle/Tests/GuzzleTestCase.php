<?php

namespace Guzzle\Tests;

use Guzzle\Common\HasDispatcherInterface;
use Guzzle\Common\Log\Adapter\ZendLogAdapter;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Plugin\MockPlugin;
use Guzzle\Service\Client;
use Guzzle\Service\ServiceBuilder;
use Guzzle\Tests\Mock\MockObserver;
use Guzzle\Tests\Http\Server;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base testcase class for all Guzzle testcases.
 */
abstract class GuzzleTestCase extends \PHPUnit_Framework_TestCase
{
    protected static $mockBasePath;
    public static $serviceBuilder;
    public static $server;

    private $requests = array();
    public $mockObserver;

    /**
     * Get the global server object used throughout the unit tests of Guzzle
     *
     * @return Server
     */
    public function getServer()
    {
        if (!self::$server) {
            try {
            self::$server = new Server();
            if (self::$server->isRunning()) {
                self::$server->flush();
            } else {
                self::$server->start();
            }
            } catch (\Exception $e) {
                fwrite(STDERR, $e->getMessage());
            }
        }

        return self::$server;
    }

    /**
     * Set the service builder to use for tests
     *
     * @param ServiceBuilder $builder Service builder
     */
    public static function setServiceBuilder(ServiceBuilder $builder)
    {
        self::$serviceBuilder = $builder;
    }

    /**
     * Get a service builder object that can be used throughout the service tests
     *
     * @return ServiceBuilder
     */
    public function getServiceBuilder()
    {
        if (!self::$serviceBuilder) {
            throw new RuntimeException('No service builder has been set via setServiceBuilder()');
        }

        return self::$serviceBuilder;
    }
    
    /**
     * Check if an event dispatcher has a subscriber
     * 
     * @param HasDispatcherInterface $dispatcher
     * @param EventSubscriberInterface $subscriber
     * 
     * @return bool
     */
    protected function hasSubscriber(HasDispatcherInterface $dispatcher, EventSubscriberInterface $subscriber)
    {
        $class = get_class($subscriber);
        $all = array_keys(call_user_func(array($class, 'getSubscribedEvents')));
        
        foreach ($all as $i => $event) {
            foreach ($dispatcher->getEventDispatcher()->getListeners($event) as $e) {
                if ($e[0] === $subscriber) {
                    unset($all[$i]);
                    break;
                }
            }
        }
        
        return count($all) == 0;
    }
    
    /**
     * Get a wildcard observer for an event dispatcher
     * 
     * @param HasEventDispatcherInterface $hasEvent
     * 
     * @return MockObserver 
     */
    public function getWildcardObserver(HasDispatcherInterface $hasDispatcher)
    {
        $class = get_class($hasDispatcher);
        $o = new MockObserver();
        $events = call_user_func(array($class, 'getAllEvents'));
        foreach ($events as $event) {
            $hasDispatcher->getEventDispatcher()->addListener($event, array($o, 'update'));
        }
        
        return $o;
    }
    
    /**
     * Set the mock response base path
     *
     * @param string $path Path to mock response folder
     *
     * @return GuzzleTestCase
     */
    public static function setMockBasePath($path)
    {
        self::$mockBasePath = $path;
    }

    /**
     * Mark a request as being mocked
     *
     * @param RequestInterface $request
     */
    public function addMockedRequest(RequestInterface $request)
    {
        $this->requests[] = $request;

        return $this;
    }

    /**
     * Get all of the mocked requests
     *
     * @return array
     */
    public function getMockedRequests()
    {
        return $this->requests;
    }

    /**
     * Get a mock response for a client by mock file name
     *
     * @param string $path Relative path to the mock response file
     *
     * @return Response
     */
    public function getMockResponse($path)
    {
        return MockPlugin::getMockFile(self::$mockBasePath . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Set a mock response from a mock file on the next client request.
     *
     * This method assumes that mock response files are located under the
     * Command/Mock/ directory of the Service being tested
     * (e.g. Unfuddle/Command/Mock/).  A mock response is added to the next
     * request sent by the client.
     *
     * @param Client $client Client object to modify
     * @param string $paths Path to files within the Mock folder of the service
     */
    public function setMockResponse(Client $client, $paths)
    {
        $this->requests = array();
        $that = $this;
        $mock = new MockPlugin(null, true);
        $mock->getEventManager()->attach(function($subject, $event, $context) use ($that) {
            if ($event == 'mock.request') {
                $that->addMockedRequest($context);
            }
        });

        foreach ((array) $paths as $path) {
            $mock->addResponse($this->getMockResponse($path));
        }
        
        $client->getEventManager()->attach($mock, 9999);
    }

    /**
     * Check if an array of HTTP headers matches another array of HTTP headers
     * while taking * into account as a wildcard for header values
     *
     * @param array $actual Actual HTTP header array
     * @param array $expected Expected HTTP headers (allows wildcard values)
     *
     * @return array|false Returns an array of the differences or FALSE if none
     */
    public function compareHttpHeaders(array $expected, array $actual)
    {
        $differences = array();

        foreach ($actual as $key => $value) {
            if (!isset($expected[$key]) || ($expected[$key] != '*' && $actual[$key] != $expected[$key])) {
                $differences[$key] = $value;
            }
        }

        return empty($differences) ? false : $differences;
    }
}