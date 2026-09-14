FROM php:8.3-apache
RUN docker-php-ext-install pdo_pgsql && a2enmod rewrite && rm /etc/apache2/sites-enabled/000-default.conf
COPY docker/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY . /var/www/html/QuizWeb
RUN mkdir -p /var/www/html/QuizWeb/data/uploads/announcements && chown -R www-data:www-data /var/www/html/QuizWeb/data
EXPOSE 80
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && apache2-foreground"]
