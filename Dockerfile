FROM revenuewire/docker-php7-alpine:v1

RUN apk update && apk add php7-xdebug php7-dom php7-tokenizer php7-phar php7-zlib && apk upgrade
RUN echo "zend_extension=xdebug.so" > /etc/php7/conf.d/xdebug.ini
RUN echo "apc.enable_cli=1" >> /etc/php7/conf.d/apcu.ini
RUN echo "apc.use_request_time=0" >> /etc/php7/conf.d/apcu.ini
