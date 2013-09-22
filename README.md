Shaarli REST API
======

### Installation
* Create mysql database shaarli-api
* Install database schema /database/mysql_schema.sql
* Set database connection string in /bootstrap.php
* Enable Apache RewriteEngine
* Initialize feed list with api action /syncfeeds
* Set cronjob: php cron.php

### API Usage
* /feeds La liste des shaarlis
* /latest Les derniers billets
* /top Les liens les plus partagés
* /search Rechercher dans les billets
*
* /syncfeeds Synchroniser la liste des shaarlis

### Options
* &format=json
* &pretty=true
