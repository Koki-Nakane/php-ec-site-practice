<?php
/** @var App\Model\Post $post */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8'); ?> | ブログ記事</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'">
    <link rel="stylesheet" href="/css/posts.css">
</head>
<body>
    <header class="page-header">
        <div class="back-link"><a href="/blog">&larr; 記事一覧へ戻る</a></div>
        <div class="header-actions"><a href="/posts/search">検索ページへ</a></div>
    </header>

    <article class="post-detail">
        <h1 class="post-title"><?php echo htmlspecialchars($post->getTitle(), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="meta">
            <span>投稿日: <?php echo $post->getCreatedAt()->format('Y/m/d H:i'); ?></span>
            <?php if ($post->getUpdatedAt() !== null): ?>
                <span>更新日: <?php echo $post->getUpdatedAt()?->format('Y/m/d H:i'); ?></span>
            <?php endif; ?>
            <span>コメント: <?php echo $post->getCommentCount(); ?>件</span>
            <?php if ($post->getCategories() !== []): ?>
                <span>カテゴリ:
                    <?php echo htmlspecialchars(implode(', ', array_map(fn ($c) => $c->getName(), $post->getCategories())), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>
        </p>
        <div class="post-body">
            <?php echo $post->getHtmlContent(); ?>
        </div>
    </article>
</body>
</html>
