# ベースとなる公式イメージを指定
FROM php:8.3-apache

# 本番環境用のphp.iniをコピーして、設定ファイルの読み込みを確実にする
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# cURL拡張機能のインストールと有効化に必要なライブラリをインストールし、
# cURL拡張機能をインストール・有効化するコマンド
RUN apt-get update && apt-get install -y \
        libcurl4-openssl-dev \
        libzip-dev \
        unzip \
        git \
        autoconf \
        pkg-config \
        build-essential \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-install \
        curl \
        zip \
        pdo_mysql \
    && php -m | grep -i xdebug \
    && apt-get purge -y --auto-remove autoconf pkg-config build-essential

# Xdebug本体の有効化ファイル (docker-php-ext-enable が生成) を残し、設定は別ファイルに分離
COPY ./.devcontainer/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Non-root user setup (match host UID/GID 1000) ---
RUN groupadd -g 1000 appgroup \
    && useradd -u 1000 -g appgroup -m appuser \
    && mkdir -p /var/www/html \
    && chown -R appuser:appgroup /var/www/html

# Entrypoint script to adjust ownership of any root-owned leftover files (first run after bind mount)
COPY ./.devcontainer/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && sed -ri -e 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's#<Directory /var/www/>#<Directory /var/www/html/public/>#g' /etc/apache2/apache2.conf \
    && sed -ri -e 's#<Directory /var/www/html/>#<Directory /var/www/html/public/>#g' /etc/apache2/apache2.conf \
    && a2enmod rewrite

USER appuser

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]