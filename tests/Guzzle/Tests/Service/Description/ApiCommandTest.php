<?php

namespace Guzzle\Tests\Service\Description;

use Guzzle\Common\Collection;
use Guzzle\Service\Description\ApiCommand;
use Guzzle\Service\Description\ServiceDescription;

class ApiCommandTest extends \Guzzle\Tests\GuzzleTestCase
{
    /**
     * @covers Guzzle\Service\Description\ApiCommand
     */
    public function testApiCommandIsDataObject()
    {
        $c = new ApiCommand(array(
            'name' => 'test',
            'doc' => 'doc',
            'method' => 'POST',
            'path' => '/api/v1',
            'params' => array(
                'key' => array(
                    'required' => 'true',
                    'type' => 'string',
                    'max_length' => 10
                ),
                'key_2' => array(
                    'required' => 'true',
                    'type' => 'integer',
                    'default' => 10
                )
           )
        ));

        $this->assertEquals('test', $c->getName());
        $this->assertEquals('doc', $c->getDoc());
        $this->assertEquals('POST', $c->getMethod());
        $this->assertEquals('/api/v1', $c->getPath());
        $this->assertEquals('Guzzle\\Service\\Command\\ClosureCommand', $c->getConcreteClass());
        $this->assertEquals(array(
            'key' => new Collection(array(
                'required' => 'true',
                'type' => 'string',
                'max_length' => 10
            )),
            'key_2' => new Collection(array(
                'required' => 'true',
                'type' => 'integer',
                'default' => 10
            ))
        ), $c->getParams());

        $this->assertEquals(new Collection(array(
            'required' => 'true',
            'type' => 'integer',
            'default' => 10
        )), $c->getParam('key_2'));

        $this->assertNull($c->getParam('afefwef'));
    }

    /**
     * @covers Guzzle\Service\Description\ApiCommand::__construct
     */
    public function testAllowsConcreteCommands()
    {
        $c = new ApiCommand(array(
            'name' => 'test',
            'class' => 'Guzzle\\Service\\Command\ClosureCommand',
            'params' => array(
                'p' => new Collection(array(
                    'name' => 'foo'
                ))
            )
        ));
        $this->assertEquals('Guzzle\\Service\\Command\ClosureCommand', $c->getConcreteClass());
    }

    /**
     * @covers Guzzle\Service\Description\ApiCommand::getData
     */
    public function testConvertsToArray()
    {
        $data = array(
            'name' => 'test',
            'class'     => 'Guzzle\\Service\\Command\ClosureCommand',
            'doc'       => 'test',
            'method'    => 'PUT',
            'path'      => '/',
            'params'    => array(
                'p' => new Collection(array(
                    'name' => 'foo'
                ))
            )
        );
        $c = new ApiCommand($data);
        $this->assertEquals($data, $c->getData());
    }
}