<?php namespace Mayconbordin\LaravelHttpDeployer;
use Mayconbordin\LaravelHttpDeployer\Exceptions\HttpDeployerException;
use Mayconbordin\LaravelHttpDeployer\Loggers\Logger;
use Mayconbordin\LaravelHttpDeployer\Servers\Server;

/**
 * Synchronizes local and remote.
 *
 * @author     David Grudl
 */
class LaravelHttpDeployer
{
	/**
	 * @var array List of things to be ignored while packaging
	 */
	public $ignoreMasks = [];

	/**
	 * @var array Associative array with the path to the file as key and as value the target path inside the package.
	 */
	public $extraFiles = [];

	/**
	 * @var array List of scripts to run before packaging the application.
	 */
	public $beforeScripts = [];

	/**
	 * @var string Temporary directory for creating packages for deployment
	 */
	public $tempDir = '/tmp';

	/**
	 * @var string The name of the package file
	 */
	public $packageName = 'package';

	/**
	 * @var string Name of the file that holds the deployment version
	 */
	public $versionFileName = 'version';

	/** @var string */
	private $local;

	/** @var Logger */
	private $logger;

	/** @var Server */
	private $server;

	/**
	 * @var int The current deployment version
	 */
	private $version;

	/**
	 * @var string The path to the package that is going to be deployed
	 */
	private $package;

	/**
	 * @param Server $server The server for deployment
	 * @param string $local Target directory for deployment
	 * @param Logger $logger
	 */
	public function __construct(Server $server, $local, Logger $logger)
	{
		$this->local = realpath($local);

		if (!$this->local) {
			throw new \InvalidArgumentException("Directory $local not found.");
		}

		$this->server = $server;
		$this->logger = $logger;
	}

	/**
	 * Synchronize remote and local.
	 * @return array
	 */
	public function deploy()
	{
		$this->logger->info("Starting deployment...");

		$this->applyBeforeScripts();
		$this->incrementVersion();
		$this->createPackage();
		$this->addExtraFiles();

		// send to server
		return $this->server->deploy($this->package);
	}

	/**
	 * Only packages the application, without incrementing the version or sending it to the server.
	 *
	 * @return string Path to the package
	 * @throws HttpDeployerException
	 */
	public function packageOnly()
	{
		$this->logger->info("Packaging application...");

		$this->applyBeforeScripts();
		$this->createPackage();
		$this->addExtraFiles();

		return $this->package;
	}

	/**
	 * Create the package with all files from the local path, except those that fall in the ignore mask rules.
	 * @throws HttpDeployerException
	 */
	private function createPackage()
	{
		$this->logger->info("Packaging version {$this->version} for deployment.");

		$this->package = $this->tempDir . '/' . $this->packageName . '-' . $this->version . '.tar.gz';

		$cmd = sprintf("cd %s && tar -zc -f %s * %s", $this->local, $this->package, $this->getIgnoreMasksTar());
		$out = null;
		$ret = null;

		exec($cmd, $out, $ret);

		if ($ret != 0) {
			throw new HttpDeployerException("An error ocurred while packaging.");
		}
	}

	/**
	 * Add the extra files to the package.
	 * @throws HttpDeployerException
	 */
	private function addExtraFiles()
	{
		$this->logger->info("Adding the extra files into the package.");

		try {
			$phar = new \PharData($this->package);

			foreach ($this->getExtraFiles() as $from => $to) {
				$phar->addFile($from, $to);
			}
		} catch (\Exception $e) {
			throw new HttpDeployerException("An error ocurred while adding the extra files to the package.", 0, $e);
		}
	}

	/**
	 * Reads the current deployment version and increments it. If it doesn't exists, create the version file and start
	 * the version at 1.
	 */
	private function incrementVersion()
	{
		$versionFile = $this->local . '/' . $this->versionFileName;
		$this->version = 0;

		if (file_exists($versionFile)) {
			$this->version = (int) file_get_contents($versionFile);
		}

		$this->version++;

		$f = fopen($versionFile, 'w');
		fwrite($f, "{$this->version}");
		fclose($f);
	}

	private function applyBeforeScripts()
	{
		$this->logger->info("Applying before scripts...");

		foreach ($this->beforeScripts as $script) {
			$cmd = sprintf("cd %s && %s", $this->local, $script);
			$out = [];
			$ret = 0;

			exec($cmd, $out, $ret);

			if ($ret != 0) {
				throw new HttpDeployerException("An error ocurred while executing the before scipts: ".$out);
			}

			$this->logger->info($cmd . ": " . implode("\n",$out));
		}
	}

	/**
	 * @return array
	 */
	private function getExtraFiles()
	{
		if (!is_array($this->extraFiles)) {
			$this->extraFiles = explode(PHP_EOL, $this->extraFiles);
		}

		$this->extraFiles = array_filter(array_map(function($item) {
			return trim($item);
		}, $this->extraFiles), function($item) {
			return (strlen($item) > 0);
		});

		return $this->extraFiles;
	}

	/**
	 * @return string List of ignore masks concatenated for the tar command.
	 */
	private function getIgnoreMasksTar()
	{
		if (!is_array($this->ignoreMasks)) {
			$this->ignoreMasks = explode(PHP_EOL, $this->ignoreMasks);
		}

		// filter empty strings
		$this->ignoreMasks = array_filter($this->ignoreMasks, function($item) {
			return (strlen(trim($item)) > 0);
		});

		return implode(' ', array_map(function($item) {
			return "--exclude='".trim($item)."'";
		}, $this->ignoreMasks));
	}
}
