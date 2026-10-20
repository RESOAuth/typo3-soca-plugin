FROM docker.io/library/composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS dependencies
COPY . /plugin
WORKDIR /host
COPY demo/project/composer.json demo/project/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --ignore-platform-req=ext-*
FROM docker.io/library/nextcloud:33-apache@sha256:5c121a3b6be3391ff078a5b69cea922f34a7470f1dfb2ca45d4521d8772d076c
WORKDIR /var/www/typo3
COPY --from=dependencies /host /var/www/typo3
COPY demo/setup.php /opt/soca-demo-setup.php
COPY demo/entrypoint.sh /opt/soca-demo-entrypoint.sh
COPY demo/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod ssl rewrite && mkdir -p /run/soca && chown -R www-data:www-data /var/www/typo3 && chmod +x /opt/soca-demo-entrypoint.sh
ENTRYPOINT ["/opt/soca-demo-entrypoint.sh"]
CMD ["apache2-foreground"]
