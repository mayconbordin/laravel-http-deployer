<?php namespace Mayconbordin\LaravelHttpDeployer\Commands;

use Illuminate\Console\Command;
use Illuminate\Config\Repository as Config;
use Mayconbordin\LaravelHttpDeployer\Loggers\Logger;
use Mayconbordin\LaravelHttpDeployer\Loggers\OutputLogger;
use Symfony\Component\Yaml\Yaml;

abstract class BaseCommand extends Command
{
    /**
     * @var int Start time of handle command in unix epoch time (in seconds).
     */
    protected $startTime;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        set_time_limit(0);
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

    protected function onStartCommand()
    {
        $this->startTime = time();
        $this->info("Started at " . date('[Y-m-d H:i:s]'));
        $this->info("Config file is ".$this->argument('config'));
    }

    protected function onEndCommand()
    {
        $time = time() - $this->startTime;
        $this->info("Finished at " . date('[Y-m-d H:i:s]') . " (in $time seconds)");
    }

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        if ($this->logger == null) {
            $this->logger = new OutputLogger($this->output);
        }

        return $this->logger;
    }
}