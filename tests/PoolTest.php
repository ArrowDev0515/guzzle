<?php
namespace GuzzleHttp\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Pool;
use GuzzleHttp\Ring\Client\MockAdapter;
use GuzzleHttp\Ring\Future;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;

class PoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesIterable()
    {
        new Pool(new Client(), 'foo');
    }

    public function testCanControlPoolSizeAndClient()
    {
        $c = new Client();
        $p = new Pool($c, [], ['pool_size' => 10]);
        $this->assertSame($c, $this->readAttribute($p, 'client'));
        $this->assertEquals(10, $this->readAttribute($p, 'poolSize'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testValidatesEachElement()
    {
        $c = new Client();
        $requests = ['foo'];
        $p = new Pool($c, new \ArrayIterator($requests));
        $p->deref();
    }

    public function testSendsAndRealizesFuture()
    {
        $c = $this->getClient();
        $p = new Pool($c, [$c->createRequest('GET', 'http://foo.com')]);
        $this->assertTrue($p->deref());
        $this->assertFalse($p->deref());
        $this->assertTrue($p->realized());
        $this->assertFalse($p->cancel());
        $this->assertFalse($p->cancelled());
    }

    public function testHasSendFunction()
    {
        $c = $this->getClient();
        Pool::send($c, [$c->createRequest('GET', 'http://foo.com')]);
    }

    public function testSendsManyRequestsInCappedPool()
    {
        $c = $this->getClient();
        $p = new Pool($c, [$c->createRequest('GET', 'http://foo.com')]);
        $this->assertTrue($p->deref());
        $this->assertFalse($p->deref());
        $this->assertTrue($p->realized());
        $this->assertFalse($p->cancel());
        $this->assertFalse($p->cancelled());
    }

    public function testSendsRequestsThatHaveNotBeenRealized()
    {
        $c = $this->getClient();
        $p = new Pool($c, [$c->createRequest('GET', 'http://foo.com')]);
        $this->assertTrue($p->deref());
        $this->assertFalse($p->deref());
        $this->assertTrue($p->realized());
        $this->assertFalse($p->cancel());
        $this->assertFalse($p->cancelled());
    }

    public function testCancelsInFlightRequests()
    {
        $c = $this->getClient();
        $h = new History();
        $c->getEmitter()->attach($h);
        $p = new Pool($c, [
            $c->createRequest('GET', 'http://foo.com'),
            $c->createRequest('GET', 'http://foo.com', [
                'events' => [
                    'before' => [
                        'fn' => function () use (&$p) {
                            $this->assertTrue($p->cancel());
                        },
                        'priority' => RequestEvents::EARLY
                    ]
                ]
            ])
        ]);
        ob_start();
        $p->deref();
        $contents = ob_get_clean();
        $this->assertTrue($p->cancelled());
        $this->assertEquals(1, count($h));
        $this->assertEquals('Cancelling', $contents);
    }

    private function getClient()
    {
        $future = new Future(function() {
            return ['status' => 200, 'headers' => []];
        }, function () {
            echo 'Cancelling';
        });

        return new Client(['adapter' => new MockAdapter($future)]);
    }

    public function testBatchesRequests()
    {
        $client = new Client(['adapter' => function () {
            throw new \RuntimeException('No network access');
        }]);

        $responses = [
            new Response(301, ['Location' => 'http://foo.com/bar']),
            new Response(200),
            new Response(200),
            new Response(404)
        ];

        $client->getEmitter()->attach(new Mock($responses));
        $requests = [
            $client->createRequest('GET', 'http://foo.com/baz'),
            $client->createRequest('HEAD', 'http://httpbin.org/get'),
            $client->createRequest('PUT', 'http://httpbin.org/put'),
        ];

        $a = $b = $c = $d = 0;
        $result = Pool::batch($client, $requests, [
            'before'   => function (BeforeEvent $e) use (&$a) { $a++; },
            'complete' => function (CompleteEvent $e) use (&$b) { $b++; },
            'error'    => function (ErrorEvent $e) use (&$c) { $c++; },
            'end'      => function (EndEvent $e) use (&$d) { $d++; }
        ]);

        $this->assertEquals(4, $a);
        $this->assertEquals(2, $b);
        $this->assertEquals(1, $c);
        $this->assertEquals(3, $d);
        $this->assertCount(3, $result);
        $this->assertInstanceOf('GuzzleHttp\BatchResults', $result);

        // The first result is actually the second (redirect) response.
        $this->assertSame($responses[1], $result[0]);
        // The second result is a 1:1 request:response map
        $this->assertSame($responses[2], $result[1]);
        // The third entry is the 404 RequestException
        $this->assertSame($responses[3], $result[2]->getResponse());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Each event listener must be a callable or
     */
    public function testBatchValidatesTheEventFormat()
    {
        $client = new Client();
        $requests = [$client->createRequest('GET', 'http://foo.com/baz')];
        Pool::batch($client, $requests, ['complete' => 'foo']);
    }
}
