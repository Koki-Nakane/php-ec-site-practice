<?php

declare(strict_types=1);

use App\Mapper\OrderMapper;
use App\Mapper\ProductMapper;
use App\Mapper\UserMapper;
use App\Model\Cart;
use App\Model\Enum\OrderStatus;
use App\Model\Order;
use App\Model\Product;
use App\Model\User;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "このスクリプトは CLI からのみ実行できます\n");
    exit(1);
}

$container = require __DIR__ . '/../config/container.php';

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);
/** @var UserMapper $userMapper */
$userMapper = $container->get(UserMapper::class);
/** @var ProductMapper $productMapper */
$productMapper = $container->get(ProductMapper::class);
/** @var OrderMapper $orderMapper */
$orderMapper = $container->get(OrderMapper::class);

ensureOrderSchema($pdo);

$users = seedUsers($userMapper, [
    [
        'name' => 'admin_manager',
        'email' => 'admin.manager@example.com',
        'password' => 'AdminPass123!',
        'address' => '東京都千代田区1-1-1 管理テストビル10F',
        'is_admin' => true,
    ],
    [
        'name' => 'standard_user01',
        'email' => 'standard.user01@example.com',
        'password' => 'UserPass123!',
        'address' => '神奈川県横浜市1-2-3 テストタワー802',
        'is_admin' => false,
    ],
    [
        'name' => 'vip_customer01',
        'email' => 'vip.user01@example.com',
        'password' => 'VipPass123!',
        'address' => '東京都渋谷区4-5-6 プレミアムレジデンス1502',
        'is_admin' => false,
    ],
    [
        'name' => 'archived_user01',
        'email' => 'archived.user01@example.com',
        'password' => 'Archive123!',
        'address' => '大阪府大阪市北区7-8-9 アーカイブマンション301',
        'is_admin' => false,
    ],
]);

[$products, $productsPendingDeletion] = seedProducts($pdo, $productMapper, [
    [
        'name' => 'エスプレッソマシン（管理テスト）',
        'price' => 59800,
        'description' => '管理画面テスト用のエスプレッソマシンです。',
        'stock' => 5,
        'is_active' => true,
        'delete_after' => null,
    ],
    [
        'name' => 'ハンドドリップセット（管理テスト）',
        'price' => 7800,
        'description' => '管理画面テスト用のハンドドリップスターターセットです。',
        'stock' => 20,
        'is_active' => false,
        'delete_after' => null,
    ],
    [
        'name' => '限定コーヒー豆（管理テスト）',
        'price' => 2400,
        'description' => '季節限定ブレンド。管理画面テスト用のデータです。',
        'stock' => 40,
        'is_active' => true,
        'delete_after' => new DateTimeImmutable(),
    ],
]);

$orders = seedOrders(
    $pdo,
    $orderMapper,
    $users,
    $products,
    [
        [
            'tag' => 'ADMIN-TEST-ORDER-001',
            'user_email' => 'standard.user01@example.com',
            'items' => [
                ['product_name' => 'エスプレッソマシン（管理テスト）', 'quantity' => 1],
                ['product_name' => 'ハンドドリップセット（管理テスト）', 'quantity' => 1],
            ],
            'status' => OrderStatus::PROCESSING,
            'shipping_address' => "東京都千代田区1-1-1 管理テストビル10F\n[ADMIN-TEST-ORDER-001]",
            'deleted' => false,
            'deleted_at' => null,
        ],
        [
            'tag' => 'ADMIN-TEST-ORDER-002',
            'user_email' => 'vip.user01@example.com',
            'items' => [
                ['product_name' => 'エスプレッソマシン（管理テスト）', 'quantity' => 1],
            ],
            'status' => OrderStatus::SHIPPED,
            'shipping_address' => "東京都渋谷区4-5-6 プレミアムレジデンス1502\n[ADMIN-TEST-ORDER-002]",
            'deleted' => false,
            'deleted_at' => null,
        ],
        [
            'tag' => 'ADMIN-TEST-ORDER-003',
            'user_email' => 'standard.user01@example.com',
            'items' => [
                ['product_name' => '限定コーヒー豆（管理テスト）', 'quantity' => 2],
            ],
            'status' => OrderStatus::COMPLETED,
            'shipping_address' => "神奈川県横浜市1-2-3 テストタワー802\n[ADMIN-TEST-ORDER-003]",
            'deleted' => false,
            'deleted_at' => null,
        ],
        [
            'tag' => 'ADMIN-TEST-ORDER-004',
            'user_email' => 'archived.user01@example.com',
            'items' => [
                ['product_name' => 'ハンドドリップセット（管理テスト）', 'quantity' => 1],
            ],
            'status' => OrderStatus::CANCELED,
            'shipping_address' => "大阪府大阪市北区7-8-9 アーカイブマンション301\n[ADMIN-TEST-ORDER-004]",
            'deleted' => true,
            'deleted_at' => new DateTimeImmutable('-1 day'),
        ],
    ]
);

markPostOrderDeletions($productMapper, $products, $productsPendingDeletion);

if (isset($users['archived.user01@example.com']) && !$users['archived.user01@example.com']->isDeleted()) {
    $updated = $userMapper->markDeleted((int) $users['archived.user01@example.com']->getId(), new DateTimeImmutable('-3 days'));
    if ($updated !== null) {
        $users['archived.user01@example.com'] = $updated;
    }
    printf("[users] 論理削除 (%s)\n", 'archived.user01@example.com');
}

reportSummary($users, $products, $orders);

/**
 * @param array<int, array{name:string,email:string,password:string,address:string,is_admin:bool}> $seeds
 * @return array<string, User>
 */
function seedUsers(UserMapper $userMapper, array $seeds): array
{
    $results = [];

    foreach ($seeds as $seed) {
        $existing = $userMapper->findByEmail($seed['email']);

        $user = new User(
            $seed['name'],
            $seed['email'],
            $seed['password'],
            $seed['address'],
            $existing?->getId(),
            $seed['is_admin']
        );

        if ($seed['is_admin']) {
            $user->promoteToAdmin();
        } else {
            $user->demoteFromAdmin();
        }

        $userMapper->save($user);
        $results[$seed['email']] = $userMapper->findByEmail($seed['email']);

        printf("[users] %s (%s) -> ID %d\n", $existing ? '更新' : '作成', $seed['email'], $results[$seed['email']]->getId() ?? 0);
    }

    return $results;
}

/**
 * @param array<int, array{name:string,price:float,description:string,stock:int,is_active:bool,delete_after:?DateTimeImmutable}> $seeds
 * @return array{0:array<string, Product>,1:array<int,array{product:Product,deleted_at:DateTimeImmutable}>}
 */
function seedProducts(PDO $pdo, ProductMapper $productMapper, array $seeds): array
{
    $results = [];
    $pendingDeletion = [];

    foreach ($seeds as $seed) {
        $row = findProductRowByName($pdo, $seed['name']);
        $needsFinalDeletion = $seed['delete_after'] instanceof DateTimeImmutable;

        if ($row === null) {
            $product = Product::createNew(
                $seed['name'],
                (float) $seed['price'],
                $seed['description'],
                (int) $seed['stock'],
                $seed['is_active']
            );
            $productMapper->save($product);
            $status = '作成';
        } else {
            $product = Product::rehydrate($row);

            if ($product->isDeleted()) {
                $product = $productMapper->restore((int) $product->getId(), new DateTimeImmutable('-1 minute'));
            }

            $product->rename($seed['name']);
            $product->changePrice((float) $seed['price']);
            $product->changeDescription($seed['description']);
            $product->changeStock((int) $seed['stock']);

            if ($seed['is_active']) {
                $product->activate();
            } else {
                $product->deactivate();
            }

            $productMapper->save($product);
            $status = '更新';
        }

        if ($needsFinalDeletion) {
            $pendingDeletion[] = [
                'product' => $product,
                'deleted_at' => $seed['delete_after'],
            ];
        }

        $final = $productMapper->findIncludingDeleted((int) $product->getId());
        if ($final === null) {
            throw new RuntimeException('商品情報の取得に失敗しました: ' . $seed['name']);
        }

        $results[$seed['name']] = $final;
        printf("[products] %s (%s) -> ID %d\n", $status, $seed['name'], $final->getId() ?? 0);
    }

    return [$results, $pendingDeletion];
}

/**
 * @param array<int, array{tag:string,user_email:string,items:array<int,array{product_name:string,quantity:int}>,status:int,shipping_address:string,deleted:bool,deleted_at:?DateTimeImmutable}> $seeds
 * @return array<string, Order>
 */
function seedOrders(PDO $pdo, OrderMapper $orderMapper, array $users, array $products, array $seeds): array
{
    $results = [];

    foreach ($seeds as $seed) {
        $user = $users[$seed['user_email']] ?? null;
        if ($user === null) {
            throw new RuntimeException('ユーザーが見つかりません: ' . $seed['user_email']);
        }

        $orderId = findOrderIdByTag($pdo, $seed['tag']);

        if ($orderId !== null) {
            $order = $orderMapper->findForAdmin($orderId);
            if ($order === null) {
                throw new RuntimeException('注文情報の取得に失敗しました: ' . $seed['tag']);
            }

            $order->changeShippingAddress($seed['shipping_address']);
            $order->setStatus($seed['status']);
            $orderMapper->save($order);

            if ($seed['deleted']) {
                $orderMapper->markDeleted($orderId, $seed['deleted_at'] ?? new DateTimeImmutable('-10 minutes'));
            } else {
                $orderMapper->restore($orderId, new DateTimeImmutable('-5 minutes'));
            }

            $results[$seed['tag']] = $orderMapper->findForAdmin($orderId);
            printf("[orders] 更新 (%s) -> ID %d\n", $seed['tag'], $orderId);
            continue;
        }

        $cart = new Cart();
        foreach ($seed['items'] as $item) {
            $product = $products[$item['product_name']] ?? null;
            if ($product === null) {
                throw new RuntimeException('商品が見つかりません: ' . $item['product_name']);
            }

            $cart->addProduct($product, (int) $item['quantity']);
        }

        $order = new Order($user, $cart, null, $seed['shipping_address']);
        $order->setStatus($seed['status']);
        $orderMapper->save($order);

        $orderId = (int) $order->getId();

        if ($seed['deleted']) {
            $orderMapper->markDeleted($orderId, $seed['deleted_at'] ?? new DateTimeImmutable('-10 minutes'));
        }

        $results[$seed['tag']] = $orderMapper->findForAdmin($orderId);
        printf("[orders] 作成 (%s) -> ID %d\n", $seed['tag'], $orderId);
    }

    return $results;
}

function markPostOrderDeletions(ProductMapper $productMapper, array &$products, array $pendingDeletion): void
{
    foreach ($pendingDeletion as $entry) {
        $product = $entry['product'];
        $deletedAt = $entry['deleted_at'];
        $productId = $product->getId();

        if ($productId === null) {
            continue;
        }

        $productMapper->markDeleted($productId, $deletedAt);
        $latest = $productMapper->findIncludingDeleted($productId);
        if ($latest !== null) {
            $products[$latest->getName()] = $latest;
        }
        printf("[products] 論理削除 (%s)\n", $product->getName());
    }
}

function reportSummary(array $users, array $products, array $orders): void
{
    echo "\n--- シードデータ概要 -----------------------------\n";
    echo "[ユーザー]\n";
    foreach ($users as $email => $user) {
        printf("  - %s (ID %d)%s\n", $email, $user->getId() ?? 0, $user->isAdmin() ? ' [admin]' : '');
    }

    echo "\n[商品]\n";
    foreach ($products as $name => $product) {
        printf(
            "  - %s (ID %d) 状態: %s / 削除: %s\n",
            $name,
            $product->getId() ?? 0,
            $product->isActive() ? '公開中' : '非公開',
            $product->isDeleted() ? '削除済' : '有効'
        );
    }

    echo "\n[注文]\n";
    foreach ($orders as $tag => $order) {
        printf(
            "  - %s (ID %d) ステータス: %d / 削除: %s\n",
            $tag,
            $order?->getId() ?? 0,
            $order?->getStatus() ?? -1,
            $order?->isDeleted() ? '削除済' : '有効'
        );
    }
    echo "-----------------------------------------------\n";
}

function findProductRowByName(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function findOrderIdByTag(PDO $pdo, string $tag): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE shipping_address LIKE :tag LIMIT 1');
    $stmt->execute([':tag' => '%' . $tag . '%']);
    $result = $stmt->fetchColumn();

    return $result === false ? null : (int) $result;
}

function ensureOrderSchema(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status'"
    );
    $stmt->execute();

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $path = __DIR__ . '/../database/migrations/2025_11_11_090000_add_status_and_deleted_at_to_orders.sql';
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('ordersテーブル用マイグレーションの読み込みに失敗しました: ' . $path);
    }

    $pdo->exec($sql);
    echo "[schema] orders テーブルを最新構成に更新しました。\n";
}
