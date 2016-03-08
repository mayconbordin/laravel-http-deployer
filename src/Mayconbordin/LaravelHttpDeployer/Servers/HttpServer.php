<?php namespace Mayconbordin\LaravelHttpDeployer\Servers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
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
        $url    = $this->config->get('remote.endpoint') . '?cmd=deploy';
        $config = $this->getRequestConfig();
        $config['package_md5'] = md5_file($packageFile);

        $this->logger->info("Sending package to remote server...");

        try {
            $response = $this->getClient()->request('POST', $url, [
                'headers'   => $this->getHeaders(),
                'multipart' => [
                    [
                        'name'     => 'config',
                        'contents' => json_encode($config)
                    ],
                    [
                        'name'     => 'package',
                        'contents' => fopen($packageFile, 'r'),
                        'headers'  => ['Content-Type' => 'application/gzip']
                    ]
                ],
                'progress' => function ($downloadSize, $downloaded, $uploadSize, $uploaded) {
                    $p = ($uploaded == 0) ? 0 : intval(($uploaded * 100)/$uploadSize);
                    $this->logger->plain($p . '% uploaded ('.$uploaded . ' of ' . $uploadSize . " bytes)\r");
                }
            ]);

            $this->logger->plain("\n");

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            throw new ServerException("Deploy error: ".$this->parseClientError($e), 0, $e);
        } catch (RequestException $e) {
            throw new ServerException("An error ocurred in the request while deploying the package: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * Rollback the last deployment.
     * @param int|null $version
     * @return mixed|void
     * @throws ServerException
     */
    public function rollback($version = null)
    {
        $url    = $this->config->get('remote.endpoint') . '?cmd=rollback';
        $config = $this->getRequestConfig();

        try {
            $params = [];
            $params['config'] = json_encode($config);

            if ($version != null) {
                $params['version'] = $version;
            }

            $response = $this->getClient()->request('POST', $url, [
                'headers'     => $this->getHeaders(),
                'form_params' => $params
            ]);

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
        $url    = $this->config->get('remote.endpoint') . '?cmd=status';
        $config = $this->getRequestConfig();

        try {
            $params = [];
            $params['config'] = json_encode($config);

            $response = $request = $this->getClient()->request('POST', $url, [
                'headers'     => $this->getHeaders(),
                'form_params' => $params
            ]);

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
                'base_uri' => $this->config->get('remote.url')
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
            'target'           => $this->config->get('remote.target'),
            'temp_dir'         => $this->config->get('remote.temp_dir'),
            'history_dir'      => $this->config->get('remote.history_dir'),
            'version_filename' => $this->config->get('version_filename', 'version')
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