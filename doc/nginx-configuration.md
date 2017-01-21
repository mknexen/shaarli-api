# Nginx configuration

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
