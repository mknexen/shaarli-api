Shaarli REST API
======

## Installation
* Create mysql database shaarli-api
* cd shaarli-api
* Copy `config.php.dist` into `config.php` and setup your own settings.
* # Run `composer install` (https://getcomposer.org/download/)
* php -r "readfile('https://getcomposer.org/installer');" | php
* php composer.phar install
* Run: php cron.php --verbose

## Requirements
* PHP 5.4.4
* MySQL or Sqlite
* PDO
* Apache RewriteEngine or Nginx

## Update your installation
* Update your installation via Git (`git update origin master`) or the [archive file](archive/master.zip).
* Check if there was any changes in [config file](blob/master/config.php.dist), and add settings if necessary.
* Update external libraries with [Composer](https://getcomposer.org/download/). Run: `composer update`.
* Run cron the finalize the update: `php cron.php --verbose`.

## Install via SSH exemple (debian)
```bash
cd /var/www
# Clone repo
git clone https://github.com/mknexen/shaarli-api.git
# Create mysql database
mysqladmin create shaarli-api -p
cd shaarli-api
# Copy `config.php.dist` into `config.php` and setup your own settings.
cp config.php.dist config.php
nano config.php
# Run composer install
php -r "readfile('https://getcomposer.org/installer');" | php
php composer.phar install
# Run cron, for initialization we recommend using the argument --verbose (or -v) to be sure everything working fine
php cron.php --verbose
```

## Nginx configuration
```
location /shaarli-api {
    if (!-e $request_filename) {
       rewrite ^(/shaarli-api)/(.*)$ $1/index.php/$2;
    }
}

location /shaarli-api/database {
    deny all;
    return 403;
}

location /shaarli-api/class {
    deny all;
    return 403;
}

location ~ [^/]\.(php|html|htm)(/|$) {
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    if (!-f $document_root$fastcgi_script_name) {
        return 404;
    }

    fastcgi_pass   unix:/var/run/php-fpm/php-fpm.sock;
    fastcgi_index  index.php;
    include        fastcgi.conf;
    fastcgi_param  PATH_INFO $fastcgi_path_info;
}
```

## API Usage
* /feeds La liste des shaarlis
* /latest Les derniers billets
* /top Les liens les plus partagés
* /search Rechercher dans les billets
* /discussion Rechercher une discussion
* /syncfeeds Synchroniser la liste des shaarlis

## Options
* &format=json
* &pretty=true

## Samples
* Obtenir la liste des flux actifs: https://nexen.netk.fr/shaarli-api/feeds?pretty=1
* Obtenir la liste complète des flux: https://nexen.netk.fr/shaarli-api/feeds?full=1&pretty=1
* Obtenir le nombre de flux actifs: https://nexen.netk.fr/shaarli-api/feeds?count=1&pretty=1
* Obtenir les billets d'un seul flux: https://nexen.netk.fr/shaarli-api/feed?id=1&pretty=1
* Obtenir les derniers billets https://nexen.netk.fr/shaarli-api/latest?pretty=1
* Obtenir le top des liens partagés depuis 48h: https://nexen.netk.fr/shaarli-api/top?interval=48h&pretty=1
* Faire une recherche sur php: https://nexen.netk.fr/shaarli-api/search?q=php&pretty=1
* Rechercher une discution sur un lien: https://nexen.netk.fr/shaarli-api/discussion?url=https://nexen.netk.fr/shaarli-river/index.php&pretty=1
