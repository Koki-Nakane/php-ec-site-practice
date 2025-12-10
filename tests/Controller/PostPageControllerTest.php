<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use App\Controller\PostPageController;
use App\Http\Request;
use App\Mapper\PostMapper;
use App\Mapper\UserMapper;
use App\Service\CsrfTokenManager;
use App\Service\PasswordValidator;
use App\Service\TemplateRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

final class PostPageControllerTest extends TestCase
{
    private PDO $pdo;
    private PostPageController $controller;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite driver is not available.');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->seedPosts();

        $views = new TemplateRenderer(__DIR__ . '/../../views');
        $auth = new AuthController(
            new UserMapper($this->pdo),
            new CsrfTokenManager(),
            new PasswordValidator(),
            $views
        );
        $this->controller = new PostPageController(new PostMapper($this->pdo), $views, $auth);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function testIndexShowsPublishedPostsOnlyForGuests(): void
    {
        $request = new Request('GET', '/blog', ['page' => 1]);
        $response = $this->controller->index($request);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Public Post', $response->body);
        $this->assertStringNotContainsString('Draft Post', $response->body);
        $this->assertStringContainsString('/blog/1', $response->body);
    }

    public function testShowReturns404ForDraftWhenGuest(): void
    {
        $request = new Request('GET', '/blog/2');
        $response = $this->controller->show($request);

        $this->assertSame(404, $response->status);
    }

    public function testShowAllowsAdminToViewDraft(): void
    {
        $adminId = $this->seedAdminUser();
        $_SESSION['user_id'] = $adminId;

        $request = new Request('GET', '/blog/2');
        $response = $this->controller->show($request);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Draft Post', $response->body);
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            address TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            deleted_at TEXT NULL
        )');

        $this->pdo->exec('CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            title TEXT NOT NULL,
            slug TEXT NOT NULL,
            content TEXT NOT NULL,
            status TEXT NOT NULL,
            comment_count INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        )');

        $this->pdo->exec('CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL
        )');

        $this->pdo->exec('CREATE TABLE post_categories (
            post_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL
        )');
    }

    private function seedPosts(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO posts (user_id, title, slug, content, status, comment_count, created_at, updated_at) VALUES (:user_id, :title, :slug, :content, :status, :comment_count, :created_at, :updated_at)');

        $rows = [
            [
                'user_id' => null,
                'title' => 'Public Post',
                'slug' => 'public-post',
                'content' => 'This is a published article.',
                'status' => 'published',
                'comment_count' => 2,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => null,
            ],
            [
                'user_id' => null,
                'title' => 'Draft Post',
                'slug' => 'draft-post',
                'content' => 'This is a draft.',
                'status' => 'draft',
                'comment_count' => 0,
                'created_at' => '2025-01-02 00:00:00',
                'updated_at' => null,
            ],
        ];

        foreach ($rows as $row) {
            $stmt->execute([
                ':user_id' => $row['user_id'],
                ':title' => $row['title'],
                ':slug' => $row['slug'],
                ':content' => $row['content'],
                ':status' => $row['status'],
                ':comment_count' => $row['comment_count'],
                ':created_at' => $row['created_at'],
                ':updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function seedAdminUser(): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password, address, is_admin, deleted_at) VALUES (:name, :email, :password, :address, :is_admin, NULL)');
        $stmt->execute([
            ':name' => 'adminuser',
            ':email' => 'admin@example.com',
            ':password' => password_hash('Pass123!', PASSWORD_DEFAULT),
            ':address' => 'somewhere',
            ':is_admin' => 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
