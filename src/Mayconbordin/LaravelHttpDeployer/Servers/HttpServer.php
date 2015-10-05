<?php namespace Mayconbordin\LaravelHttpDeployer\Servers;

use GuzzleHttp\Client;
use GuzzleHttp\Event\ProgressEvent;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Post\PostFile;
use Illuminate\Config\Repository as Config;
use Mayconbordin\LaravelHttpDeployer\Exceptions\ServerException;
use Mayconbordin\LaravelHttpDeployer\Loggers\Logger;

class HttpServer implements Server
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Initialize the server.
     *
     * @param Config $config
     */
    public function initialize(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Upload the deployment package to the server.
     * @param string $packageFile
     * @return array
     * @throws ServerException
     */
    public function deploy($packageFile)
    {
        $url    = $this->config->get('remote_endpoint') . '?cmd=deploy';
        $config = $this->getRequestConfig();
        $config['package_md5'] = md5_file($packageFile);

        $this->logger->info("Sending package to remote server...");

        try {
            $body = new PostBody();
            $body->setField('config', json_encode($config));
            $body->addFile(new PostFile('package', fopen($packageFile, 'r'), null, ['Content-Type' => 'application/gzip']));

            $request = $this->getClient()->createRequest('POST', $url, [
                'headers' => $this->getHeaders(),
                'body'    => $body
            ]);

            $request->getEmitter()->on('progress', function (ProgressEvent $e) {
                $p = ($e->uploaded == 0) ? 0 : intval(($e->uploaded * 100)/$e->uploadSize);
                $this->logger->plain($p . '% uploaded ('.$e->uploaded . ' of ' . $e->uploadSize . " bytes)\r");
            });

            $response = $this->getClient()->send($request);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            throw new ServerException("Deploy error: ".$this->parseClientError($e), 0, $e);
        } catch (RequestException $e) {
            throw new ServerException("An error ocurred in the request while deploying the package: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * Rollback the last deployment.
     * @return mixed|void
     * @throws ServerException
     */
    public function rollback()
    {
        $url    = $this->config->get('remote_endpoint') . '?cmd=rollback';
        $config = $this->getRequestConfig();

        try {
            $body = new PostBody();
            $body->setField('config', json_encode($config));

            $request = $this->getClient()->createRequest('POST', $url, [
                'headers' => $this->getHeaders(),
                'body'    => $body
            ]);

            $response = $this->getClient()->send($request);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            throw new ServerException("Rollback error: ".$this->parseClientError($e), 0, $e);
        }
    }

    /**
     * Get the deployment status from the server.
     * @return mixed|void
     * @throws ServerException
     */
    public function status()
    {
        $url    = $this->config->get('remote_endpoint') . '?cmd=status';
        $config = $this->getRequestConfig();

        try {
            $body = new PostBody();
            $body->setField('config', json_encode($config));

            $request = $this->getClient()->createRequest('POST', $url, [
                'headers' => $this->getHeaders(),
                'body'    => $body
            ]);

            $response = $this->getClient()->send($request);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            throw new ServerException("Status error: ".$this->parseClientError($e), 0, $e);
        }
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        if ($this->client == null) {
            $this->client = new Client([
                'base_url' => $this->config->get('remote_url')
            ]);
        }
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $extra Extra headers
     * @return array
     */
    private function getHeaders($extra = [])
    {
        return array_merge([
            'Auth' => $this->config->get('auth_key')
        ], $extra);
    }

    /**
     * Get default configuration for deployment requests.
     * @return array
     */
    private function getRequestConfig()
    {
        return [
            'target'       => $this->config->get('remote_target'),
            'temp_dir'     => $this->config->get('remote_temp_dir'),
            'history_dir'  => $this->config->get('remote_history_dir')
        ];
    }

    /**
     * Parse the client error and return the error message thrown.
     * @param ClientException $e
     * @return string
     */
    private function parseClientError(ClientException $e)
    {
        $msg = "";

        if ($e->getResponse() != null && $e->getResponse()->getBody() != null) {
            $json = json_decode($e->getResponse()->getBody()->getContents(), true);
            $msg = isset($json['error']) ? $json['error'] : "";
        }

        return $msg;
    }
}