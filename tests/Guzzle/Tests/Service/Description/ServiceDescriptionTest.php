<?php

namespace Guzzle\Tests\Service\Description;

use Guzzle\Common\Collection;
use Guzzle\Service\Inspector;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Service\Description\XmlDescriptionBuilder;
use Guzzle\Service\Description\ApiCommand;

class ServiceDescriptionTest extends \Guzzle\Tests\GuzzleTestCase
{
    /**
     * @covers Guzzle\Service\Description\ServiceDescription::factory
     */
    public function testAllowsDeepNestedInheritence()
    {
        $d = ServiceDescription::factory(array(
            'commands' => array(
                'abstract' => array(
                    'method' => 'GET',
                    'params' => array(
                        'test' => array(
                            'type' => 'string',
                            'required' => true
                        )
                    )
                ),
                'abstract2' => array(
                    'path' => '/test',
                    'extends' => 'abstract'
                ),
                'concrete' => array(
                    'extends' => 'abstract2'
                )
            )
        ));

        $c = $d->getCommand('concrete');
        $this->assertEquals('/test', $c->getPath());
        $this->assertEquals('GET', $c->getMethod());
        $params = $c->getParams();
        $param = $params['test'];
        $this->assertEquals('string', $param['type']);
        $this->assertTrue($param['required']);
    }

    /**
     * @covers Guzzle\Service\Description\ServiceDescription::factory
     * @expectedException RuntimeException
     */
    public function testThrowsExceptionWhenExtendingMissingCommand()
    {
        ServiceDescription::factory(array(
            'commands' => array(
                'concrete' => array(
                    'extends' => 'missing'
                )
            )
        ));
    }

    /**
     * @covers Guzzle\Service\Description\ServiceDescription::factory
     */
    public function testRegistersCustomTypes()
    {
        ServiceDescription::factory(array(
            'types' => array(
                'slug' => array(
                    'class' => 'Symfony\\Component\\Validator\\Constraints\\Regex',
                    'pattern' => '/[0-9a-zA-z_\-]+/'
                )
            )
        ));

        $regex = Inspector::getInstance()->getConstraint('slug');
        $this->assertInstanceOf('Symfony\\Component\\Validator\\Constraints\\Regex', $regex);
        $this->assertEquals('/[0-9a-zA-z_\-]+/', $regex->pattern);
    }

    /**
     * @covers Guzzle\Service\Description\ServiceDescription::factory
     * @expectedException RuntimeException
     * @expectedExceptionMessage Custom types require a class attribute
     */
    public function testCustomTypesRequireClassAttribute()
    {
        ServiceDescription::factory(array(
            'types' => array(
                'slug' => array()
            )
        ));
    }

    /**
     * @covers Guzzle\Service\Description\ServiceDescription
     * @covers Guzzle\Service\Description\ServiceDescription::__construct
     * @covers Guzzle\Service\Description\ServiceDescription::getCommands
     * @covers Guzzle\Service\Description\ServiceDescription::getCommand
     */
    public function testConstructor()
    {
        $service = new ServiceDescription(array(
            'test_command' => new ApiCommand(array(
                'doc' => 'documentationForCommand',
                'method' => 'DELETE',
                'class' => 'Guzzle\\Tests\\Service\\Mock\\Command\\MockCommand',
                'params' => array(
                    'bucket' => array(
                        'required' => true
                    ),
                    'key' => array(
                        'required' => true
                    )
                )
            ))
        ));

        $this->assertEquals(1, count($service->getCommands()));
        $this->assertFalse($service->hasCommand('foobar'));
        $this->assertTrue($service->hasCommand('test_command'));

        $c = $service->createCommand('test_command', array(
            'bucket' => '123',
            'key' => 'abc'
        ));

        $this->assertInstanceOf('Guzzle\\Tests\\Service\\Mock\\Command\\MockCommand', $c);
        $this->assertEquals('123', $c->get('bucket'));
        $this->assertEquals('abc', $c->get('key'));

        try {
            $service->createCommand('foobar', array());
            $this->fail('Expected exception not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('foobar command not found', $e->getMessage());
        }
    }
}