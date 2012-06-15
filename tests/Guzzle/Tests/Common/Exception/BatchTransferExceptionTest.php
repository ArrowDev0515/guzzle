<?php

namespace Guzzle\Tests\Common\Exception;

use Guzzle\Common\Exception\BatchTransferException;

class BatchTransferExceptionTest extends \Guzzle\Tests\GuzzleTestCase
{
    public function testContainsBatch()
    {
        $e = new \Exception('Baz!');
        $t = $this->getMock('Guzzle\Common\Batch\BatchTransferInterface');
        $d = $this->getMock('Guzzle\Common\Batch\BatchDivisorInterface');
        $transferException = new BatchTransferException(array('foo'), $e, $t, $d);
        $this->assertEquals(array('foo'), $transferException->getBatch());
        $this->assertSame($t, $transferException->getTransferStrategy());
        $this->assertSame($d, $transferException->getDivisorStrategy());
        $this->assertSame($e, $transferException->getPrevious());
    }
}
