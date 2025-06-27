# ![](/icon.png) tinyi

A tiny file hosting service that somehow now consists of booru features.

Features:

+ Non-JavaScript frontend option.
+ Upload history.
+ Tags.
+ Download from external sources (`yt-dlp` required).
+ Static/animated thumbnails.
+ File Catalog
+ Accounts

## Prerequisites

+ PHP >= 8.4
+ `yt-dlp` (for downloading from external sources)
+ `ffmpeg` (for animated thumbnails)

## Installation guide

1. Clone the Git repository.
2. Use a reverse proxy *(Nginx, Apache, etc.)* for the project. See [configuration examples](#reverse-proxy-configurations).
3. Copy the `/config.sample.php` file to `/config.php`, or open `/index.php` to copy it automatically. In this file, you can customize your instance.
4. ???
5. Profit! Now everything should be working.

### Reverse proxy configurations

<details>
<summary>Basic Nginx configuration</summary>

```nginx
server {
    server_name tinyiinstance.com;

    root /www/tinyiinstance/public;
    index index.php;

    client_max_body_size 10M;

    location / {
            try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php/php-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /thumbnails/^/[^/]+\.[a-zA-Z0-9]+$ {
            root /var/www/tinyiinstance/thumbnails;
            try_files $uri =404;
    }

    location ~ ^/[^/]+\.[a-zA-Z0-9]+$ {
            root /var/www/tinyiinstance/uploads;
            try_files $uri =404;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 6M;
        access_log off;
        add_header Cache-Control "public";
    }

    location ~ /\. {
        deny all;
    }
}
```

</details>

## License

This project is under Apache-2.0 license. See [LICENSE](/LICENSE).

