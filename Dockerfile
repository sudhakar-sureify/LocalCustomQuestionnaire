FROM php:8.0-apache

# Install required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Xdebug for debugging
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set a non-root user to avoid permission issues
RUN useradd -m dockeruser
USER dockeruser

# Set working directory
WORKDIR /var/www/html

# Copy project files with correct ownership (prevents locking)
COPY --chown=dockeruser:dockeruser . /var/www/html/

# Copy Xdebug configuration
COPY docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Expose Apache port
EXPOSE 80
