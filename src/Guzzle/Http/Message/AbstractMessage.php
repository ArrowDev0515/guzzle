<?php

namespace Guzzle\Http\Message;

use Guzzle\Common\Collection;

/**
 * HTTP messages consist of request messages that request data from a server,
 * and response messages that carry back data from the server to the client.
 */
abstract class AbstractMessage implements MessageInterface
{
    /**
     * @var Collection Collection of HTTP headers
     */
    protected $headers;

    /**
     * @var Collection Custom message parameters that are extendable by plugins
     */
    protected $params;

    /**
     * @var array Cache-Control directive information
     */
    private $cacheControl = array();

    /*
     * @var string HTTP protocol version of the message
     */
    protected $protocolVersion = '1.1';

    /**
     * Get application and plugin specific parameters set on the message.  The
     * return object is a reference to the internal object.
     *
     * @return Collection
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Add and merge in an array of HTTP headers.
     *
     * @param array $headers Associative array of header data.
     *
     * @return AbstractMessage
     */
    public function addHeaders(array $headers)
    {
        // Special handling for case-insensitive keys
        foreach ($headers as $key => $value) {
            $current = $this->headers->get($key);
            if (null === $current) {
                // Simply add the headers=
                $this->headers->set($key, $value);
            } else if (is_array($current)) {
                // Merge in sub arrays as needed
                $current[] = $value;
                $this->headers->set($key, $current);
            } else {
                // use the default add functionality
                $this->headers->add($key, $value);
            }
        }

        $this->changedHeader('set', array_keys($headers));

        return $this;
    }

    /**
     * Retrieve an HTTP header by name
     *
     * @param string $header Header to retrieve.
     * @param mixed $default (optional) If the header is not found, the passed
     *      $default value will be returned
     * @param int $match (optional) Match mode:
     *     0 - Exact match
     *     1 - Case insensitive match
     *     2 - Regular expression match
     *
     * @return string|array|null Returns the matching HTTP header value or NULL if the
     *     header is not found. If multiple headers are present for the header, then
     *     an associative array is returned
     */
    public function getHeader($header, $default = null, $match = Collection::MATCH_IGNORE_CASE)
    {
        $headers = $this->headers->getAll(array($header), $match);
        if (!$headers) {
            return $default;
        } else if (count($headers) > 1) {
            return $headers;
        } else {
            return end($headers);
        }
    }

    /**
     * Get all or all matching headers.
     *
     * @param array $names (optional) Pass an array of header names to retrieve
     *      only a particular subset of headers.
     * @param int $match (optional) Match mode
     *
     * @see AbstractMessage::getHeader
     * @return Collection Returns a collection of all headers if no $headers
     *      array is specified, or a Collection of only the headers matching
     *      the headers in the $headers array.
     */
    public function getHeaders(array $headers = null, $match = Collection::MATCH_IGNORE_CASE)
    {
        if (!$headers) {
            return clone $this->headers;
        } else {
            return new Collection($this->headers->getAll($headers, $match));
        }
    }

    /**
     * Get a tokenized header as a Collection
     *
     * @param string $header Header to retrieve
     * @param string $token (optional) Token separator
     * @param int $match (optional) Match mode
     *
     * @return Collection|null
     */
    public function getTokenizedHeader($header, $token = ';', $match = Collection::MATCH_IGNORE_CASE)
    {
        $value = $this->getHeader($header, null, $match);
        if (!$value) {
            return null;
        }

        $data = new Collection();
        foreach ((array) $value as $singleValue) {
            foreach (explode($token, $singleValue) as $kvp) {
                $parts = explode('=', $kvp, 2);
                if (!isset($parts[1])) {
                    $data[count($data)] = trim($parts[0]);
                } else {
                    $data->add(trim($parts[0]), trim($parts[1]));
                }
            }
        }

        return $data->map(function($key, $value) {
            return is_array($value) ? array_unique($value) : $value;
        });
    }

    /**
     * Set a tokenized header on the request that implodes a Collection of data
     * into a string separated by a token
     *
     * @param string $header Header to set
     * @param array|Collection $data Header data
     * @param string $token (optional) Token delimiter
     *
     * @return AbstractMessage
     * @throws InvalidArgumentException if data is not an array or Collection
     */
    public function setTokenizedHeader($header, $data, $token = ';')
    {
        if (!($data instanceof Collection) && !is_array($data)) {
            throw new \InvalidArgumentException('Data must be a Collection or array');
        }

        $values = array();
        foreach ($data as $key => $value) {
            foreach ((array) $value as $v) {
                $values[] = is_int($key) ? $v : $key . '=' . $v;
            }
        }

        return $this->setHeader($header, implode($token, $values));
    }

    /**
     * Check if the specified header is present.
     *
     * @param string $header The header to check.
     * @param int $match (optional) Match mode
     *
     * @see AbstractMessage::getHeader
     * @return bool|mixed Returns TRUE or FALSE if the header is present
     */
    public function hasHeader($header, $match = Collection::MATCH_IGNORE_CASE)
    {
        return false !== $this->headers->hasKey($header, $match);
    }

    /**
     * Remove a specific HTTP header.
     *
     * @param string $header HTTP header to remove.
     * @param int $match (optional) Bitwise match setting
     *
     * @see AbstractMessage::getHeader
     * @return AbstractMessage
     */
    public function removeHeader($header, $match = Collection::MATCH_IGNORE_CASE)
    {
        $this->headers->remove($header, $match);
        $this->changedHeader('remove', $header);

        return $this;
    }

    /**
     * Set an HTTP header
     *
     * @param string $header Name of the header to set.
     * @param mixed $value Value to set.
     *
     * @return AbstractMessage
     */
    public function setHeader($header, $value)
    {
        // Remove any existing header
        $this->removeHeader($header);
        $this->headers->set($header, $value);
        $this->changedHeader('set', $header);

        return $this;
    }

    /**
     * Overwrite all HTTP headers with the supplied array of headers
     *
     * @param array $headers Associative array of header data.
     *
     * @return AbstractMessage
     */
    public function setHeaders(array $headers)
    {
        $this->changedHeader('set', $this->getHeaders()->getKeys());
        $this->headers->replace($headers);

        return $this;
    }

    /**
     * Get a Cache-Control directive from the message
     *
     * @param string $directive Directive to retrieve
     *
     * @return null|string
     */
    public function getCacheControlDirective($directive)
    {
        return isset($this->cacheControl[$directive]) ? $this->cacheControl[$directive] : null;
    }

    /**
     * Check if the message has a Cache-Control directive
     *
     * @param string $directive Directive to check
     *
     * @return bool
     */
    public function hasCacheControlDirective($directive)
    {
        return isset($this->cacheControl[$directive]);
    }

    /**
     * Add a Cache-Control directive on the message
     *
     * @param string $directive Directive to set
     * @param bool|string $value (optional) Value to set
     *
     * @return AbstractMessage
     */
    public function addCacheControlDirective($directive, $value = true)
    {
        $this->cacheControl[$directive] = $value;
        $this->rebuildCacheControlDirective();

        return $this;
    }

    /**
     * Remove a Cache-Control directive from the message
     *
     * @param string $directive Directive to remove
     *
     * @return AbstractMessage
     */
    public function removeCacheControlDirective($directive)
    {
        if (array_key_exists($directive, $this->cacheControl)) {
            unset($this->cacheControl[$directive]);
            $this->rebuildCacheControlDirective();
        }

        return $this;
    }

    /**
     * Check to see if the modified headers need to reset any of the managed
     * headers like cache-control
     *
     * @param string $action One of set or remove
     * @param string|array $keyOrArray Header or headers that changed
     */
    protected function changedHeader($action, $keyOrArray)
    {
        if (in_array('Cache-Control', (array) $keyOrArray)) {
            $this->parseCacheControlDirective();
        }
    }

    /**
     * Parse the Cache-Control HTTP header into an array
     */
    private function parseCacheControlDirective()
    {
        $this->cacheControl = array();
        $tokenized = $this->getTokenizedHeader('Cache-Control', ',') ?: array();
        foreach ($tokenized as $key => $value) {
            if (is_numeric($key)) {
                $this->cacheControl[$value] = true;
            } else {
                $this->cacheControl[$key] = $value;
            }
        }
    }

    /**
     * Rebuild the Cache-Control HTTP header using the user-specified values
     */
    private function rebuildCacheControlDirective()
    {
        $cacheControl = array();
        foreach ($this->cacheControl as $key => $value) {
            if ($value === true) {
                $cacheControl[] = $key;
            } else {
                $cacheControl[] = $key . '=' . $value;
            }
        }

        $this->headers->set('Cache-Control', implode(', ', $cacheControl));
    }
}