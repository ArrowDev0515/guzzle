<?php

namespace Guzzle\Http\Message\Header;

use Guzzle\Common\Collection;
use Guzzle\Common\ToArrayInterface;

/**
 * Provides a case-insensitive collection of headers
 */
class HeaderCollection implements \IteratorAggregate, \Countable, \ArrayAccess, ToArrayInterface
{
    protected $headers;

    public function __construct($headers = array())
    {
        $this->headers = $headers;
    }

    public function __clone()
    {
        foreach ($this->headers as &$header) {
            $header = clone $header;
        }
    }

    /**
     * Clears the header collection
     */
    public function clear()
    {
        $this->headers = array();
    }

    /**
     * Set a header on the collection
     *
     * @param HeaderInterface $header Header to add
     *
     * @return self
     */
    public function add(HeaderInterface $header)
    {
        $this->headers[strtolower($header->getName())] = $header;

        return $this;
    }

    /**
     * Alias of offsetGet
     */
    public function get($key)
    {
        return $this->offsetGet($key);
    }

    /**
     * @inheritdoc}
     */
    public function count()
    {
        return count($this->headers);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->headers[strtolower($offset)]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        $l = strtolower($offset);

        return isset($this->headers[$l]) ? $this->headers[$l] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->add($value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        unset($this->headers[strtolower($offset)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->headers);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        $result = array();
        foreach ($this->headers as $header) {
            $result[$header->getName()] = $header->toArray();
        }

        return $result;
    }
}
