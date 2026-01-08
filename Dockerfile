ARG WORDPRESS_VERSION=5.9.0
ARG PHP_VERSION=7.4

FROM alpine:3.19.0 AS build-dependencies

WORKDIR /dependencies

ARG WOOCOMMERCE_VERSION=5.4.1

RUN apk add --no-cache wget unzip

RUN wget https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip -O woocommerce.zip \
    && unzip woocommerce.zip \
    && rm woocommerce.zip

FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache AS development

ENV WORDPRESS_DB_NAME=wordpress
ENV WORDPRESS_DB_USER=root
ENV WORDPRESS_DB_PASSWORD=wordpress
ENV WORDPRESS_DB_HOST=mysql:3306
ENV WORDPRESS_DEBUG=1

# Update sources to use Debian archive (Buster is EOL)
# Fix for Debian Buster repositories that have moved to archive
RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until && \
    if [ -f /etc/apt/sources.list ]; then \
    sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g' /etc/apt/sources.list; \
    sed -i 's|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' /etc/apt/sources.list; \
    fi && \
    if [ -d /etc/apt/sources.list.d ]; then \
    find /etc/apt/sources.list.d -type f -name "*.list" -exec sed -i 's|http://deb.debian.org/debian|http://archive.debian.org/debian|g' {} \; && \
    find /etc/apt/sources.list.d -type f -name "*.list" -exec sed -i 's|http://security.debian.org/debian-security|http://archive.debian.org/debian-security|g' {} \; ; \
    fi

RUN apt update && apt install -y less

# Set ServerName to suppress Apache warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN groupadd -g 1000 wp_user && \
    useradd -m -u 1000 -g wp_user wp_user

# Set proper permissions for WordPress directories
RUN chown -R wp_user:wp_user /var/www/html && \
    chown -R wp_user:wp_user /usr/src/wordpress

RUN sed -i "11i php_value upload_max_filesize 256M" /usr/src/wordpress/.htaccess && \
    sed -i "12i php_value post_max_size 256M" /usr/src/wordpress/.htaccess

WORKDIR /usr/src/wordpress

COPY --from=build-dependencies \
    /dependencies/woocommerce ./wp-content/plugins/woocommerce

# Note: USER directive removed - Apache needs to run as root initially
# The WordPress entrypoint script will handle proper user switching
WORKDIR /var/www/html
