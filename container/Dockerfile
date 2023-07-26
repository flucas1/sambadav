FROM php:7.0-apache

VOLUME ["/var/www/htdocs/webfolders/config","/etc/ssl/localcerts"]
ENV SAMBADAV_ENABLE_SSL=true
ENV SAMBADAV_SSL_REDIRECT=false
WORKDIR /var/www/htdocs/webfolders
RUN mkdir log data && chown daemon:root log data && chmod 0750 log data

RUN apt-get update && apt-get install -y smbclient
COPY ./entrypoint.sh /bin/entrypoint.sh
RUN chmod +x /bin/entrypoint.sh

RUN a2enmod rewrite ssl

COPY ./apache2/sambadav.conf /etc/apache2/sites-available/sambadav.conf
COPY ./apache2/sambadav-ssl.conf /etc/apache2/sites-available/sambadav-ssl.conf
COPY ./sambadav/src /var/www/htdocs/webfolders

RUN a2ensite sambadav
RUN a2dissite 000-default default-ssl

ENTRYPOINT "/bin/entrypoint.sh"
