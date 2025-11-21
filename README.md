# php-ec-site-practice

このリポジトリは、学習用の EC サイト実装です。現在はフロントコントローラ方式（`public/index.php`）とミドルウェアパイプラインで構成されています。

## 起動方法（Docker）

1. 依存関係をインストール
	- VS Code の Dev Containers で開くか、ホストで `composer install` を実行します。
2. コンテナ起動
	- `docker compose up -d` で `app` (PHP+Apache) / `db` (MariaDB) を起動します。
3. データベース初期化
	- 初回は `database/schema.sql` を流すか、個別マイグレーションを順番に適用します。
	- 例: `docker compose exec -T db sh -c "mysql -u root -proot_password php-ec-site-practice_db" < database/schema.sql`
4. 最新マイグレーション（Problem 42）
	- コメント・記事の author 対応（`comments.user_id` / `posts.user_id`）は個別マイグレーションを流して反映します。

```bash
docker compose exec -T db sh -c "mysql -u root -proot_password php-ec-site-practice_db" < database/migrations/2025_11_20_150000_add_user_id_to_comments.sql
docker compose exec -T db sh -c "mysql -u root -proot_password php-ec-site-practice_db" < database/migrations/2025_11_20_153000_add_user_id_to_posts.sql
docker compose exec -T db sh -c "mysql -u root -proot_password php-ec-site-practice_db" < database/migrations/2025_11_20_160000_drop_author_name_from_comments.sql
```

5. Web へアクセス
	- `http://localhost:8000/` がフロントページ。API は `http://localhost:8000/posts` などで確認できます。

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

### 従来機能

- GET `/` … 商品一覧（Web 公開）
- GET `/checkout` … 注文確認（要ログイン）
- POST `/place_order` … 注文確定（要ログイン）
- GET `/api/products` … 商品一覧 API（JSON）

### Problem 42: Blog/Post API

| HTTP | Path | 認証タグ | 説明 |
| --- | --- | --- | --- |
| GET | `/posts` | `api:public` | 記事一覧 (クエリに `page`/`perPage`/`category`/`q`/`sort`/`order`)
| GET | `/posts/{id}` | `api:public` | 単一記事の詳細
| POST | `/posts` | `api:auth` | 新規記事作成（title/body/categories）
| PATCH | `/posts/{id}` | `api:auth:owner` | 部分更新。記事 author または管理者のみ
| DELETE | `/posts/{id}` | `api:auth:owner` | 記事削除
| GET | `/posts/{id}/comments` | `api:public` | コメント一覧（ページング対応）
| POST | `/posts/{id}/comments` | `api:auth` | コメント投稿（body のみ）
| DELETE | `/comments/{commentId}` | `api:auth:comment-owner` | コメント削除（投稿者/管理者のみ）

詳しいリクエスト例やレスポンス形式は `docs/problems/problem-42.md` を参照してください。

## 旧 .php 直リンクからの移行

以前は `login.php` や `order_complete.php` など、ルート直下の `.php` に直接アクセスしていましたが現在は削除済みです。利用者は次のルートを参照してください。

- `/login`（GET/POST） … ログイン画面・認証処理
- `/orders`（GET） … 注文履歴画面
- `/orders/export`（POST） … CSV ダウンロード
- `/order_complete`（GET） … ご注文完了画面
- `/checkout` / `/place_order` … 注文確定フロー
- `/cart` / `/add_to_cart` … カート機能
- `/api/products` … 商品一覧 API
- `/posts` 系 … Problem 42 で追加された API。`config/routes.php` で正規表現マッチとして定義。

ドキュメントやスクリプトでは `.php` 拡張子付きの URL を使わず、上記のルート表現を使用してください。

## 開発メモ

- ログは `var/log/app.log` に出力されます（Git から除外）。
- 簡易イベントディスパッチャが導入されており、`user.created` を発火します。
- コード整形は `composer run format` を使用します（コミット前フックで検査されます）。
- 静的解析は `composer stan`。API 改修時は最低限ここまで実行してからコミットしてください。
- PHPUnit ベースのテストは `composer test`、フォーマッタ/静的解析/テストをまとめて実行する場合は `composer check` を使用します。
- GitHub Actions（`.github/workflows/ci.yml`）で `composer check` を実行し、プルリクエスト／main・feature ブランチへの push を自動検証します。