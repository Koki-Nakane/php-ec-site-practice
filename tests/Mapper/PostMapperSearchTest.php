<?php

declare(strict_types=1);

namespace App\Tests\Mapper;

use App\Mapper\PostFilter;
use App\Mapper\PostMapper;
use PDO;
use PHPUnit\Framework\TestCase;

final class PostMapperSearchTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite driver is not available.');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->seedPosts();
    }

    public function testSearchMatchesTitleAndContent(): void
    {
        $mapper = new PostMapper($this->pdo);
        $filter = new PostFilter(query: 'PHP', perPage: 10);

        $result = $mapper->findAll($filter);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['posts']);
        $this->assertSame('hello-world', $result['posts'][0]->getSlug());
    }

    public function testSearchEscapesWildcardAndInjectionLikePatterns(): void
    {
        $mapper = new PostMapper($this->pdo);
        $filter = new PostFilter(query: '%', perPage: 10);

        $result = $mapper->findAll($filter);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['posts']);
    }

    private function createSchema(): void
    {
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
                'user_id' => 1,
                'title' => 'Hello World',
                'slug' => 'hello-world',
                'content' => 'This post talks about PHP content.',
                'status' => 'published',
                'comment_count' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => null,
            ],
            [
                'user_id' => 2,
                'title' => 'SQL tips',
                'slug' => 'sql-tips',
                'content' => 'LIKE clauses and escaping patterns.',
                'status' => 'published',
                'comment_count' => 1,
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
}
