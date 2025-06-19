# ![](/icon.png) tinyi

A tiny file hosting service.

Features:

+ Non-JavaScript frontend option.
+ Upload history.
+ No database.

## Prerequisites

+ PHP >= 8.4

## Installation guide

1. Setup `tinyi.ini` in the root directory.

Example:

```ini
[instance]
mirror = "https://mirror.website.com;Mirror name" ; Optional.

[files]
upload_directory = "./userdata" ; Required.
upload_prefix = "xd" ; Optional.
file_id_length = "5" ; Optional.
file_id_char_pool = "ABCDEFabcdef0123456789" ; Optional.
```

4. Use reverse proxy *(Nginx, Apache, etc.)* for the project. See [configuration examples](#reverse-proxy-configurations).
5. ???
6. Profit! It should work.

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

    location ~ ^/[^/]+\.[a-zA-Z0-9]+$ {
            root /var/www/tinyiinstance;
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

