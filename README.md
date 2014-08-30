Shaarli REST API
======

## Installation
* Create mysql database shaarli-api
* Copy `config.php.dist` into `config.php` and setup your own settings.
* Run `composer install` (https://getcomposer.org/download/)
* Run: php cron.php

### Requirements
* PHP 5.4.4
* MySQL
* PDO
* Apache RewriteEngine

### Install via SSH exemple (debian)
```
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
composer install
# Run cron
php cron.php
```

### API Usage
* /feeds La liste des shaarlis
* /latest Les derniers billets
* /top Les liens les plus partagés
* /search Rechercher dans les billets
* /discussion Rechercher une discussion
* /syncfeeds Synchroniser la liste des shaarlis

### Options
* &format=json
* &pretty=true

### Samples
* Obtenir la liste des flux actifs: http://nexen.mkdir.fr/shaarli-api/feeds?pretty=1
* Obtenir la liste complète des flux: http://nexen.mkdir.fr/shaarli-api/feeds?full=1&pretty=1
* Obtenir les derniers billets http://nexen.mkdir.fr/shaarli-api/latest?pretty=1
* Obtenir le top des liens partagés depuis 48h: http://nexen.mkdir.fr/shaarli-api/top?interval=48h&pretty=1
* Faire une recherche sur php: http://nexen.mkdir.fr/shaarli-api/search?q=php&pretty=1
* Rechercher une discution sur un lien: http://nexen.mkdir.fr/shaarli-api/discussion?url=https://nexen.mkdir.fr/shaarli-river/index.php&pretty=1
