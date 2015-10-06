Laravel HTTP Deployer
====================================

A tool for automated deployment of Laravel applications using HTTP.

This library is for those that have to deploy Laravel applications on shared hosting without SSH access and don't want to use FTP or GIT.

If you want to use FTP and/or GIT check these libraries:
  - [BrunoDeBarros/git-deploy-php](https://github.com/BrunoDeBarros/git-deploy-php)
  - [lkwdwrd/git-deploy](https://github.com/lkwdwrd/git-deploy)
  - [banago/PHPloy](https://github.com/banago/PHPloy)
  - [dg/ftp-deployment](https://github.com/dg/ftp-deployment)
  
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

## Remote Installation

On the server where the application is going to be deployed, you need to drop the [deploy.php](https://github.com/mayconbordin/laravel-http-deployer/blob/master/src/server/deploy.php) file at a public accessible directory (e.g. `public_html`) with 0644 permissions.

You will also need to create a configuration file. By default the file should be called `deploy_config.ini` and be located in the same directory as the script. Example configuration file:

```
auth_key = YOUR_AUTH_KEY
```

Where `auth_key` is a unique key that will be used for authentication. So, your configuration file should have 0640 permissions.
