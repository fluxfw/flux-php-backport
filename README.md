# flux-php-backport

Port PHP 8.1 back to PHP 7.4

## Example

```dockerfile
FROM docker-registry.fluxpublisher.ch/flux-php-backport:latest AS build_php_backport
COPY --from=xyz /path/to/xyz /code/xyz
RUN php-backport /code/xyz XYZ\\Libs\\FluxLegacyEnum
```

```dockerfile
COPY --from=build_php_backport /code/xyz /path/to/xyz
```
