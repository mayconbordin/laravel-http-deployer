<?php namespace Mayconbordin\LaravelHttpDeployer\Commands;


use Mayconbordin\LaravelHttpDeployer\Exceptions\ServerException;
use Mayconbordin\LaravelHttpDeployer\Loggers\OutputLogger;
use Mayconbordin\LaravelHttpDeployer\Servers\HttpServer;

class StatusCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:status
                            {config : The path to the configuration file}
                            {deployment? : Name of deployment to verify the status. Default to all.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the status of the deployed application on the remote server.';

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

            $this->info("Getting the status of $section");

            try {
                $server = new HttpServer($this->getLogger());
                $server->initialize($config);

                $data = $server->status();

                $this->info("Current version: ".$data['currentVersion']);
                $this->info("Old versions: ".implode(', ', $data['oldVersions']));
            } catch (ServerException $e) {
                $this->error("Server error: ".$e->getMessage());
            }
        }

        $this->onEndCommand();
    }
}