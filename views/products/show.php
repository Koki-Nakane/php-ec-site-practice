<?php
/** @var App\Model\Product $product */
/** @var array{items:array<int,array{review:App\Model\Review,authorName:string}>,total:int,average:?float,page:int,perPage:int,totalPages:int} $reviews */
/** @var App\Model\User|null $user */
/** @var string|null $eligibility */
/** @var App\Model\Review|null $userReview */
/** @var string $reviewToken */
/** @var string $deleteToken */
/** @var array<int,string> $flash */
/** @var bool $isAdmin */

$average = $reviews['average'];
$totalReviews = $reviews['total'];
$currentPage = $reviews['page'];
$totalPages = $reviews['totalPages'];
$perPage = $reviews['perPage'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?> | 商品詳細</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; margin: 2rem; background-color: #f8f9fa; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        header a { color: #0d6efd; text-decoration: none; }
        header a:hover { text-decoration: underline; }
        .card { background: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(28,28,28,0.1); margin-bottom: 1.5rem; }
        .reviews-summary { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .summary-box { flex: 1 1 200px; background: #f1f3f5; border-radius: 10px; padding: 1rem; }
        .flash { background: #fff3cd; color: #664d03; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        form.review-form label { display: block; font-weight: 600; margin-top: 1rem; }
        form.review-form input[type="text"],
        form.review-form textarea,
        form.review-form select { width: 100%; padding: 0.5rem; border-radius: 8px; border: 1px solid #ced4da; }
        form.review-form button { margin-top: 1rem; padding: 0.75rem 1.25rem; border: none; border-radius: 8px; background: #0d6efd; color: #fff; cursor: pointer; }
        form.review-form button:disabled { background: #adb5bd; cursor: not-allowed; }
        .review-item { border-bottom: 1px solid #e9ecef; padding: 1rem 0; }
        .review-item:last-child { border-bottom: none; }
        .review-meta { color: #6c757d; font-size: 0.9rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .review-title { margin: 0; font-size: 1.1rem; }
        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.85rem; background: #e9ecef; }
        .pagination { margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 0.4rem 0.7rem; border-radius: 6px; border: 1px solid #ced4da; text-decoration: none; color: #0d6efd; font-size: 0.9rem; }
        .pagination .current { background: #0d6efd; color: #fff; border-color: #0d6efd; }
        .delete-form { margin-top: 0.5rem; }
        .delete-form button { background: #dc3545; color: #fff; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; }
        @media (max-width: 600px) {
            body { margin: 1rem; }
            .reviews-summary { flex-direction: column; }
        }
    </style>
</head>
<body>
<header>
    <div><a href="/">&larr; 商品一覧に戻る</a></div>
    <nav>
        <?php if ($user !== null): ?>
            <span class="badge">ようこそ、<?php echo htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?>さん</span>
            <a href="/orders">注文履歴</a>
        <?php else: ?>
            <a href="/login">ログイン</a>
        <?php endif; ?>
    </nav>
</header>

<?php foreach ($flash as $message): ?>
    <p class="flash"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endforeach; ?>

<section class="card">
    <h1><?php echo htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><strong>価格:</strong> <?php echo number_format($product->getPrice()); ?>円</p>
    <p><strong>在庫:</strong> <?php echo (int) $product->getStock(); ?>個</p>
    <p><?php echo nl2br(htmlspecialchars($product->getDescription(), ENT_QUOTES, 'UTF-8')); ?></p>
</section>

<section class="card" id="reviews-summary">
    <h2>レビュー概要</h2>
    <div class="reviews-summary">
        <div class="summary-box">
            <div>平均評価</div>
            <p style="font-size:2rem; margin:0;">
                <?php echo $average === null ? '―' : number_format($average, 1); ?> / 5
            </p>
        </div>
        <div class="summary-box">
            <div>レビュー件数</div>
            <p style="font-size:2rem; margin:0;"><?php echo $totalReviews; ?> 件</p>
        </div>
    </div>
</section>

<section class="card" id="review-form">
    <h2>レビューを投稿する</h2>
    <?php if ($user === null): ?>
        <p>レビューを投稿するには <a href="/login?redirect=<?php echo urlencode('/products/' . $product->getId()); ?>">ログイン</a>してください。</p>
    <?php elseif ($eligibility === 'eligible'): ?>
        <form method="post" action="/products/<?php echo $product->getId(); ?>/reviews/form" class="review-form">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($reviewToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="rating">評価 (1〜5)</label>
            <select name="rating" id="rating" required>
                <option value="">選択してください</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?> - <?php echo str_repeat('*', $i); ?></option>
                <?php endfor; ?>
            </select>

            <label for="title">タイトル</label>
            <input type="text" name="title" id="title" maxlength="120" required>

            <label for="comment">本文</label>
            <textarea name="comment" id="comment" rows="5" maxlength="500" required></textarea>

            <button type="submit">レビューを送信</button>
        </form>
    <?php elseif ($eligibility === 'already_reviewed' && $userReview !== null): ?>
        <p>この商品へのレビューは既に投稿済みです。修正する場合は一度削除してから再投稿してください。</p>
        <article class="review-item">
            <h3 class="review-title">あなたのレビュー</h3>
            <div class="review-meta">
                <span>評価: <?php echo str_repeat('*', $userReview->getRating()); ?> (<?php echo $userReview->getRating(); ?>/5)</span>
                <span>最終更新: <?php echo $userReview->getUpdatedAt()->format('Y/m/d H:i'); ?></span>
            </div>
            <p><?php echo nl2br(htmlspecialchars($userReview->getComment(), ENT_QUOTES, 'UTF-8')); ?></p>
            <form method="post" action="/reviews/<?php echo $userReview->getId(); ?>/delete" class="delete-form">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($deleteToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">レビューを削除</button>
            </form>
        </article>
    <?php else: ?>
        <p>レビュー投稿には対象商品を購入済みである必要があります。</p>
    <?php endif; ?>
</section>

<section class="card" id="reviews">
    <h2>レビュー一覧</h2>
    <?php if ($totalReviews === 0): ?>
        <p>まだレビューは投稿されていません。</p>
    <?php else: ?>
        <?php foreach ($reviews['items'] as $item): ?>
            <?php $review = $item['review']; ?>
            <article class="review-item">
                <h3 class="review-title"><?php echo htmlspecialchars($review->getTitle(), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="review-meta">
                    <span>評価: <?php echo str_repeat('*', $review->getRating()); ?> (<?php echo $review->getRating(); ?>/5)</span>
                    <span>投稿者: <?php echo htmlspecialchars($item['authorName'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>投稿日: <?php echo $review->getCreatedAt()->format('Y/m/d H:i'); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($review->getComment(), ENT_QUOTES, 'UTF-8')); ?></p>
                <?php if ($isAdmin || ($user !== null && $review->getUserId() === $user->getId())): ?>
                    <form method="post" action="/reviews/<?php echo $review->getId(); ?>/delete" class="delete-form">
                        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($deleteToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit">このレビューを削除</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <?php if ($page === $currentPage): ?>
                        <span class="current"><?php echo $page; ?></span>
                    <?php else: ?>
                        <a href="/products/<?php echo $product->getId(); ?>?page=<?php echo $page; ?>&perPage=<?php echo $perPage; ?>#reviews"><?php echo $page; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
</body>
</html>
