<?php

use Mayconbordin\LaravelHttpDeployer\Servers\HttpServer;
use Mockery as m;
use Hamcrest as h;

include __DIR__ . '/TestCase.php';
include __DIR__ . '/PayloadMatcher.php';

class HttpServerTest extends TestCase
{
    /**
     * @var HttpServer
     */
    private $httpServer;

    private $config = [
        'auth_key' => 'AUTH_KEY',
        'remote' => [
            'url' => 'http://test.com',
            'endpoint' => '/deploy.php',
            'target' => '/home/test/app',
            'temp_dir' => '/home/test/tmp',
            'history_dir' => '/home/test/tmp/old_deployments',
        ],
        'local' => [
            'path' => '.'
        ]
    ];

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $logger = new \Mayconbordin\LaravelHttpDeployer\Loggers\ConsoleLogger();
        $this->httpServer = new HttpServer($logger);
        $this->httpServer->initialize(new \Illuminate\Config\Repository($this->config));
    }


    public function testDeploy()
    {
        $body = m::mock(\GuzzleHttp\Stream\StreamInterface::class);
        $body->shouldReceive('getContents')->once()->andReturn('{"success": "Deploy success"}');

        $response = m::mock(\GuzzleHttp\Message\ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($body);

        $emitter = m::mock(\GuzzleHttp\Event\EmitterInterface::class);
        $emitter->shouldReceive('on')->with(m::mustBe('progress'), m::type('callable'))->once();

        $request = m::mock(\GuzzleHttp\Message\RequestInterface::class);
        $request->shouldReceive('getEmitter')->once()->andReturn($emitter);

        $client = m::mock(\GuzzleHttp\Client::class);
        $client->shouldReceive('createRequest')
               ->with(m::mustBe('POST'), m::mustBe($this->config['remote']['endpoint'] . '?cmd=deploy'), new PayloadMatcher(['headers', 'body']))
               ->once()->andReturn($request);

        $client->shouldReceive('send')->with($request)->once()->andReturn($response);

        $package = $this->createTestPackage();

        $this->httpServer->setClient($client);
        $this->httpServer->deploy($package);
    }

    public function testRollback()
    {
        $body = m::mock(\GuzzleHttp\Stream\StreamInterface::class);
        $body->shouldReceive('getContents')->once()->andReturn('{"success": "Rolled back"}');

        $response = m::mock(\GuzzleHttp\Message\ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($body);

        $request = m::mock(\GuzzleHttp\Message\RequestInterface::class);

        $client = m::mock(\GuzzleHttp\Client::class);
        $client->shouldReceive('createRequest')
            ->with(m::mustBe('POST'), m::mustBe($this->config['remote']['endpoint'] . '?cmd=rollback'), new PayloadMatcher(['headers', 'body']))
            ->once()->andReturn($request);

        $client->shouldReceive('send')->with($request)->once()->andReturn($response);

        $this->httpServer->setClient($client);
        $this->httpServer->rollback();
    }

    public function testStatus()
    {
        $body = m::mock(\GuzzleHttp\Stream\StreamInterface::class);
        $body->shouldReceive('getContents')->once()->andReturn('{"success": "Rolled back"}');

        $response = m::mock(\GuzzleHttp\Message\ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($body);

        $request = m::mock(\GuzzleHttp\Message\RequestInterface::class);

        $client = m::mock(\GuzzleHttp\Client::class);
        $client->shouldReceive('createRequest')
            ->with(m::mustBe('POST'), m::mustBe($this->config['remote']['endpoint'] . '?cmd=status'), new PayloadMatcher(['headers', 'body']))
            ->once()->andReturn($request);

        $client->shouldReceive('send')->with($request)->once()->andReturn($response);

        $this->httpServer->setClient($client);
        $this->httpServer->status();
    }

    private function createTestPackage()
    {
        $filename = '/tmp/test_package.tar.gz';

        $f = fopen($filename, 'w');
        fwrite($f, 'test');
        fclose($f);

        return $filename;
    }
}