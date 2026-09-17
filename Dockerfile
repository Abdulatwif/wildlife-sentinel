FROM php:8.2-apache

# Install PDO MySQL extension (required for TiDB Cloud)
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite for .htaccess support
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
