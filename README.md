# php-ec-site-practice

このリポジトリは、学習用の EC サイト実装です。現在はフロントコントローラ方式（`public/index.php`）とミドルウェアパイプラインで構成されています。

## 起動方法（Docker）

1. 依存関係をインストール
	- VS Code の Dev Containers で開くか、ホストで Composer を実行します。
2. コンテナ起動
	- コンテナ起動後、Apache のドキュメントルートは `public/` になります。

## 構成のポイント

- すべてのリクエストは `public/index.php`（フロントコントローラ）を通過します。
- ルーティング定義は `config/routes.php` にあり、`/checkout` や `/api/products` のような拡張子のないパスにマップされます。
- ミドルウェア（エラーハンドラ、認証、ロギング）は `src/Infrastructure/Middleware/` にあります。
- 依存注入（DI）コンテナは `config/container.php` で初期化されます。

## 主なエンドポイント

- GET `/` … 商品一覧（Web 公開）
- GET `/checkout` … 注文確認（要ログイン）
- POST `/place_order` … 注文確定（要ログイン）
- GET `/api/products` … 商品一覧 API（JSON）

## 旧 .php 直リンクからの移行

以前は `index.php` や `api/products.php` など、ルート直下の `.php` に直接アクセスしていました。現在は以下のように置き換えています。

- `index.php` → `public/index.php`（フロントコントローラに統合）
- `/api/products.php` → `/api/products`
- `/api/product.php?id=...` → 今後実装するなら `/api/products/{id}` 等を検討
- `/checkout.php` → `/checkout`
- `/place_order.php` → `/place_order`
- `/cart.php` → `/cart`（将来的にフロントコントローラへ統合予定）
- `/add_to_cart.php` → `/add_to_cart`（将来的にフロントコントローラへ統合予定）

ドキュメントやスクリプトでは `.php` 拡張子付きの URL を使わず、上記のルート表現を使用してください。

## 開発メモ

- ログは `var/log/app.log` に出力されます（Git から除外）。
- 簡易イベントディスパッチャが導入されており、`user.created` を発火します。
- コード整形は `composer run format` を使用します（コミット前フックで検査されます）。