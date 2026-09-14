FROM php:8.3-apache
RUN docker-php-ext-install pdo_pgsql && a2enmod rewrite && rm /etc/apache2/sites-enabled/000-default.conf
COPY docker/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY docker/start-apache.sh /usr/local/bin/start-apache
COPY . /var/www/html/QuizWeb
RUN chmod +x /usr/local/bin/start-apache \
    && mkdir -p /var/www/html/QuizWeb/data/uploads/announcements \
    && chown -R www-data:www-data /var/www/html/QuizWeb/data
EXPOSE 80
CMD ["start-apache"]
