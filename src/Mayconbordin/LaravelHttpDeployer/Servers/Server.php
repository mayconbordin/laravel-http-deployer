<?php namespace Mayconbordin\LaravelHttpDeployer\Servers;

use Illuminate\Config\Repository as Config;

/**
 * Server.
 *
 * @author Maycon Bordin <mayconbordin@gmail.com>
 */
interface Server
{
	/**
	 * Initialize the server.
	 * @param Config $config
	 * @return void
	 */
	function initialize(Config $config);

	/**
	 * Upload the deployment package to the server.
	 * @param string $packageFile
	 * @return array
	 */
	function deploy($packageFile);

	/**
	 * Rollback the last deployment.
	 * @param int|null $version
	 * @return mixed
	 */
	function rollback($version = 0);

	/**
	 * Get the deployment status from the server.
	 * @return mixed
	 */
	function status();
}
