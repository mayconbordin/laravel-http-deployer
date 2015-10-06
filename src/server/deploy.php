<?php

if (!function_exists('getallheaders'))
{
    function getallheaders()
    {
        $headers = '';
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

function removeDir($dir)
{
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
        if ($file->isDir()){
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}

class DeploymentServerException extends Exception
{
    public function __construct($message = "", $code = 400)
    {
        parent::__construct($message, $code);
    }
}

class DeploymentServer
{
    private $package;
    private $config;

    private $authKey;

    private $validTypes = array('application/gzip');
    private $defaultConfig = array(
        'package_name' => 'package',
        'temp_dir'     => '../tmp',
        'history_dir'  => '../tmp/old_deployments'
    );

    public function __construct()
    {
        $this->readConfiguration();
    }

    private function readConfiguration()
    {
        $cfgFile = __DIR__.'/deploy_config.ini';

        if (!file_exists($cfgFile)) {
            throw new DeploymentServerException("Configuration file $cfgFile does not exists.");
        }

        $cfg = parse_ini_file($cfgFile);

        if ($cfg === false) {
            throw new DeploymentServerException("Unable to read configuration file $cfgFile.");
        }

        $this->authKey = $cfg['auth_key'];
    }

    private function authenticate()
    {
        $headers = getallheaders();
        return (isset($headers['Auth']) && $this->authKey != null && strlen($this->authKey) > 0 && $headers['Auth'] == $this->authKey);
    }

    private function getPackage()
    {
        if (!isset($_FILES['package'])) {
            throw new DeploymentServerException("Package for deployment missing.");
        }

        $package = $_FILES['package'];

        if (!in_array($package['type'], $this->validTypes)) {
            throw new DeploymentServerException("Package for deployment has not a valid type: ".$package['type']);
        }

        if ($package['error'] != 0) {
            throw new DeploymentServerException("An error occurred while uploading the deployment package.");
        }

        return $package;
    }

    private function loadConfig()
    {
        if (!isset($_POST['config'])) {
            throw new DeploymentServerException("Configuration for deployment missing.");
        }

        $config = json_decode(stripslashes($_POST['config']), true);
        $this->config = array_merge($this->defaultConfig, $config);
    }

    private function getPostParameter($name, $default = null)
    {
        return isset($_POST[$name]) ? $_POST[$name] : $default;
    }

    private function extractPackage($tmpPackage)
    {
        $this->package = [
            'name' => $this->config['package_name'],
            'gzip' => $this->config['temp_dir'] . '/' . $this->config['package_name'] . '.tar.gz',
            'tar'  => $this->config['temp_dir'] . '/' . $this->config['package_name'] . '.tar',
            'dir'  => $this->config['temp_dir'] . '/' . $this->config['package_name']
        ];

        // move file to temporary location
        move_uploaded_file($tmpPackage['tmp_name'], $this->package['gzip']);

        // check if file is corrupted
        if (md5_file($this->package['gzip']) != $this->config['package_md5']) {
            throw new DeploymentServerException("Deployment package is corrupted");
        }

        if (!file_exists($this->package['dir'])) {
            mkdir($this->package['dir']);
        }

        // decompress from gz
        $out = 0;
        $ret = [];
        $cmd = sprintf("tar -C %s -xf %s", $this->package['dir'], $this->package['gzip']);

        exec($cmd, $out, $ret);

        if ($ret != 0) {
            throw new DeploymentServerException("Unable to extract deployment package: ".implode("\n", $out));
        }
    }

    private function cleanAll()
    {
        if (file_exists($this->package['gzip'])) {
            unlink($this->package['gzip']);
        }

        if (file_exists($this->package['tar'])) {
            unlink($this->package['tar']);
        }

        if (file_exists($this->package['dir'])) {
            removeDir($this->package['dir']);
        }
    }

    public function getVersion($packageDir)
    {
        $vFile = $packageDir . "/" . $this->config['version_filename'];

        if (!file_exists($vFile)) {
            throw new DeploymentServerException("Version file not found.");
        }

        $version = file_get_contents($vFile);
        return (int) trim($version);
    }

    private function replaceOldVersion()
    {
        if (!file_exists($this->config['history_dir'])) {
            mkdir($this->config['history_dir']);
        }

        $oldVersion = $this->getVersion($this->config['target']);
        $newVersion = $this->getVersion($this->package['dir']);

        if ($oldVersion >= $newVersion) {
            $this->cleanAll();
            throw new DeploymentServerException("Old version ($oldVersion) is actually newer or equal to new version ($newVersion)");
        }

        // move the old version (current) to the history
        rename($this->config['target'], $this->config['history_dir'] . '/' . $oldVersion);

        // and move the new version to the current directory
        rename($this->package['dir'], $this->config['target']);
    }

    private function getCommand()
    {
        if (!isset($_GET['cmd'])) {
            throw new Exception("You should provide a command to be executed.");
        }

        return trim($_GET['cmd']);
    }

    private function getHistory()
    {
        if (!file_exists($this->config['history_dir'])) {
            mkdir($this->config['history_dir']);
        }

        $history = scandir($this->config['history_dir'], SCANDIR_SORT_DESCENDING);

        $history = array_filter($history, function($name) {
            return ($name != "." && $name != "..");
        });

        sort($history, SORT_NUMERIC);

        return $history;
    }

    private function deploy()
    {
        $tmpPackage = $this->getPackage();
        $this->extractPackage($tmpPackage);

        $this->replaceOldVersion();
        $this->cleanAll();

        $this->response(["success" => "Deployment finished"]);
    }

    public function rollback()
    {
        $targetVersion  = $this->getPostParameter('version');
        $currentVersion = $this->getVersion($this->config['target']);
        $versions       = $this->getHistory();

        if ($targetVersion == null) {
            $oldVersions = array_filter($versions, function ($version) use ($currentVersion) {
                return (intval($version) < intval($currentVersion));
            });

            if (sizeof($oldVersions) == 0) {
                return $this->response(["success" => "Nothing to rollback"]);
            }

            $replaceVersion = $oldVersions[0];
        } else {
            if (!in_array($targetVersion, $versions)) {
                return $this->response(["success" => "Version $targetVersion is not available for rollback"]);
            }

            $replaceVersion = $targetVersion;
        }

        rename($this->config['target'], $this->config['history_dir'] . '/' . $currentVersion);
        rename($this->config['history_dir'] . '/' . $replaceVersion, $this->config['target']);

        $this->response(["success" => "Rolled back from version $currentVersion to version $replaceVersion"]);
    }

    private function status()
    {
        $currentVersion = $this->getVersion($this->config['target']);
        $oldVersions    = $this->getHistory();

        $this->response([
            'currentVersion' => $currentVersion,
            'oldVersions'    => $oldVersions
        ]);
    }

    public function serve()
    {
        if (!$this->authenticate()) {
            throw new DeploymentServerException("Unauthorized", 401);
        }

        $this->loadConfig();
        $command = $this->getCommand();

        switch ($command) {
            case "deploy":
                $this->deploy();
                break;

            case "rollback":
                $this->rollback();
                break;

            case "status":
                $this->status();
                break;

            default:
                $this->response(["error" => "The command provided is not valid"], 400);
        }
    }

    public function response(array $data, $code = 200)
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($data);
    }
}


$server = new DeploymentServer();

try {
    $server->serve();
} catch (DeploymentServerException $e) {
    $server->response(["error" => $e->getMessage()], $e->getCode());
} catch (Exception $e) {
    $server->response(["error" => $e->getMessage()], 400);
}