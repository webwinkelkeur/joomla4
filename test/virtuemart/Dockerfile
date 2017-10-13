FROM php:5.6-apache

RUN apt-get update && \
    apt-get install -y unzip libmcrypt-dev && \
    docker-php-ext-install -j`nproc` mysqli mcrypt

RUN cd /tmp && \
    curl -o package.zip 'http://dev.virtuemart.net/attachments/download/1092/VirtueMart3.2.4_Joomla_3.7.5-Stable-Full_Package.zip' && \
    cd /var/www/html && \
    unzip /tmp/package.zip
