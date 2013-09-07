Shaarli REST API
======

### Installation
* Create mysql database shaarli-api
* Install database schema /database/mysql_schema.sql
* Set database connection string in /bootstrap.php
* Set write permission on cache directory
* Init feed table with /install/init.php
* Set cronjob: php cron.php

### API Usage
* /feeds La liste des shaarlis
* /lastest Les derniers billets
* /top Les liens les plus partagés
* /search Rechercher dans les billets

### Options
* &format=json
* &pretty=true
