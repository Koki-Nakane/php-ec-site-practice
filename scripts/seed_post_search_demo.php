<?php

declare(strict_types=1);

use App\Mapper\PostMapper;
use App\Model\Category;
use App\Model\Database;
use App\Model\Post;
use DateTimeImmutable;
use PDO;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "このスクリプトは CLI からのみ実行できます\n");
    exit(1);
}

$pdo = Database::getInstance()->getConnection();
$mapper = new PostMapper($pdo);

/** @return array<string,int> slug => id */
function ensureCategories(PDO $pdo, array $categories): array
{
    $ids = [];
    $select = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug');
    $insert = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');

    foreach ($categories as $cat) {
        $slug = $cat['slug'];
        $name = $cat['name'];

        $select->execute([':slug' => $slug]);
        $id = $select->fetchColumn();

        if ($id === false) {
            $insert->execute([':name' => $name, ':slug' => $slug]);
            $id = (int) $pdo->lastInsertId();
            fwrite(STDOUT, "[categories] inserted: {$slug}\n");
        }

        $ids[$slug] = (int) $id;
    }

    return $ids;
}

function findPostBySlug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT id, created_at, comment_count FROM posts WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

$categoryDefinitions = [
    ['name' => 'お知らせ', 'slug' => 'news'],
    ['name' => '技術メモ', 'slug' => 'tech'],
    ['name' => '使い方ガイド', 'slug' => 'guide'],
];
$categoryIds = ensureCategories($pdo, $categoryDefinitions);

$posts = [
    [
        'title' => 'キーワード検索の使い方',
        'slug' => 'keyword-search-howto',
        'content' => 'ブログAPIのキーワード検索デモです。PHPとLIKE句で検索します。',
        'categories' => ['guide', 'tech'],
    ],
    [
        'title' => 'SQLインジェクション対策メモ',
        'slug' => 'sql-injection-notes',
        'content' => 'LIKEのエスケープやバインドを含む検索の注意点をまとめました。',
        'categories' => ['tech'],
    ],
    [
        'title' => '冬のセール情報',
        'slug' => 'winter-sale-news',
        'content' => 'セールのお知らせと人気商品の紹介。',
        'categories' => ['news'],
    ],
];

foreach ($posts as $seed) {
    $existing = findPostBySlug($pdo, $seed['slug']);

    $cats = [];
    foreach ($seed['categories'] as $slug) {
        if (!isset($categoryIds[$slug])) {
            fwrite(STDERR, "カテゴリが見つかりません: {$slug}\n");
            exit(1);
        }
        $cats[] = new Category(
            name: array_values(array_filter($categoryDefinitions, fn ($c) => $c['slug'] === $slug))[0]['name'],
            slug: $slug,
            id: $categoryIds[$slug]
        );
    }

    $createdAt = $existing !== null && isset($existing['created_at'])
        ? new DateTimeImmutable((string) $existing['created_at'])
        : null;

    $post = new Post(
        $seed['title'],
        $seed['content'],
        $seed['slug'],
        authorId: null,
        id: $existing !== null ? (int) $existing['id'] : null,
        createdAt: $createdAt,
        updatedAt: null,
        categories: $cats,
        status: 'published',
        commentCount: $existing !== null ? (int) $existing['comment_count'] : 0,
    );

    if ($existing === null) {
        $mapper->insert($post);
        fwrite(STDOUT, "[posts] inserted: {$seed['slug']}\n");
    } else {
        $mapper->update($post);
        fwrite(STDOUT, "[posts] updated: {$seed['slug']}\n");
    }
}

fwrite(STDOUT, "ブログ記事のデモデータを投入しました。\n");
