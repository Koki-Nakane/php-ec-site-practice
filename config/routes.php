<?php

declare(strict_types=1);

use App\Controller\HomeController;
use App\Controller\OrderController;
use App\Http\Request;
use App\Http\Response;

/**
 * Build route table.
 * @return array{0:string,1:string,2:string,3:callable(Request):Response}[]
 */
return function (HomeController $home, OrderController $order): array {
    return [
        ['GET',  '/',              'web:public', [$home, 'index']],
        ['GET',  '/checkout',      'web:auth',   [$order, 'checkout']],
        ['POST', '/place_order',   'web:auth',   [$order, 'place']],
        ['GET',  '/api/products',  'api:public', [$home, 'apiProducts']],
    ];
};
