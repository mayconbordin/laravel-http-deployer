<?php namespace Mayconbordin\LaravelHttpDeployer\Commands;


use Mayconbordin\LaravelHttpDeployer\Exceptions\ServerException;
use Mayconbordin\LaravelHttpDeployer\Loggers\OutputLogger;
use Mayconbordin\LaravelHttpDeployer\Servers\HttpServer;

class RollbackCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:rollback
                            {config : The path to the configuration file}
                            {deployment? : Name of deployment to be rolled back. Default to all.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback to previous version (if available) the deployed application on the remote server.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $deployments    = $this->fetchDeployments();
        $deploymentName = $this->argument('deployment');

        $this->onStartCommand();

        foreach ($deployments as $section => $config) {
            if ($deploymentName != null && $deploymentName != $section) {
                continue;
            }

            $this->info("Rolling back $section");

            try {
                $server = new HttpServer($this->getLogger());
                $server->initialize($config);

                $response = $server->rollback();
                $this->info($response['success']);
            } catch (ServerException $e) {
                $this->error("Server error: ".$e->getMessage());
            }
        }

        $this->onEndCommand();
    }
}