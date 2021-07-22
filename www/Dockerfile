FROM php:7.4-apache

# install php extension dependencies
RUN apt-get update
RUN apt-get install -y libzip-dev libpng-dev libmcrypt-dev #mcrypt
# TODO: pecl installs mcrypt outside of ext_dir
#RUN pecl install mcrypt-stable \
#    && docker-php-ext-install mcrypt

RUN docker-php-ext-install mysqli bcmath zip gd #mcrypt

RUN mkdir -p /tmp/ps_themes_compiled
RUN chmod 777 /tmp/ps_themes_compiled