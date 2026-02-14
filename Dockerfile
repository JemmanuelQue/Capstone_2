FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Install common PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy application code to HRIS subfolder
COPY . /var/www/html/HRIS

# Change Apache DocumentRoot to point to HRIS folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/HRIS

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]