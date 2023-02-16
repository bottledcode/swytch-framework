FROM registry.bottled.codes/r/library/php:8.2-cli

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions mbstring xdebug dom
