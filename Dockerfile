FROM registry.cn-hangzhou.aliyuncs.com/jcleng/library-alpine:3.20.1

ARG PHP_URL=https://gh-proxy.com/https://github.com/jcleng/staticphpbuild/releases/download/static-php_8.2_20261007042314/php-8.2_20261007042314
ARG https_proxy=
ARG http_proxy=

RUN apk add --no-cache curl \
 && for i in 1 2 3 4 5; do \
      curl -fL --retry 3 --retry-delay 2 --connect-timeout 30 "$PHP_URL" -o /usr/local/bin/php && break || sleep 3; \
    done \
 && chmod +x /usr/local/bin/php \
 && php -v

WORKDIR /app

COPY . /app

RUN mkdir -p /app/var

EXPOSE 7764

CMD ["php", "-S", "0.0.0.0:7764", "router.php"]
