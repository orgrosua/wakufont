#syntax=docker/dockerfile:1.4
# Adapted from https://github.com/api-platform/api-platform

# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target


# https://docs.docker.com/engine/reference/builder/#understand-how-arg-and-from-interact
ARG PHP_VERSION=8.1
ARG CADDY_VERSION=2

# "php" stage

# Build for production image
FROM php:${PHP_VERSION}-fpm-alpine AS symfony_php

ENV APP_ENV=prod

WORKDIR /srv/symfony

# persistent / runtime deps
RUN apk add --no-cache \
		acl \
		fcgi \
		file \
		gettext \
		git \
		nano \
	;

ARG EXT_AMQP_VERSION=1.11.0
ARG EXT_APCU_VERSION=5.1.22
ARG EXT_REDIS_VERSION=5.3.7

RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-data-full \
		icu-dev \
		libzip-dev \
		zlib-dev \
	; \
	\
	docker-php-ext-configure zip; \
	docker-php-ext-install -j$(nproc) \
		intl \
		zip \
	; \
	pecl install \
		apcu-${EXT_APCU_VERSION} \
	; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		opcache \
	; \
	\
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .symfony-phpexts-rundeps $runDeps; \
	\
	apk del .build-deps

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --link docker/php/conf.d/symfony.ini $PHP_INI_DIR/conf.d/
COPY --link docker/php/conf.d/symfony.prod.ini $PHP_INI_DIR/conf.d/

COPY --link docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
RUN mkdir -p /var/run/php

COPY --link docker/php/docker-healthcheck.sh /usr/local/bin/docker-healthcheck
RUN chmod +x /usr/local/bin/docker-healthcheck

HEALTHCHECK --interval=10s --timeout=3s --retries=3 CMD ["docker-healthcheck"]

COPY --link docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

COPY --from=composer:latest --link /usr/bin/composer /usr/bin/composer

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.* symfony.* ./
RUN set -eux; \
	composer install --prefer-dist --no-dev --no-scripts; \
	composer clear-cache

# copy sources
COPY --link . .
RUN rm -Rf docker/

RUN set -eux; \
	mkdir -p var/cache var/log var/tasks; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer symfony:dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync

COPY docker/php/cron.d/cron /etc/cron.d/cron
RUN chmod 0644 /etc/cron.d/cron

# Build for development image
FROM symfony_php AS symfony_php_dev

ENV APP_ENV=dev

VOLUME /srv/symfony/var

RUN rm $PHP_INI_DIR/conf.d/symfony.prod.ini; \
	mv "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php.ini-production"; \
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link docker/php/conf.d/symfony.dev.ini $PHP_INI_DIR/conf.d/

RUN rm -f .env.local.php

# "caddy" stage
# depends on the "php" stage above
FROM caddy:${CADDY_VERSION}-builder-alpine AS symfony_caddy_builder

# install Mercure and Vulcain modules
RUN xcaddy build \
    --with github.com/caddy-dns/cloudflare

# Caddy image
FROM caddy:${CADDY_VERSION}-alpine AS symfony_caddy

WORKDIR /srv/symfony

COPY --from=symfony_caddy_builder --link /usr/bin/caddy /usr/bin/caddy
COPY --from=symfony_php --link /srv/symfony/public public/
COPY --link docker/caddy/Caddyfile /etc/caddy/Caddyfile