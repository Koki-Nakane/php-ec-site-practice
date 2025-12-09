<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HomeController;
use App\Http\Request;
use App\Mapper\ProductMapper;
use App\Service\CsrfTokenManager;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    private ?PDO $pdo = null;
    private CsrfTokenManager $csrf;

    protected function setUp(): void
    {
        $this->csrf = new CsrfTokenManager();
    }

    public function testIndexRendersSearchForm(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite driver is not available.');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createProductSchema($this->pdo);
        $this->seedProduct($this->pdo);

        $products = new ProductMapper($this->pdo);
        $controller = new HomeController($products, $this->csrf);
        $response = $controller->index(new Request('GET', '/'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('id="post-search-form"', $response->body);
        $this->assertStringContainsString('name="q"', $response->body);
        $this->assertStringContainsString('/posts?q=', $response->body);
    }

    private function createProductSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            price INTEGER NOT NULL,
            description TEXT NOT NULL,
            stock INTEGER NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function seedProduct(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO products (name, price, description, stock, is_active, created_at, updated_at) VALUES (:name, :price, :description, :stock, :is_active, :created_at, :updated_at)');
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt->execute([
            ':name' => 'Sample',
            ':price' => 1000,
            ':description' => 'Desc',
            ':stock' => 3,
            ':is_active' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}
