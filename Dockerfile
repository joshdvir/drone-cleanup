FROM composer

WORKDIR /opt
COPY drone-cleanup.php /opt
RUN composer require "guzzlehttp/guzzle=^6.3"

CMD ["php", "drone-cleanup.php"]