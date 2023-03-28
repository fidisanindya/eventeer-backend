FROM trafex/php-nginx:3.0.0

LABEL "maintainer"="M Arnas Risqianto <arnas@digitalamoeba.id>"


USER root
RUN apk add --no-cache php81-tokenizer php81-xmlwriter php81-redis php81-pdo php81-pdo_mysql php-fileinfo
RUN rm -rf /var/cache/apk/*
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN rm /var/www/html/*
RUN chown -R nobody.nobody /var/www /run /var/lib/nginx /var/log/nginx
COPY --chown=nobody . /var/www/html
RUN rm -Rf /var/www/html/docker /var/www/html/public/uploads

USER nobody

RUN composer update --optimize-autoloader --no-interaction --no-progress 

