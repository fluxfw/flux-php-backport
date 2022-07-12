# flux-php-backport

Port PHP 8.1 back to PHP 7.4

## Example

```dockerfile
FROM php:cli-alpine

RUN (mkdir -p /flux-php-backport && cd /flux-php-backport && wget -O - https://github.com/flux-eco/flux-php-backport/releases/download/%tag%/flux-php-backport-%tag%-build.tar.gz | tar -xz --strip-components=1)

RUN /flux-php-backport/bin/php-backport.php /path/to/xyz XYZ\\Libs\\FluxLegacyEnum
```
