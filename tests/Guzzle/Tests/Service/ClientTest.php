<?php

namespace Guzzle\Tests\Service;

use Guzzle\Guzzle;
use Guzzle\Common\Collection;
use Guzzle\Common\Log\ClosureLogAdapter;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Curl\CurlMulti;
use Guzzle\Http\Plugin\MockPlugin;
use Guzzle\Service\Description\ApiCommand;
use Guzzle\Service\Client;
use Guzzle\Service\Command\CommandSet;
use Guzzle\Service\Command\CommandInterface;
use Guzzle\Service\Description\XmlDescriptionBuilder;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Tests\Service\Mock\Command\MockCommand;

/**
 * @group server
 */
class ClientTest extends \Guzzle\Tests\GuzzleTestCase
{
    protected $service;
    protected $serviceTest;

    public function setUp()
    {
        $this->serviceTest = new ServiceDescription(array(
            'test_command' => new ApiCommand(array(
                'doc' => 'documentationForCommand',
                'method' => 'DELETE',
                'class' => 'Guzzle\\Tests\\Service\\Mock\\Command\\MockCommand',
                'args' => array(
                    'bucket' => array(
                        'required' => true
                    ),
                    'key' => array(
                        'required' => true
                    )
                )
            ))
        ));

        $this->service = XmlDescriptionBuilder::build(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'TestData' . DIRECTORY_SEPARATOR . 'test_service.xml');
    }

    /**
     * @covers Guzzle\Service\Client::factory
     */
    public function testFactoryCreatesClient()
    {
        $client = Client::factory(array(
            'base_url' => 'http://www.test.com/',
            'test' => '123'
        ));

        $this->assertEquals('http://www.test.com/', $client->getBaseUrl());
        $this->assertEquals('123', $client->getConfig('test'));
    }

    /**
     * @covers Guzzle\Service\Client::getAllEvents
     */
    public function testDescribesEvents()
    {
        $this->assertInternalType('array', Client::getAllEvents());
    }

    /**
     * @covers Guzzle\Service\Client::execute
     */
    public function testExecutesCommands()
    {
        $this->getServer()->flush();
        $this->getServer()->enqueue("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");

        $client = new Client($this->getServer()->getUrl());
        $cmd = new MockCommand();
        $client->execute($cmd);

        $this->assertInstanceOf('Guzzle\\Http\\Message\\Response', $cmd->getResponse());
        $this->assertInstanceOf('Guzzle\\Http\\Message\\Response', $cmd->getResult());
        $this->assertEquals(1, count($this->getServer()->getReceivedRequests(false)));
    }

    /**
     * @covers Guzzle\Service\Client::execute
     */
    public function testExecutesCommandsWithArray()
    {
        $client = new Client('http://www.test.com/');
        $client->getEventDispatcher()->addSubscriber(new MockPlugin(array(
            new \Guzzle\Http\Message\Response(200),
            new \Guzzle\Http\Message\Response(200)
        )));

        // Create a command set and a command
        $set = array(new MockCommand(), new MockCommand());
        $client->execute($set);

        // Make sure it sent
        $this->assertTrue($set[0]->isExecuted());
        $this->assertTrue($set[1]->isExecuted());
    }

    /**
     * @covers Guzzle\Service\Client::execute
     * @expectedException Guzzle\Service\Command\CommandSetException
     */
    public function testThrowsExceptionWhenExecutingMixedClientCommandSets()
    {
        $client = new Client('http://www.test.com/');
        $otherClient = new Client('http://www.test-123.com/');

        // Create a command set and a command
        $set = new CommandSet();
        $cmd = new MockCommand();
        $set->addCommand($cmd);

        // Associate the other client with the command
        $cmd->setClient($otherClient);

        // Send the set with the wrong client, causing an exception
        $client->execute($set);
    }

    /**
     * @covers Guzzle\Service\Client::execute
     * @expectedException InvalidArgumentException
     */
    public function testThrowsExceptionWhenExecutingInvalidCommandSets()
    {
        $client = new Client('http://www.test.com/');
        $client->execute(new \stdClass());
    }

    /**
     * @covers Guzzle\Service\Client::execute
     */
    public function testExecutesCommandSets()
    {
        $client = new Client('http://www.test.com/');
        $client->getEventDispatcher()->addSubscriber(new MockPlugin(array(
            new \Guzzle\Http\Message\Response(200)
        )));

        // Create a command set and a command
        $set = new CommandSet();
        $cmd = new MockCommand();
        $set->addCommand($cmd);
        $this->assertSame($set, $client->execute($set));

        // Make sure it sent
        $this->assertTrue($cmd->isExecuted());
        $this->assertTrue($cmd->isPrepared());
        $this->assertEquals(200, $cmd->getResponse()->getStatusCode());
    }

    /**
     * @covers Guzzle\Service\Client::getCommand
     * @expectedException InvalidArgumentException
     */
    public function testThrowsExceptionWhenNoCommandFactoryIsSetAndGettingCommand()
    {
        $client = new Client($this->getServer()->getUrl());
        $client->getCommand('test');
    }

    /**
     * @covers Guzzle\Service\Client::getCommand
     * @covers Guzzle\Service\Client::getDescription
     * @covers Guzzle\Service\Client::setDescription
     */
    public function testRetrievesCommandsFromConcreteAndService()
    {
        $client = new Mock\MockClient('http://www.example.com/');
        $this->assertSame($client, $client->setDescription($this->serviceTest));
        $this->assertSame($this->serviceTest, $client->getDescription());
        // Creates service commands
        $this->assertInstanceOf('Guzzle\\Tests\\Service\\Mock\\Command\\MockCommand', $client->getCommand('test_command'));
        // Creates concrete commands
        $this->assertInstanceOf('Guzzle\\Tests\\Service\\Mock\\Command\\OtherCommand', $client->getCommand('other_command'));
    }
}