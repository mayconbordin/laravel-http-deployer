<?php namespace Mayconbordin\LaravelHttpDeployer\Commands;

use Mayconbordin\LaravelHttpDeployer\Exceptions\HttpDeployerException;
use Mayconbordin\LaravelHttpDeployer\Exceptions\ServerException;
use Mayconbordin\LaravelHttpDeployer\LaravelHttpDeployer;
use Mayconbordin\LaravelHttpDeployer\Loggers\OutputLogger;
use Mayconbordin\LaravelHttpDeployer\Servers\HttpServer;


class DeployCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy
                            {config : The path to the configuration file}
                            {deployment? : Name of deployment to be deployed. Default to all.}
                            {--package-only : Only generates the deployment package}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy the application to a remote server.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $deployments    = $this->fetchDeployments();
        $deploymentName = $this->argument('deployment');
        $packageOnly    = $this->option('package-only');

        $time = time();
        $this->info("Started at " . date('[Y-m-d H:i:s]'));
        $this->info("Config file is ".$this->argument('config'));

        $logger = new OutputLogger($this->output);

        foreach ($deployments as $section => $config) {
            if ($deploymentName != null && $deploymentName != $section) {
                continue;
            }

            $this->info("Deploying $section");

            try {
                $server = new HttpServer($logger);
                $server->initialize($config);

                $deployer = new LaravelHttpDeployer($server, $config->get('local.path', '.'), $logger);
                $deployer->ignoreMasks     = $config->get('ignore', []);
                $deployer->extraFiles      = $config->get('extra_files', []);
                $deployer->tempDir         = $config->get('local.temp_dir', '/tmp');
                $deployer->versionFileName = $config->get('version_filename', 'version');
                $deployer->packageName     = $section;

                if ($packageOnly) {
                    $packagePath = $deployer->packageOnly();
                    $this->info("Package created at '$packagePath'.");
                } else {
                    $response = $deployer->deploy();
                    $this->info($response['success']);
                }
            } catch (ServerException $e) {
                $this->error("Server error: ".$e->getMessage());
            } catch (HttpDeployerException $e) {
                $this->error("Deployer error: ".$e->getMessage());
            }
        }

        $time = time() - $time;
        $this->info("Finished at " . date('[Y-m-d H:i:s]') . " (in $time seconds)");
    }
}