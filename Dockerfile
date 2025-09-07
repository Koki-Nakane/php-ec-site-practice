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