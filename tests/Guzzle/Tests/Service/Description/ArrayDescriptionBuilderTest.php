<?php

namespace Guzzle\Tests\Service\Description;

use Guzzle\Service\Inspector;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Service\Description\ArrayDescriptionBuilder;

class ArrayDescriptionBuilderTest extends \Guzzle\Tests\GuzzleTestCase
{
    /**
     * @covers Guzzle\Service\Description\ServiceDescription::factory
     * @covers Guzzle\Service\Description\ArrayDescriptionBuilder::build
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
        $this->assertEquals('/test', $c->getUri());
        $this->assertEquals('GET', $c->getMethod());
        $params = $c->getParams();
        $param = $params['test'];
        $this->assertEquals('string', $param['type']);
        $this->assertTrue($param['required']);
    }

    /**
     * @covers Guzzle\Service\Description\ServiceDescription::factory
     * @covers Guzzle\Service\Description\ArrayDescriptionBuilder::build
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
     * @covers Guzzle\Service\Description\ArrayDescriptionBuilder::build
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
     * @covers Guzzle\Service\Description\ArrayDescriptionBuilder::build
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

}
