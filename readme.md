Laravel HTTP Deployer
====================================

A tool for automated deployment of Laravel applications using HTTP.

This library is for those that have to deploy Laravel applications on shared hosting without SSH access and don't want to use FTP or GIT.

If you want to use FTP and/or GIT check these libraries:
  - [BrunoDeBarros/git-deploy-php](https://github.com/BrunoDeBarros/git-deploy-php)
  - [lkwdwrd/git-deploy](https://github.com/lkwdwrd/git-deploy)
  - [banago/PHPloy](https://github.com/banago/PHPloy)
  - [dg/ftp-deployment](https://github.com/dg/ftp-deployment)
  
## Remote Installation

On the server where the application is going to be deployed, you need to drop the [deploy.php](https://github.com/mayconbordin/laravel-http-deployer/blob/master/src/server/deploy.php) file at a public accessible directory (e.g. `public_html`) with 0644 permissions.

You will also need to create a configuration file. By default the file should be called `deploy_config.ini` and be located in the same directory as the script. Example configuration file:

```
auth_key = YOUR_AUTH_KEY
```

Where `auth_key` is a unique key that will be used for authentication. So, your configuration file should have 0640 permissions.

## Local Installation

In order to install Laravel HTTP Deployer locally (development environment), just add 

```json
"mayconbordin/laravel-http-deployer": "dev-master"
```

to your composer.json. Then run `composer install` or `composer update`.

Then in your `config/app.php` add 

```php
'Mayconbordin\LaravelHttpDeployer\LaravelHttpDeployerServiceProvider'
```

in the `providers` array.

### Configuration

You will also need to create a file with the deployment configuration. It's a YAML file that should look something like this:

```yaml
deployments:
  site1:
    auth_key: AUTH_KEY
    remote:
      url: http://localhost
      endpoint: /deploy.php
      target: /home/user/app
      temp_dir: /home/user/tmp
      history_dir: /home/user/tmp/old_deployments

    local:
      path: .
      temp_dir: /tmp

    ignore:
      - .git*
      - project.pp[jx]
      - /deployment.*
      - /log
      - temp/*
      - !temp/.htaccess
      - deploy

    extra_files:
      'deploy/env_file': .env
```

The `deployments` section can have multiple entries, each one describing one deployment configuration. The `auth_key` is a string that will be used to authenticate the deployment with the server.

Files can be excluded from the package by using the `ignore` list, the patterns are those from the [`tar`](https://www.gnu.org/software/tar/manual/html_section/tar_50.html) command.

The `extra_files` is a list of key/value pairs, where the key is the current file location and the value is the location of the file in the package. You can use it to include configuration files only meant for deployment, like the `.env` file.
