FROM php:fpm

#Additional libraries for PHP extensions
#ICU for intl php ext
#zlib1g-dev for zip php ext
#libbz2-dev for bz2 php ext
#libxml2-dev for soap and wddx php ext
RUN apt-get update && \
    apt-get install -y \
        libicu-dev \
        zlib1g-dev \
        libbz2-dev \
        libxml2-dev \
	wkhtmltopdf

#besoin de ftp ? bz2 ? soap ? zip ? sockets ? wddx ? gettext ? calendar ?
#PHP extensions
#pdo_mysql for MySQL access
#bcmath mbstring for AMQP lib
RUN docker-php-ext-install \
        pdo_mysql \
        bcmath \
        mbstring \
        intl \
        json \
        opcache \
        gettext \
        ftp \
        zip \
        bz2 \
        sockets

RUN	php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/local/bin --filename=composer \
	&& chmod +x /usr/local/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/

