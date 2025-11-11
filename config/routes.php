<?php

declare(strict_types=1);

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\ProductController as AdminProductController;
use App\Controller\Admin\UserController as AdminUserController;
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
return function (
    HomeController $home,
    OrderController $order,
    CartController $cart,
    AuthController $auth,
    DashboardController $adminDashboard,
    AdminProductController $adminProducts,
    AdminUserController $adminUsers,
): array {
    return [
        ['GET',  '/',              'web:public', [$home, 'index']],
        ['GET',  '/cart',          'web:public', [$cart, 'show']],
        ['POST', '/add_to_cart',   'web:public', [$cart, 'add']],
        ['GET',  '/login',         'web:public', [$auth, 'showLogin']],
        ['POST', '/login',         'web:public', [$auth, 'handleLogin']],
        ['GET',  '/orders',        'web:auth',   [$order, 'orders']],
        ['POST', '/orders/export', 'web:auth',   [$order, 'exportMonthlyCsv']],
        ['GET',  '/checkout',      'web:auth',   [$order, 'checkout']],
        ['POST', '/place_order',   'web:auth',   [$order, 'place']],
        ['GET',  '/order_complete','web:auth',   [$order, 'orderComplete']],
        ['GET',  '/api/products',  'api:public', [$home, 'apiProducts']],
        ['GET',  '/admin',                  'web:admin', [$adminDashboard, 'index']],
        ['GET',  '/admin/products',         'web:admin', [$adminProducts, 'index']],
        ['GET',  '/admin/products/edit',    'web:admin', [$adminProducts, 'edit']],
        ['POST', '/admin/products/update',  'web:admin', [$adminProducts, 'update']],
        ['POST', '/admin/products/toggle',  'web:admin', [$adminProducts, 'toggleActive']],
        ['GET',  '/admin/users',            'web:admin', [$adminUsers, 'index']],
        ['GET',  '/admin/users/edit',       'web:admin', [$adminUsers, 'edit']],
        ['POST', '/admin/users/update',     'web:admin', [$adminUsers, 'update']],
        ['POST', '/admin/users/toggle-admin',     'web:admin', [$adminUsers, 'toggleAdmin']],
        ['POST', '/admin/users/toggle-deletion',  'web:admin', [$adminUsers, 'toggleDeletion']],
    ];
};
