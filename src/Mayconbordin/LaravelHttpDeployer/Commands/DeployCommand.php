<?php namespace Mayconbordin\LaravelHttpDeployer\Commands;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;
use Mayconbordin\LaravelHttpDeployer\Exceptions\HttpDeployerException;
use Mayconbordin\LaravelHttpDeployer\Exceptions\ServerException;
use Mayconbordin\LaravelHttpDeployer\LaravelHttpDeployer;
use Mayconbordin\LaravelHttpDeployer\Loggers\Logger;
use Mayconbordin\LaravelHttpDeployer\Loggers\OutputLogger;
use Mayconbordin\LaravelHttpDeployer\Servers\HttpServer;
use Mayconbordin\LaravelHttpDeployer\Servers\Server;
use Symfony\Component\Yaml\Yaml;

class DeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy
                            {config : The path to the configuration file}
                            {--generate : Only generates deployment file}
                            {--test : Run in test-mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    /**
     * @var array
     */
    protected $deployments;

    /**
     * @var string generate|test|null
     */
    protected $mode;

    /**
     * @var Server
     */
    protected $server;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        set_time_limit(0);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->deployments = $this->fetchDeployments();
        $this->mode        = $this->option('generate') ? 'generate' : ($this->option('test') ? 'test' : null);

        $time = time();
        $this->info("Started at " . date('[Y-m-d H:i:s]'));
        $this->info("Config file is ".$this->argument('config'));

        $logger = new OutputLogger($this->output);

        foreach ($this->deployments as $section => $config) {
            $this->info("Deploying $section");

            try {
                $server = $this->getServer($config, $logger);

                $deployer = new LaravelHttpDeployer($server, $config->get('local.path', '.'), $logger);
                $deployer->ignoreMasks     = $config->get('ignore', []);
                $deployer->extraFiles      = $config->get('extra_files', []);
                $deployer->tempDir         = $config->get('local.temp_dir', '/tmp');
                $deployer->versionFileName = $config->get('version_filename', 'version');
                $deployer->packageName     = $section;

                $response = $deployer->deploy();
                $this->info($response['success']);
            } catch (ServerException $e) {
                $this->error("Server error: ".$e->getMessage());
            } catch (HttpDeployerException $e) {
                $this->error("Deployer error: ".$e->getMessage());
            }
        }

        $time = time() - $time;
        $this->info("Finished at " . date('[Y-m-d H:i:s]') . " (in $time seconds)");
    }

    /**
     * Get the server and initialize with the given configuration.
     * @param Config $config
     * @return Server
     */
    protected function getServer(Config $config, Logger $logger)
    {
        if ($this->server == null) {
            $this->server = new HttpServer($logger);
        }

        $this->server->initialize($config);
        return $this->server;
    }

    /**
     * Return list of deployments and its configurations.
     * @return array
     */
    protected function fetchDeployments()
    {
        $config = $this->loadConfigFile();

        if (!isset($config['deployments'])) {
            $this->error("Configuration file should have a 'deployments' section.");
            exit;
        }

        $deployments = [];

        foreach ($config['deployments'] as $section => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }

            $deployments[$section] = new Config($cfg);
        }

        return $deployments;
    }

    /**
     * Load configuration file into associative array.
     *
     * @param $file
     * @return array|mixed
     */
    protected function loadConfigFile($file = null)
    {
        if ($file == null) {
            $file = $this->argument('config');
        }

        if (!file_exists($file)) {
            $this->error("Configuration file '$file' does not exists.");
            exit;
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if ($ext == 'yaml') {
            return Yaml::parse(file_get_contents($file));
        } else {
            $this->error("Configuration format '$ext' not supported. Supported format is YAML.");
            exit;
        }
    }
}