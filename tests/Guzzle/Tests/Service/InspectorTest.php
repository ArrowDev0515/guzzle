<?php

namespace Guzzle\Tests\Common;

use Guzzle\Service\Inspector;
use Guzzle\Common\Collection;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;

/**
 * @covers Guzzle\Service\Inspector
 *
 * @guzzle test type="type:object"
 * @guzzle bool_1 default="true" type="boolean"
 * @guzzle bool_2 default="false"
 * @guzzle float type="float"
 * @guzzle int type="integer"
 * @guzzle date type="date"
 * @guzzle timestamp type="time"
 * @guzzle string type="string"
 * @guzzle username required="true" filters="strtolower"
 * @guzzle dynamic default="{{username}}_{{ string }}_{{ does_not_exist }}"
 * @guzzle test_function type="string" filters="Guzzle\Tests\Common\InspectorTest::strtoupper"
 */
class InspectorTest extends \Guzzle\Tests\GuzzleTestCase
{
    public static function strtoupper($string)
    {
        return strtoupper($string);
    }

    /**
     * @covers Guzzle\Service\Inspector::validateClass
     * @expectedException InvalidArgumentException
     */
    public function testValidatesRequiredArgs()
    {
        Inspector::getInstance()->validateClass(__CLASS__, new Collection());
    }

    /**
     * @cover Guzzle\Service\Inspector::__constructor
     */
    public function testRegistersDefaultFilters()
    {
        $inspector = new Inspector();
        $this->assertNotEmpty($inspector->getRegisteredConstraints());
    }

    /**
     * @covers Guzzle\Service\Inspector::getValidator
     */
    public function testCreatesDefaultValidator()
    {
        $inspector = new Inspector();
        $this->assertInstanceOf('Symfony\Component\Validator\Validator', $inspector->getValidator());
    }

    /**
     * @covers Guzzle\Service\Inspector::setValidator
     */
    public function testAllowsValidatorInjection()
    {
        $inspector = new Inspector();
        $default = $inspector->getValidator();
        $validator = new Validator(new ClassMetadataFactory(new StaticMethodLoader()), new ConstraintValidatorFactory());
        $inspector->setValidator($validator);
        $this->assertSame($validator, $inspector->getValidator());
        $this->assertNotSame($default, $inspector->getValidator());
    }

    /**
     * @covers Guzzle\Service\Inspector::prepareConfig
     */
    public function testPreparesConfig()
    {
        $c = Inspector::prepareConfig(array(
            'a' => '123',
            'base_url' => 'http://www.test.com/'
        ), array(
            'a' => 'xyz',
            'b' => 'lol'
        ), array('a'));

        $this->assertInstanceOf('Guzzle\Common\Collection', $c);
        $this->assertEquals(array(
            'a' => '123',
            'b' => 'lol',
            'base_url' => 'http://www.test.com/'
        ), $c->getAll());

        try {
            $c = Inspector::prepareConfig(null, null, array('a'));
            $this->fail('Exception not throw when missing config');
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * @covers Guzzle\Service\Inspector
     */
    public function testAddsDefaultAndInjectsConfigs()
    {
        $col = new Collection(array(
            'username' => 'user',
            'string' => 'test',
            'float' => 1.23
        ));

        Inspector::getInstance()->validateClass(__CLASS__, $col);
        $this->assertEquals(false, $col->get('bool_2'));
        $this->assertEquals('user_test_', $col->get('dynamic'));
        $this->assertEquals(1.23, $col->get('float'));
    }

    /**
     * @covers Guzzle\Service\Inspector::validateClass
     * @expectedException InvalidArgumentException
     */
    public function testValidatesTypeHints()
    {
        Inspector::getInstance()->validateClass(__CLASS__, new Collection(array(
            'test' => 'uh oh',
            'username' => 'test'
        )));
    }

    /**
     * @covers Guzzle\Service\Inspector::validateClass
     * @covers Guzzle\Service\Inspector::validateConfig
     */
    public function testConvertsBooleanDefaults()
    {
        $c = new Collection(array(
            'test' => $this,
            'username' => 'test'
        ));

        Inspector::getInstance()->validateClass(__CLASS__, $c);

        $this->assertTrue($c->get('bool_1'));
        $this->assertFalse($c->get('bool_2'));
    }

    /**
     * @covers Guzzle\Service\Inspector
     */
    public function testInspectsClassArgs()
    {
        $doc = <<<EOT
/**
 * Client for interacting with the Unfuddle webservice
 *
 * @guzzle username required="true" doc="API username" type="string"
 * @guzzle password required="true" doc="API password" type="string"
 * @guzzle subdomain required="true" doc="Unfuddle project subdomain" type="string"
 * @guzzle api_version required="true" default="v1" doc="API version" type="choice:'v1','v2',v3"
 * @guzzle protocol required="true" default="https" doc="HTTP protocol (http or https)" type="string"
 * @guzzle base_url required="true" default="{{ protocol }}://{{ subdomain }}.unfuddle.com/api/{{ api_version }}/" doc="Unfuddle API base URL" type="string"
 * @guzzle class type="type:object"
 */
EOT;

        $params = Inspector::getInstance()->parseDocBlock($doc);

        $this->assertEquals(array(
            'required' => 'true',
            'doc' => 'API username',
            'type' => 'string'
        ), $params['username']);

        $this->assertEquals(array(
            'required' => 'true',
            'default' => 'v1',
            'doc' => 'API version',
            'type' => "choice:'v1','v2',v3"
        ), $params['api_version']);

        $this->assertEquals(array(
            'required' => 'true',
            'default' => 'https',
            'doc' => 'HTTP protocol (http or https)',
            'type' => 'string'
        ), $params['protocol']);

        $this->assertEquals(array(
            'required' => 'true',
            'default' => '{{ protocol }}://{{ subdomain }}.unfuddle.com/api/{{ api_version }}/',
            'doc' => 'Unfuddle API base URL',
            'type' => 'string'
        ), $params['base_url']);

        $this->assertEquals(array(
            'type' => "type:object"
        ), $params['class']);

        $config = new Collection(array(
            'username' => 'test',
            'password' => 'pass',
            'subdomain' => 'sub',
            'api_version' => 'v2'
        ));

        Inspector::getInstance()->validateConfig($params, $config);

        // make sure the configs were injected
        $this->assertEquals('https://sub.unfuddle.com/api/v2/', $config->get('base_url'));

        try {
            Inspector::getInstance()->validateConfig($params, new Collection(array(
                'base_url' => '',
                'username' => '',
                'password' => '',
                'class' => '123',
                'api_version' => 'v10'
            )));
            $this->fail('Expected exception not thrown when params are invalid');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals("Validation errors: Requires that the username argument be supplied.  (API username).
Requires that the password argument be supplied.  (API password).
Requires that the subdomain argument be supplied.  (Unfuddle project subdomain).
The value you selected is not a valid choice
This value should be of type object", $e->getMessage());
        }
    }

    /**
     * @covers Guzzle\Service\Inspector::registerConstraint
     * @covers Guzzle\Service\Inspector::getConstraint
     * @covers Guzzle\Service\Inspector::getRegisteredConstraints
     */
    public function testRegistersCustomConstraints()
    {
        $constraintClass = 'Symfony\\Component\\Validator\\Constraints\\Ip';

        Inspector::getInstance()->registerConstraint('mock', $constraintClass);
        Inspector::getInstance()->registerConstraint('mock_2', $constraintClass, array(
           'version' => '4'
        ));

        $this->assertArrayHasKey('mock', Inspector::getInstance()->getRegisteredConstraints());
        $this->assertArrayHasKey('mock_2', Inspector::getInstance()->getRegisteredConstraints());

        $this->assertInstanceOf($constraintClass, Inspector::getInstance()->getConstraint('mock'));
        $this->assertInstanceOf($constraintClass, Inspector::getInstance()->getConstraint('mock_2'));
        $this->assertEquals('4', Inspector::getInstance()->getConstraint('mock_2')->version);

        $validating = new Collection(array(
            'data' => '192.168.16.121',
            'test' => '10.1.1.0'
        ));

        $this->assertTrue(Inspector::getInstance()->validateConfig(array(
            'data' => array(
                'type' => 'mock'
            ),
            'test' => array(
                'type' => 'mock_2'
            )
        ), $validating, false));
    }

    /**
     * @covers Guzzle\Service\Inspector
     * @expectedException InvalidArgumentException
     */
    public function testChecksFilterValidity()
    {
        Inspector::getInstance()->validateConfig(array(
            'data' => array(
                'type' => 'invalid'
            )
        ), new Collection(array(
            'data' => 'false'
        )));
    }

    /**
     * @covers Guzzle\Service\Inspector
     */
    public function testValidatesArgs()
    {
        $config = new Collection(array(
            'data' => 123,
            'min' => 'a',
            'max' => 'aaa'
        ));

        $result = Inspector::getInstance()->validateConfig(array(
            'data' => array(
                'type' => 'string'
            ),
            'min' => array(
                'type' => 'string',
                'min_length' => 2
            ),
            'max' => array(
                'type' => 'string',
                'max_length' => 2
            )
        ), $config, false);

        $this->assertEquals("This value should be of type string
Requires that the min argument be >= 2 characters.
Requires that the max argument be <= 2 characters.", implode("\n", $result));
    }

    /**
     * @covers Guzzle\Service\Inspector::parseDocBlock
     */
    public function testVerifiesGuzzleAnnotations()
    {
        $this->assertEquals(
            array(),
            Inspector::getInstance()->parseDocBlock('testing')
        );
    }

    /**
     * @covers Guzzle\Service\Inspector::validateConfig
     */
    public function testRunsValuesThroughFilters()
    {
        $data = new Collection(array(
            'username' => 'TEST',
            'test_function'   => 'foo'
        ));
        Inspector::getInstance()->validateClass(__CLASS__, $data);
        $this->assertEquals('test', $data->get('username'));
        $this->assertEquals('FOO', $data->get('test_function'));
    }
}