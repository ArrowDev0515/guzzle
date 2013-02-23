<?php

namespace Guzzle\Service;

use Guzzle\Common\Collection;
use Guzzle\Common\Exception\InvalidArgumentException;
use Guzzle\Common\Exception\BadMethodCallException;
use Guzzle\Inflection\InflectorInterface;
use Guzzle\Inflection\Inflector;
use Guzzle\Http\Client as HttpClient;
use Guzzle\Http\Exception\MultiTransferException;
use Guzzle\Service\Exception\CommandTransferException;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Service\Command\CommandInterface;
use Guzzle\Service\Command\Factory\CompositeFactory;
use Guzzle\Service\Command\Factory\FactoryInterface as CommandFactoryInterface;
use Guzzle\Service\Resource\ResourceIteratorClassFactory;
use Guzzle\Service\Resource\ResourceIteratorFactoryInterface;
use Guzzle\Service\Description\ServiceDescriptionInterface;

/**
 * Client object for executing commands on a web service.
 */
class Client extends HttpClient implements ClientInterface
{
    const COMMAND_PARAMS = 'command.params';

    /**
     * @var ServiceDescriptionInterface Description of the service and possible commands
     */
    protected $serviceDescription;

    /**
     * @var bool Whether or not magic methods are enabled
     */
    protected $enableMagicMethods = true;

    /**
     * @var CommandFactoryInterface
     */
    protected $commandFactory;

    /**
     * @var ResourceIteratorFactoryInterface
     */
    protected $resourceIteratorFactory;

    /**
     * @var InflectorInterface Inflector associated with the service/client
     */
    protected $inflector;

    /**
     * Basic factory method to create a new client. Extend this method in subclasses to build more complex clients.
     *
     * @param array|Collection $config Configuration data
     *
     * @return Client
     */
    public static function factory($config = array())
    {
        return new static(isset($config['base_url']) ? $config['base_url'] : null, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getAllEvents()
    {
        return array_merge(HttpClient::getAllEvents(), array(
            'client.command.create',
            'command.before_prepare',
            'command.before_send',
            'command.after_send'
        ));
    }

    /**
     * Magic method used to retrieve a command. Magic methods must be enabled on the client to use this functionality.
     *
     * @param string $method Name of the command object to instantiate
     * @param array  $args   Arguments to pass to the command
     *
     * @return mixed Returns the result of the command
     * @throws BadMethodCallException when a command is not found or magic methods are disabled
     */
    public function __call($method, $args = null)
    {
        if (!$this->enableMagicMethods) {
            throw new BadMethodCallException("Missing method {$method}. This client has not enabled magic methods.");
        }

        return $this->getCommand($method, isset($args[0]) ? $args[0] : array())->getResult();
    }

    /**
     * Specify whether or not magic methods are enabled (disabled by default)
     *
     * @param bool $isEnabled Set to true to enable magic methods or false to disable them
     *
     * @return self
     */
    public function enableMagicMethods($isEnabled)
    {
        $this->enableMagicMethods = $isEnabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommand($name, array $args = array())
    {
        if (!($command = $this->getCommandFactory()->factory($name, $args))) {
            throw new InvalidArgumentException("Command was not found matching {$name}");
        }

        $command->setClient($this);

        // Add global client options to the command
        if ($command instanceof Collection) {
            if ($options = $this->getConfig(self::COMMAND_PARAMS)) {
                foreach ($options as $key => $value) {
                    if (!$command->hasKey($key)) {
                        $command->set($key, $value);
                    }
                }
            }
        }

        $this->dispatch('client.command.create', array(
            'client'  => $this,
            'command' => $command
        ));

        return $command;
    }

    /**
     * {@inheritdoc}
     */
    public function setCommandFactory(CommandFactoryInterface $factory)
    {
        $this->commandFactory = $factory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setResourceIteratorFactory(ResourceIteratorFactoryInterface $factory)
    {
        $this->resourceIteratorFactory = $factory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator($command, array $commandOptions = null, array $iteratorOptions = array())
    {
        if (!($command instanceof CommandInterface)) {
            $command = $this->getCommand($command, $commandOptions ?: array());
        }

        return $this->getResourceIteratorFactory()->build($command, $iteratorOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($command)
    {
        if ($command instanceof CommandInterface) {
            $command = array($command);
            $singleCommand = true;
        } elseif (is_array($command)) {
            $singleCommand = false;
        } else {
            throw new InvalidArgumentException('Command must be a command or array of commands');
        }

        $failureException = null;
        $requests = array();
        $successful = new \SplObjectStorage();

        foreach ($command as $c) {
            $c->setClient($this);
            // Set the state to new if the command was previously executed
            $request = $c->prepare()->setState(RequestInterface::STATE_NEW);
            $successful[$request] = $c;
            $requests[] = $request;
            $this->dispatch('command.before_send', array('command' => $c));
        }

        try {
            $this->send($requests);
        } catch (MultiTransferException $failureException) {
            $failures = new \SplObjectStorage();
            // Remove failed requests from the successful requests array and add to the failures array
            foreach ($failureException->getFailedRequests() as $request) {
                if (isset($successful[$request])) {
                    $failures[$request] = $successful[$request];
                    unset($successful[$request]);
                }
            }
        }

        foreach ($successful as $success) {
            $this->dispatch('command.after_send', array('command' => $successful[$success]));
        }

        // Return the response or throw an exception
        if (!$failureException) {
            return $singleCommand ? end($command)->getResult() : $command;
        } elseif ($singleCommand) {
            // If only sending a single request, then don't use a CommandTransferException
            throw $failureException->getFirst();
        } else {
            // Throw a CommandTransferException using the successful and failed commands
            $e = CommandTransferException::fromMultiTransferException($failureException);
            foreach ($failures as $failure) {
                $e->addFailedCommand($failures[$failure]);
            }
            foreach ($successful as $success) {
                $e->addSuccessfulCommand($successful[$success]);
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription(ServiceDescriptionInterface $service)
    {
        $this->serviceDescription = $service;

        // If a baseUrl was set on the description, then update the client
        if ($baseUrl = $service->getBaseUrl()) {
            $this->setBaseUrl($baseUrl);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->serviceDescription;
    }

    /**
     * {@inheritdoc}
     */
    public function setInflector(InflectorInterface $inflector)
    {
        $this->inflector = $inflector;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getInflector()
    {
        if (!$this->inflector) {
            $this->inflector = Inflector::getDefault();
        }

        return $this->inflector;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResourceIteratorFactory()
    {
        if (!$this->resourceIteratorFactory) {
            // Build the default resource iterator factory if one is not set
            $clientClass = get_class($this);
            $prefix = substr($clientClass, 0, strrpos($clientClass, '\\'));
            $this->resourceIteratorFactory = new ResourceIteratorClassFactory(array(
                "{$prefix}\\Iterator",
                "{$prefix}\\Model"
            ));
        }

        return $this->resourceIteratorFactory;
    }

    /**
     * Get the command factory associated with the client
     *
     * @return CommandFactoryInterface
     */
    protected function getCommandFactory()
    {
        if (!$this->commandFactory) {
            $this->commandFactory = CompositeFactory::getDefaultChain($this);
        }

        return $this->commandFactory;
    }
}
