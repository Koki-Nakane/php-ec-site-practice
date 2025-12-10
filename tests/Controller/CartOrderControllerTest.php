<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CartController;
use App\Controller\OrderController;
use App\Http\Request;
use App\Model\Cart;
use App\Model\Order;
use App\Model\Product;
use App\Model\User;
use App\Service\CsrfTokenManager;
use App\Service\OrderCsvExporter;
use App\Service\TemplateRenderer;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CartOrderControllerTest extends TestCase
{
    private TemplateRenderer $views;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $this->views = new TemplateRenderer(__DIR__ . '/../../views');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function testCartShowRendersEmptyState(): void
    {
        $controller = new CartController($this->dummyProductMapper(), $this->views);

        $response = $controller->show(new Request('GET', '/cart'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('カートに商品はありません', $response->body);
    }

    public function testCartShowRendersItems(): void
    {
        $cart = new Cart();
        $product = new Product('Test Product', 1200, 'desc', 10, true, null, null, null, 1);
        $cart->addProduct($product, 2);
        $_SESSION['cart'] = $cart;

        $controller = new CartController($this->dummyProductMapper(), $this->views);

        $response = $controller->show(new Request('GET', '/cart'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Test Product', $response->body);
        $this->assertStringContainsString('2400', $response->body); // subtotal
    }

    public function testOrderCheckoutUsesTemplate(): void
    {
        $cart = new Cart();
        $product = new Product('Checkout Product', 1500, 'desc', 5, true, null, null, null, 5);
        $cart->addProduct($product, 1);
        $_SESSION['cart'] = $cart;
        $_SESSION['user_id'] = 42;

        $user = new User('Taro', 'taro@example.com', 'Pass123!', "Tokyo\nAddress", 42);

        $controller = new OrderController(
            new FakePdo(),
            $this->dummyUserMapper($user),
            $this->dummyProductMapper(),
            $this->dummyOrderMapper(),
            $this->dummyOrderCsvExporter(),
            new CsrfTokenManager(),
            $this->views,
        );

        $response = $controller->checkout(new Request('GET', '/checkout'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('注文内容の確認', $response->body);
        $this->assertStringContainsString('Checkout Product', $response->body);
        $this->assertStringContainsString('Taro', $response->body);
    }

    private function dummyProductMapper(): object
    {
        return new \App\Mapper\ProductMapper(new FakePdo());
    }

    private function dummyUserMapper(User $user): object
    {
        return new \App\Mapper\UserMapper(new FakePdo([
            'SELECT * FROM users WHERE id = ?' => function (?array $params) use ($user): array {
                return [
                    'id' => $params[0] ?? $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'password' => $user->getHashedPassword(),
                    'address' => $user->getAddress(),
                    'is_admin' => 0,
                    'deleted_at' => null,
                ];
            },
        ]));
    }

    private function dummyOrderMapper(): object
    {
        $pdo = new FakePdo();
        $productMapper = new \App\Mapper\ProductMapper(new FakePdo());
        return new \App\Mapper\OrderMapper($pdo, $productMapper);
    }

    private function dummyOrderCsvExporter(): OrderCsvExporter
    {
        return new OrderCsvExporter($this->dummyOrderMapper());
    }
}

class FakePdo extends \PDO
{
    /** @var array<string, callable|null> */
    private array $handlers;
    private ?FakeStatement $lastStatement = null;

    /** @param array<string, callable|null> $handlers */
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers;
    }

    public function prepare($query, $options = null): FakeStatement
    {
        $handler = $this->handlers[$query] ?? null;
        $this->lastStatement = new FakeStatement($handler);
        return $this->lastStatement;
    }

    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }
}

class FakeStatement extends \PDOStatement
{
    private $handler;
    private ?array $params = null;

    public function __construct(?callable $handler)
    {
        $this->handler = $handler;
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params;
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->handler === null) {
            return false;
        }

        return ($this->handler)($this->params);
    }
}
