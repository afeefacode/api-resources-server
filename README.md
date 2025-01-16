# api-resources-server

## Testing

The very first time you need to pull the MariaDB docker image: `docker pull mariadb`.

```php
cd tests
phpunit
```

## Debugging

run xdebug with this configuration:

```ini
zend_extension=xdebug.so

xdebug.start_with_request = yes
xdebug.mode = debug
xdebug.client_port = 9000
xdebug.log = /var/log/xdebug.log
```
