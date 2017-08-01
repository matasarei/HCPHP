# HCPHP
Tiny PHP MVC framework

## nginx + php-fpm config example
```
server {
    listen        80;
    server_name   hcphp.dev www.hcphp.dev;

    root /var/www/hcphp.dev/public_html;
    index index.php index.html index.htm;

    charset utf-8;
    client_max_body_size 100M;
    fastcgi_read_timeout 1800;

    error_page 500 /500/;
    error_page 404 /404/;
    error_page 403 /403/;

    location / {
        rewrite ^/(.*)([^/])$ $scheme://$http_host/$1$2/ redirect;
        try_files $uri $uri/ /index.php?q=$uri&$args;
    }

    location ~ \.php$ {
        try_files $uri =404;

        fastcgi_pass  unix:/var/run/php/php7.1-fpm.sock; # PHP 7.1
        #fastcgi_pass  unix:/var/run/php/php7.0-fpm.sock; # PHP 7.0
        #fastcgi_pass  unix:/var/run/php/php5.6-fpm.sock; # PHP 5.6
        #fastcgi_pass  unix:/var/run/php-fpm.sock # PHP <= 5.5.9
        #fastcgi_pass  127.0.0.1:9000; # TCP

        fastcgi_index /index.php;

        include fastcgi_params;
        fastcgi_split_path_info       ^(.+\.php)(/.+)$;
        fastcgi_param PATH_INFO       $fastcgi_path_info;
        fastcgi_param PATH_TRANSLATED $document_root$fastcgi_path_info;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {
        expires       max;
        log_not_found off;
        access_log    off;
    }
}
```
