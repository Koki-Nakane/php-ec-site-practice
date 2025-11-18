# Problem 42 設計メモ

## API エンドポイント案

| やりたいこと | メソッド | URI 例 | 意味 / 備考 |
| --- | --- | --- | --- |
| 記事の一覧を取得 | GET | `/posts` | posts を取得する |
| 特定の記事を取得 | GET | `/posts/{id}` | ID 指定で単一 post を取得する |
| 新しい記事を作成 | POST | `/posts` | 新規 post を登録する |
| 特定の記事を更新 | PATCH | `/posts/{id}` | ID 指定の post を部分更新する |
| 特定の記事を削除 | DELETE | `/posts/{id}` | ID 指定の post を削除する |

> 備考: 更新は全項目上書きではなく差分適用を想定（PATCH）。PUT を選ぶ場合は完全上書きポリシーとの整合性を再確認する。

### 認証・権限

- コメント投稿はログイン済みユーザーに限定する。
- コメント取得 API は公開だが、作成・削除・記事作成などの書き込み系は認証必須。
- API の認証フローは既存の `AuthMiddleware`/`AdminAuthMiddleware` を再利用する。`api:auth` タグ付きルートは `AuthMiddleware` をパイプラインに差し込み、未ログイン時は JSON 形式で 401 を返す。管理系エンドポイントでは `AuthMiddleware` → `AdminAuthMiddleware` の順で差し込み、認証済みだが管理者ロールでない場合は `AdminAuthMiddleware` が 403 を返す。
- API ではセッション Cookie によるログイン状態をそのまま利用し、追加のトークン方式は今回導入しない。将来的にトークン認証へ切り替える場合もミドルウェア差し替えで吸収できるよう、ルートの `tag` に `api:public`/`api:auth`/`web:admin` などの情報を持たせる前提で設計する。

## ドメインモデル変更案

- `Post` クラス
	- `use DateTimeImmutable;` を追加し、`DateTime` から移行。
	- `$createdAt` の型を `DateTimeImmutable` に変更。
	- `private ?DateTimeImmutable $updatedAt` プロパティを追加し、更新時に現在時刻を反映。
  - `private string $slug` プロパティを追加し、URL 生成に使う一意の識別子として扱う。
  - `private string $status` / `private int $commentCount` を追加し、公開状態とコメント数をドメインオブジェクトで保持する。
  - `private array $categories` プロパティを追加し、`Category` 値オブジェクト（id/slug/name を保持予定）の配列として管理。
  - カテゴリをまとめて差し替える `setCategories(array $categories): void` と取得用 `getCategories(): array` を実装し、`PostMapper` からセットする運用にする。
- `Comment` クラス
	- 同様に `DateTimeImmutable` を採用して日時型を統一。
	- コメント投稿はログイン済みユーザーのみ許可し、`User` オブジェクトを参照（`private User $author`）。
	- コメント時点のユーザー情報は常に最新化する方針のため、名前のスナップショット列は持たない。
- `Category` クラス
  - `private string $slug` プロパティを追加し、カテゴリの表示名と分離して管理する。
  - `Post` と同様に `DateTimeImmutable` へ移行する必要は現状なし。

- `posts` テーブル:
	- `created_at` / `updated_at` は `TIMESTAMP` 型を採用。
	- デフォルト値: `created_at` は作成時刻、`updated_at` は `NULL` を許可（初期値も `NULL`）。
	- `ON UPDATE CURRENT_TIMESTAMP` は使用せず、アプリ側で更新を管理。
  - `slug` カラム（VARCHAR 255・UNIQUE）を追加し、記事の公開 URL を一意に識別する。
  - `status`（VARCHAR 32・デフォルト `published`）と `comment_count`（INT UNSIGNED・デフォルト 0）を追加し、API で返却する値を保持。
- `post_categories` テーブル（中間テーブル）:
  - `post_id` / `category_id` の複合主キー。各 `post_id` に対して `category_id` は一意。
  - JOIN でカテゴリ一覧をまとめて取得する前提なので、`PostMapper` での一括 INSERT/DELETE ができるようにする。
- `comments` テーブル:
	- 今回は編集機能を提供しないため `updated_at` カラムは追加しない。
	- 新たに `user_id`（NOT NULL）を追加し、`users.id` への外部キー制約を設定。
	- これに伴い `author_name` は廃止予定、表示名は `users` テーブルから取得。
- `categories` テーブル:
  - `slug` カラム（VARCHAR 255・UNIQUE）を追加し、表示名と URL 用識別子を分離する。
- 既存データ:
	- 旧 `DateTime` ベースの値を `DateTimeImmutable` 相当として扱うための移行手順を整理。

## PostMapper 設計方針

- **責務**: posts / post_categories テーブルから `Post` ドメインオブジェクトを復元（ハイドレート）し、カテゴリ情報も含めて一貫性のある状態で返す。`Post` から永続化／削除する際は中間テーブルを含めてトランザクション制御する。
- **取得系メソッド**
  - `findById(int $id): ?Post` — 単一記事をカテゴリ付きで取得。存在しない場合は `null`。
  - `findAll(PostFilter $filter): array{posts: Post[], total: int}` — 一覧 API 用。クエリパラメータ相当（カテゴリ配列、キーワード、ステータス、並び順、ページング）を `PostFilter` 値オブジェクトで受け取り、SQL 組み立てを Mapper 内に閉じ込める。
  - `findPostsByCategory(int $categoryId): Post[]` — 問題27の要件を充足。内部的には `PostFilter` を使ってもよい。
  - `findPostsByCategories(array $categoryIds): Post[]` — OR 条件でまとめて取得。`IN (...)` で解決し、N+1 を避ける。
- **永続化メソッド**
  - `insert(Post $post): int` — 新規作成。posts への INSERT と post_categories の一括 INSERT を同一トランザクションで行い、生成された ID を返す。
  - `update(Post $post): void` — 既存記事の更新。posts を UPDATE し、中間テーブルは一旦削除→再登録で整合性を確保（カテゴリ差分更新が必要なら別途最適化）。
  - `delete(int $id): void` — 論理削除／物理削除の方針に応じて実装。
- **補助構造**
  - `PostFilter` 値オブジェクトで検索条件を表現し、Mapper 側はその値をもとに SQL を構築する。これにより将来条件が増えても Mapper のシグネチャを変えずに拡張できる。
  - カテゴリ情報は `Category` 値オブジェクト（id/slug/name）で扱い、`Post` には VO の配列として渡す。

## リソース表現方針

- **Post レスポンス例**
	- 最低限返すフィールド: `id`, `author` (id/name), `title`, `body`, `status`, `commentCount`, `categories`, `createdAt`, `updatedAt`。
	- `status` は将来の公開予約・下書き管理を見越して導入（現在は `published` 固定でも良い）。
	- `slug` や `links` は将来の SEO / HAL 対応を検討する段階で追加できるよう、現時点では非採用。
- **Comment レスポンス例**
	- フィールド: `id`, `postId`, `author` (id/name), `body`, `createdAt`。
	- コメント編集は非対応のため `updatedAt` は返さない。
- **追加で検討しないフィールド**
	- `avatarUrl`: プロフィール画像機能が整った段階で追加。現状は不要。
	- `slug`, `links`: 必要になった時点で JSON に追加しても非破壊のため後回し。

## `GET /posts` クエリパラメータ方針

- `page`: 1 以上の整数。デフォルト 1。
- `perPage`: 1〜50 程度の範囲で指定。デフォルト 20。
- `category`: 複数指定に対応（OR 条件）。指定スラッグのいずれかを含む記事を取得。
- `q`: タイトル／本文の部分一致検索用文字列。
- `status`: `published`, `draft` など。認可されたユーザー（管理者・執筆者）のみに許可。
- `sort`: `createdAt` または `commentCount`。
- `order`: `asc` / `desc`。デフォルトは `desc`。
- 認証していないユーザーは常に `status=published` の結果のみ取得。ユーザー種別はサーバ側の認証情報で判定し、クエリで `userId` を渡さない。

## `GET /posts` レスポンス例

```json
{
  "data": [
    {
      "id": 1,
      "author": { "id": 7, "name": "Alice" },
      "title": "新機能リリース", 
      "body": "...",
      "status": "published",
      "commentCount": 3,
      "categories": ["release", "product"],
      "createdAt": "2025-11-12T04:30:00+09:00",
      "updatedAt": null
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 137,
    "totalPages": 7
  }
}
```

> ページ情報として `page`, `perPage`, `total`, `totalPages` を返し、`hasNext`/`hasPrev` のようなフラグは提供しない。

## 書き込み系エンドポイント方針

- **POST /posts**
  - リクエスト: `title` (必須), `body` (必須), `categories` (任意・配列)。`author` や `status` はサーバ側で決定。
  - レスポンス: 作成された記事の JSON を `201 Created` とともに返す。`Location` ヘッダで `/posts/{id}` を通知する。
- **PATCH /posts/{id}**
  - リクエスト: URL パスの `{id}` を対象 ID とみなす。ボディは差分形式で `title`, `body`, `categories` のいずれかを最低1つ含む。
  - レスポンス: 更新後の記事 JSON を `200 OK` で返す。
- **DELETE /posts/{id}**
  - リクエストボディは不要。URL パスの `{id}` を対象とする。
  - レスポンス: `204 No Content`。

## バリデーション方針

- **共通**
  - URL パスの ID は 1 以上の整数。存在しない場合は 404。
  - クライアントから `author`, `commentCount`, `createdAt`, `updatedAt` などサーバ定義フィールドが送信された場合は 400。
- **POST /posts**
  - `title`: 必須。トリム後 1〜255 文字。
  - `body`: 必須。トリム後 1〜10000 文字。
  - `categories`: 任意。配列（最大 10 要素）。要素は既知のカテゴリ slug で重複不可。
- **PATCH /posts/{id}**
  - ボディは差分形式で `title`, `body`, `categories`, `status` のいずれかを最低 1 つ含む。
  - `title`: 送信された場合、POST と同条件。
  - `body`: 送信された場合、POST と同条件。
  - `categories`: 送信された場合、POST と同条件。空配列で全解除を許可。
  - `status`: 送信された場合は `published` / `draft` のみ許可（権限チェックは別途）。

## 今後詰める事項

1. **ルーティング/コントローラ設計**: 既存ルートとの整合性、認証や CSRF 保護の要否、公開範囲。
2. **例外・エラーハンドリング**: ドメイン例外とインフラ例外の扱い、HTTP ステータスの割り当て、エラーメッセージ形式。
3. **テスト戦略**: ユニット・統合テストでカバーすべきシナリオ（一覧取得、部分更新、バリデーションエラー、削除済みの参照など）。
4. **ドキュメント更新**: README や API リファレンス、テストシナリオへの追記、Postman コレクションの有無。
5. **コード影響調査**: Mapper やテンプレートで `DateTimeImmutable` を前提にするための修正箇所の洗い出し。
6. **PostMapper 実装**: 新規に `PostMapper` を作成し、Post とカテゴリのマッピング・永続化処理を担わせる。

必要に応じて各セクションを肉付けし、問題 42 の実装方針を固めていく。
