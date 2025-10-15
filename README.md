# php-ec-site-practice

このリポジトリは、学習用の EC サイト実装です。現在はフロントコントローラ方式（`public/index.php`）とミドルウェアパイプラインで構成されています。

## 起動方法（Docker）

1. 依存関係をインストール
	- VS Code の Dev Containers で開くか、ホストで Composer を実行します。
2. コンテナ起動
	- コンテナ起動後、Apache のドキュメントルートは `public/` になります。

## デモデータの投入

- `scripts/seed_demo.php` を実行すると、学習用のデモユーザーなど最低限のデータを投入できます。
	- 例: `docker compose exec app php scripts/seed_demo.php`
- 既存データがある場合は上書き更新されます。
- 付与されるデモユーザーの認証情報は以下です。
	- Email: `demo@example.com`
	- Password: `Password123`

> `users.name` カラムにはアプリケーションと同じ ASCII 制約（英数字+アンダースコア）が付与されています。シードや手動投入時もこのルールを守ってください。

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

以前は `login.php` や `order_complete.php` など、ルート直下の `.php` に直接アクセスしていましたが現在は削除済みです。利用者は次のルートを参照してください。

- `/login`（GET/POST） … ログイン画面・認証処理
- `/orders`（GET） … 注文履歴画面
- `/orders/export`（POST） … CSV ダウンロード
- `/order_complete`（GET） … ご注文完了画面
- `/checkout` / `/place_order` … 注文確定フロー
- `/cart` / `/add_to_cart` … カート機能
- `/api/products` … 商品一覧 API

ドキュメントやスクリプトでは `.php` 拡張子付きの URL を使わず、上記のルート表現を使用してください。

## 開発メモ

- ログは `var/log/app.log` に出力されます（Git から除外）。
- 簡易イベントディスパッチャが導入されており、`user.created` を発火します。
- コード整形は `composer run format` を使用します（コミット前フックで検査されます）。