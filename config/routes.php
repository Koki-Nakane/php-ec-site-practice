<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\CartController;
use App\Controller\HomeController;
use App\Controller\OrderController;
use App\Http\Request;
use App\Http\Response;

/**
 * Build route table.
 * @return array{0:string,1:string,2:string,3:callable(Request):Response}[]
 */
return function (HomeController $home, OrderController $order, CartController $cart, AuthController $auth): array {
    return [
        ['GET',  '/',              'web:public', [$home, 'index']],
        ['GET',  '/cart',          'web:public', [$cart, 'show']],
        ['POST', '/add_to_cart',   'web:public', [$cart, 'add']],
        ['GET',  '/login',         'web:public', [$auth, 'showLogin']],
        ['POST', '/login',         'web:public', [$auth, 'handleLogin']],
        ['GET',  '/checkout',      'web:auth',   [$order, 'checkout']],
        ['POST', '/place_order',   'web:auth',   [$order, 'place']],
        ['GET',  '/order_complete','web:auth',   [$order, 'orderComplete']],
        ['GET',  '/api/products',  'api:public', [$home, 'apiProducts']],
    ];
};
