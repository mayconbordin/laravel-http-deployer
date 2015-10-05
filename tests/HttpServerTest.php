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
        'remote_url' => 'http://test.com',
        'remote_endpoint' => '/deploy.php',
        'remote_target' => '/home/test/app',
        'remote_temp_dir' => '/home/test/tmp',
        'remote_history_dir' => '/home/test/tmp/old_deployments',
        'local' => '.'
    ];

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->httpServer = new HttpServer();
        $this->httpServer->initialize(new \Illuminate\Config\Repository($this->config));
    }


    public function testDeploy()
    {
        $body = m::mock(\Psr\Http\Message\StreamInterface::class);
        $body->shouldReceive('getContents')->once()->andReturn('{"success": "Deploy success"}');

        $response = m::mock(\Psr\Http\Message\ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($body);

        $client = m::mock(\GuzzleHttp\Client::class);
        $client->shouldReceive('request')
               ->with(m::mustBe('POST'), m::mustBe($this->config['remote_endpoint'] . '?cmd=deploy'), new PayloadMatcher(['headers', 'multipart']))
               ->once()->andReturn($response);

        $package = $this->createTestPackage();

        $this->httpServer->setClient($client);
        $this->httpServer->deploy($package);
    }

    public function testRollback()
    {
        $body = m::mock(\Psr\Http\Message\StreamInterface::class);
        $body->shouldReceive('getContents')->once()->andReturn('{"success": "Rolled back"}');

        $response = m::mock(\Psr\Http\Message\ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($body);

        $client = m::mock(\GuzzleHttp\Client::class);
        $client->shouldReceive('request')
            ->with(m::mustBe('POST'), m::mustBe($this->config['remote_endpoint'] . '?cmd=rollback'), new PayloadMatcher(['headers', 'form_params']))
            ->once()->andReturn($response);

        $this->httpServer->setClient($client);
        $this->httpServer->rollback();
    }

    public function testStatus()
    {
        $body = m::mock(\Psr\Http\Message\StreamInterface::class);
        $body->shouldReceive('getContents')->once()->andReturn('{"success": "Rolled back"}');

        $response = m::mock(\Psr\Http\Message\ResponseInterface::class);
        $response->shouldReceive('getBody')->once()->andReturn($body);

        $client = m::mock(\GuzzleHttp\Client::class);
        $client->shouldReceive('request')
            ->with(m::mustBe('POST'), m::mustBe($this->config['remote_endpoint'] . '?cmd=status'), new PayloadMatcher(['headers', 'form_params']))
            ->once()->andReturn($response);

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