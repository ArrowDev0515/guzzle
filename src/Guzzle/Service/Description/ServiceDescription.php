<?php

namespace Guzzle\Service\Description;

use Guzzle\Common\NullObject;
use Guzzle\Service\Inspector;

/**
 * A ServiceDescription stores service information based on a service document
 */
class ServiceDescription
{
    const DEFAULT_COMMAND_CLASS = 'Guzzle\\Service\\Command\\ClosureCommand';

    /**
     * @var array Array of ApiCommand objects
     */
    protected $commands = array();

    /**
     * @var CommandFactoryInterface
     */
    protected $commandFactory;

    /**
     * Create a ServiceDescription based on an array
     *
     * @param array $data Description data
     *
     * @return ServiceDescription
     */
    public static function factory(array $data)
    {
        if (!empty($data['types'])) {
            foreach ($data['types'] as $name => $type) {
                $default = array();
                if (!isset($type['class'])) {
                    throw new \RuntimeException('Custom types require a class attribute');
                }
                foreach ($type as $key => $value) {
                    if ($key != 'name' && $key != 'class') {
                        $default[$key] = $value;
                    }
                }
                Inspector::getInstance()->registerConstraint($name, $type['class'], $default);
            }
        }

        $commands = array();
        if (!empty($data['commands'])) {
            foreach ($data['commands'] as $name => $command) {
                $name = $command['name'] = isset($command['name']) ? $command['name'] : $name;
                // Extend other commands
                if (!empty($command['extends'])) {
                    if (empty($commands[$command['extends']])) {
                        throw new \RuntimeException($name . ' extends missing command ' . $command['extends']);
                    }
                    $params = array_merge($commands[$command['extends']]->getParams(), !empty($command['params']) ? $command['params'] : array());
                    $command = array_merge($commands[$command['extends']]->getData(), $command);
                    $command['params'] = $params;
                }
                // Use the default class
                $command['class'] = isset($command['class']) ? str_replace('.', '\\', $command['class']) : self::DEFAULT_COMMAND_CLASS;
                $commands[$name] = new ApiCommand($command);
            }
        }

        return new self($commands);
    }

    /**
     * Create a new ServiceDescription
     *
     * @param array $commands (optional) Array of {@see ApiCommand} objects
     * @param CommandFactoryInterface (optional) Command factory to build
     *      dynamic commands.  Uses the DynamicCommandFactory by default.
     */
    public function __construct(array $commands = array(), CommandFactoryInterface $commandFactory = null)
    {
        $this->commands = $commands;
        $this->commandFactory = $commandFactory ?: new DynamicCommandFactory();
    }

    /**
     * Get the API commands of the service
     *
     * @return array Returns an array of ApiCommand objects
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Check if the service has a command by name
     *
     * @param string $name Name of the command to check
     *
     * @return bool
     */
    public function hasCommand($name)
    {
        return array_key_exists($name, $this->commands);
    }

    /**
     * Get an API command by name
     *
     * @param string $name Name of the command
     *
     * @return ApiCommand|NullObject Returns an ApiCommand on success or a
     *      NullObject on error
     */
    public function getCommand($name)
    {
        return $this->hasCommand($name) ? $this->commands[$name] : new NullObject();
    }

    /**
     * Create a webservice command based on the service document
     *
     * @param string $command Name of the command to retrieve
     * @param array $args (optional) Arguments to pass to the command
     *
     * @return Command\CommandInterface
     * @throws InvalidArgumentException if the command was not found in the service doc
     */
    public function createCommand($name, array $args = array())
    {
        if (!$this->hasCommand($name)) {
            throw new \InvalidArgumentException($name . ' command not found');
        }

        return $this->commandFactory->createCommand($this->getCommand($name), $args);
    }
}