# ベースとなる公式イメージを指定
FROM php:8.2-apache

# cURL拡張機能のインストールと有効化に必要なライブラリをインストールし、
# cURL拡張機能をインストール・有効化するコマンド
RUN apt-get update && apt-get install -y \
        libcurl4-openssl-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        curl \
        zip \
        pdo_mysql