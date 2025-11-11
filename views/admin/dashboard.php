<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理ダッシュボード</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 2rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        nav a { margin-right: 1rem; text-decoration: none; color: #0b5ed7; font-weight: 600; }
        nav a:hover { text-decoration: underline; }
        .cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .card { border: 1px solid #ccc; border-radius: 8px; padding: 1rem; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; }
    </style>
</head>
<body>
    <header>
        <h1>管理ダッシュボード</h1>
        <nav>
            <a href="/admin/products">商品管理</a>
            <a href="/admin/users">ユーザー管理</a>
            <a href="/admin/orders">注文管理</a>
            <a href="/">サイトに戻る</a>
        </nav>
    </header>
    <section class="cards">
        <article class="card">
            <h3>商品管理</h3>
            <p>商品の作成・編集・公開状態の切り替えなどを行います。</p>
            <p><a href="/admin/products">商品一覧へ</a></p>
        </article>
        <article class="card">
            <h3>ユーザー管理</h3>
            <p>ユーザー情報の確認や権限管理、削除・復元操作を行います。</p>
            <p><a href="/admin/users">ユーザー一覧へ</a></p>
        </article>
        <article class="card">
            <h3>注文管理</h3>
            <p>注文状況の確認やステータス更新、帳票出力を行います。</p>
            <p><a href="/admin/orders">注文一覧へ</a></p>
        </article>
    </section>
</body>
</html>
