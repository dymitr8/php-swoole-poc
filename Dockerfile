FROM phpswoole/swoole:php8.2

WORKDIR /app

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

EXPOSE 9501

CMD ["php", "server.php"]
