# Build stage
FROM masrodjie/php81:slim AS build

COPY --chown=www-data . /app/

WORKDIR /app/

USER www-data
RUN composer install --no-cache --no-dev --optimize-autoloader --no-interaction --no-progress

# Production stage
FROM masrodjie/php81:slim

COPY --from=build --chown=www-data /app/ /var/www/html/

COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY ./docker/rr.yaml /var/www/html/.rr.yaml

RUN rm -Rf /var/www/html/Dockerfile /var/www/html/docker /var/www/html/.styleci.yml /var/www/html/.gitlab-ci.yml /var/www/html/.gitignore /var/www/html/.gitattributes /var/www/html/.git

COPY docker/php.ini /etc/php/8.1/cli/php.ini
RUN usermod -s /bin/bash www-data
RUN chown -R www-data.www-data /var/www

USER www-data
RUN /var/www/html/vendor/bin/rr get-binary -q

USER root

WORKDIR /var/www/html

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

HEALTHCHECK --interval=10s --timeout=1s CMD curl --fail http://localhost:8001/health?plugin=http || exit 1