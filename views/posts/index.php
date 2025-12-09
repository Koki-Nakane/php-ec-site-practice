<?php
/** @var App\Model\Post[] $posts */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var int $totalPages */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ブログ記事一覧</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'">
    <link rel="stylesheet" href="/css/posts.css">
</head>
<body>
    <header class="page-header">
        <div class="back-link"><a href="/">&larr; トップへ戻る</a></div>
        <h1>ブログ記事一覧</h1>
        <div class="header-actions"><a href="/posts/search">キーワード検索へ</a></div>
    </header>

    <main class="results">
        <?php if ($total === 0): ?>
            <p class="muted">まだ記事がありません。</p>
        <?php else: ?>
            <p class="summary">全 <?php echo $total; ?> 件（<?php echo $page; ?> / <?php echo $totalPages; ?> ページ）</p>
            <ol class="post-results post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-card">
                        <h2>
                            <a href="/blog/<?php echo $post->getId(); ?>">
                                <?php echo htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </h2>
                        <p class="meta">
                            <span>投稿日: <?php echo $post->getCreatedAt()->format('Y/m/d H:i'); ?></span>
                            <span>コメント: <?php echo $post->getCommentCount(); ?>件</span>
                            <?php if ($post->getCategories() !== []): ?>
                                <span>カテゴリ:
                                    <?php echo htmlspecialchars(implode(', ', array_map(fn ($c) => $c->getName(), $post->getCategories())), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <?php
                        $content = $post->getContent();
                    $excerpt = mb_substr($content, 0, 200, 'UTF-8');
                    $hasMore = mb_strlen($content, 'UTF-8') > mb_strlen($excerpt, 'UTF-8');
                    ?>
                        <p class="excerpt">
                            <?php echo nl2br(htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8')); ?><?php if ($hasMore): ?>…<?php endif; ?>
                        </p>
                        <p class="read-more"><a href="/blog/<?php echo $post->getId(); ?>">続きを読む</a></p>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="記事一覧のページ切り替え">
                    <?php
                    $buildUrl = function (int $targetPage) use ($perPage): string {
                        $params = ['page' => $targetPage];
                        if ($perPage !== 10) {
                            $params['perPage'] = $perPage;
                        }
                        $query = http_build_query($params);
                        return $query === '' ? '/blog' : '/blog?' . $query;
                    };
                ?>
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $buildUrl($page - 1); ?>" rel="prev">前のページ</a>
                    <?php endif; ?>
                    <span class="current"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo $buildUrl($page + 1); ?>" rel="next">次のページ</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
