FROM php:8.2-apache

# Install PostgreSQL dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip

# Install PHP Extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql

# Enable Apache Rewrite
RUN a2enmod rewrite

# Copy Project Files
COPY . /var/www/html/

WORKDIR /var/www/html/

EXPOSE 80