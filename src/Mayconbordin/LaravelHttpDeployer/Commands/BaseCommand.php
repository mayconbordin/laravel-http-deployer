<?php namespace Mayconbordin\LaravelHttpDeployer\Commands;

use Illuminate\Console\Command;
use Illuminate\Config\Repository as Config;
use Symfony\Component\Yaml\Yaml;

abstract class BaseCommand extends Command
{
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
}