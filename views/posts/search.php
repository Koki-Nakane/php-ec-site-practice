<?php
/** @var App\Model\Post[] $posts */
/** @var string $query */
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
    <title>ブログ記事検索</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'">
    <link rel="stylesheet" href="/css/posts.css">
</head>
<body>
    <header class="page-header">
        <div class="back-link"><a href="/">&larr; トップへ戻る</a></div>
        <h1>ブログ記事検索</h1>
    </header>

    <section class="search-box">
        <form action="/posts/search" method="get" class="search-form">
            <label for="q" class="visually-hidden">キーワード</label>
            <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="例: セキュリティ 予約">
            <button type="submit">検索</button>
        </form>
    </section>

    <main class="results">
        <?php if ($query === ''): ?>
            <p class="muted">キーワードを入力して検索してください。</p>
        <?php elseif ($total === 0): ?>
            <p>「<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>」に一致する記事は見つかりませんでした。</p>
        <?php else: ?>
            <p class="summary">「<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>」の検索結果 <?php echo $total; ?> 件（<?php echo $page; ?> / <?php echo $totalPages; ?> ページ）</p>
            <ol class="post-results">
                <?php foreach ($posts as $post): ?>
                    <li class="post-card">
                        <h2><?php echo htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8'); ?></h2>
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
                    $excerpt = mb_substr($content, 0, 160, 'UTF-8');
                    $hasMore = mb_strlen($content, 'UTF-8') > mb_strlen($excerpt, 'UTF-8');
                    ?>
                        <p class="excerpt"><?php echo nl2br(htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8')); ?><?php if ($hasMore): ?>…<?php endif; ?></p>
                        <p class="note">※ 記事の詳細ページは後続タスクで追加予定です。</p>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="検索結果のページ切り替え">
                    <?php
                    $buildUrl = function (int $targetPage) use ($query, $perPage): string {
                        $params = ['q' => $query, 'page' => $targetPage];
                        if ($perPage !== 10) {
                            $params['perPage'] = $perPage;
                        }
                        return '/posts/search?' . http_build_query($params);
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
