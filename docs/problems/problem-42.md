# Problem 42 設計メモ

## API エンドポイント案

| やりたいこと | メソッド | URI 例 | 意味 / 備考 |
| --- | --- | --- | --- |
| 記事の一覧を取得 | GET | `/posts` | posts を取得する |
| 特定の記事を取得 | GET | `/posts/{id}` | ID 指定で単一 post を取得する |
| 新しい記事を作成 | POST | `/posts` | 新規 post を登録する |
| 特定の記事を更新 | PATCH | `/posts/{id}` | ID 指定の post を部分更新する |
| 特定の記事を削除 | DELETE | `/posts/{id}` | ID 指定の post を削除する |
| 記事コメントを一覧取得 | GET | `/posts/{id}/comments` | post ごとのコメントを取得する |
| コメントを投稿 | POST | `/posts/{id}/comments` | post にひも付くコメントを作成する |
| コメントを削除 | DELETE | `/comments/{commentId}` | コメント単体を削除する |

> 備考: 更新は全項目上書きではなく差分適用を想定（PATCH）。PUT を選ぶ場合は完全上書きポリシーとの整合性を再確認する。

### 認証・権限

- コメント投稿はログイン済みユーザーに限定する。
- コメント取得 API は公開だが、作成・削除・記事作成などの書き込み系は認証必須。
- 閲覧系（`GET /posts` / `GET /posts/{id}`）は `api:public` タグで公開し、`AuthMiddleware` などは差し込まない。
- 作成系（`POST /posts` や `POST /posts/{id}/comments` など）は `api:auth` タグとし、`AuthMiddleware` だけを差し込んで未ログイン時は JSON の 401 を返す。
- 更新・削除系（`PATCH /posts/{id}` / `DELETE /posts/{id}`）は新設する `api:auth:owner` タグを使い、`AuthMiddleware` でログインを保証したあとに `PostAuthorOrAdminMiddleware`（仮）のような専用ガードを差し込む。ガードは該当記事の author であれば通し、異なる場合は `AuthController::isAdmin()` を用いて管理者なら許可、そうでなければ 403 JSON を返す。
- コメント削除は `api:auth:comment-owner` タグを想定し、`AuthMiddleware` 後に `CommentAuthorOrAdminMiddleware` を差し込み、コメントの投稿者または管理者のみ許可する。判定は `comments.user_id` とログインユーザー ID を突き合わせるだけで完結させる。
- 書き込み系は CSRF ミドルウェアを必須化し、フォームは hidden `_token`、API は `X-CSRF-Token` ヘッダーを送る。SPA などは `GET /csrf-token` で JSON 取得後にヘッダーへ設定し、同じセッションでは同一トークンを使い回す。

## ドメインモデル変更案

- `Post` クラス
	- `use DateTimeImmutable;` を追加し、`DateTime` から移行。
	- `$createdAt` の型を `DateTimeImmutable` に変更。
	- `private ?DateTimeImmutable $updatedAt` プロパティを追加し、更新時に現在時刻を反映。
  - `private string $slug` プロパティを追加し、URL 生成に使う一意の識別子として扱う。
  - `private ?int $authorId` を保持し、`users.id` と結び付けて権限判定や API 応答の author 情報に利用する。
  - `private string $status` / `private int $commentCount` を追加し、公開状態とコメント数をドメインオブジェクトで保持する。
  - `private array $categories` プロパティを追加し、`Category` 値オブジェクト（id/slug/name を保持予定）の配列として管理。
  - カテゴリをまとめて差し替える `setCategories(array $categories): void` と取得用 `getCategories(): array` を実装し、`PostMapper` からセットする運用にする。
- `Comment` クラス
  - 同様に `DateTimeImmutable` を採用して日時型を統一。
  - コメント投稿はログイン済みユーザーのみ許可し、`user_id` で `User` を参照する。
  - コメント一覧・詳細のレスポンスは常に `users.name` をリアルタイム参照で組み立て、スナップショットは保持しない。
- `Category` クラス
  - `private string $slug` プロパティを追加し、カテゴリの表示名と分離して管理する。
  - `Post` と同様に `DateTimeImmutable` へ移行する必要は現状なし。

- `posts` テーブル:
	- `created_at` / `updated_at` は `TIMESTAMP` 型を採用。
	- デフォルト値: `created_at` は作成時刻、`updated_at` は `NULL` を許可（初期値も `NULL`）。
	- `ON UPDATE CURRENT_TIMESTAMP` は使用せず、アプリ側で更新を管理。
  - `slug` カラム（VARCHAR 255・UNIQUE）を追加し、記事の公開 URL を一意に識別する。
  - `status`（VARCHAR 32・デフォルト `published`）と `comment_count`（INT UNSIGNED・デフォルト 0）を追加し、API で返却する値を保持。
  - `user_id`（INT・NULL 可）を追加し、`users.id` への外部キー制約（`ON DELETE SET NULL`）で記事作者を追跡する。
- `post_categories` テーブル（中間テーブル）:
  - `post_id` / `category_id` の複合主キー。各 `post_id` に対して `category_id` は一意。
  - JOIN でカテゴリ一覧をまとめて取得する前提なので、`PostMapper` での一括 INSERT/DELETE ができるようにする。
- `comments` テーブル:
  - 今回は編集機能を提供しないため `updated_at` カラムは追加しない。
  - `user_id`（初期は NULL 許容、将来的に NOT NULL 化）を追加し、`users.id` への外部キー制約を設定。
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
  - リクエスト: JSON ボディで `title`・`body` を必須、`categories`（カテゴリ slug の配列、最大 10 個）を任意で受け付ける。`status` や `author` を送ってきた場合は 400。
  - レスポンス: 作成された記事を `201 Created` で返し、`Location: /posts/{id}` を付与する。

    ```json
    {
      "id": 42,
      "author": { "id": 7, "name": "Alice" },
      "title": "新しい投稿",
      "body": "本文...",
      "status": "published",
      "commentCount": 0,
      "categories": [
        { "id": 2, "slug": "release", "name": "リリース" }
      ],
      "createdAt": "2025-11-19T10:00:00+09:00",
      "updatedAt": null
    }
    ```
- **PATCH /posts/{id}**
  - リクエスト: URL パス `{id}` を対象とし、JSON ボディは差分形式。`title`・`body`・`categories`・`status` のいずれかを最低 1 つ含む（複数可）。`categories` は slug 配列で上限 10。`status` は `draft`/`published` のみ。
  - レスポンス: 反映後の記事を `200 OK` で返す。`updatedAt` は現在時刻に更新された値が入る。

    ```json
    {
      "id": 42,
      "author": { "id": 7, "name": "Alice" },
      "title": "タイトル修正後",
      "body": "本文...",
      "status": "draft",
      "commentCount": 3,
      "categories": [],
      "createdAt": "2025-11-19T10:00:00+09:00",
      "updatedAt": "2025-11-20T08:30:00+09:00"
    }
    ```
- **DELETE /posts/{id}**
  - リクエストボディは不要。URL パスの `{id}` を対象とする。
  - レスポンス: `204 No Content`。

## コメント API 詳細

- **GET /posts/{postId}/comments** (`api:public`)
  - クエリ: `page`/`perPage` を記事一覧と同じ仕様でサポートし、デフォルトは `page=1`・`perPage=20`。古いものから並べ替えたい場合に備えて `sort=createdAt` ＋ `order=asc|desc` も許可する（デフォルトは `desc`）。
  - レスポンス: 下記の JSON を `200 OK` で返却。

    ```json
    {
      "data": [
        {
          "id": 10,
          "postId": 1,
          "author": { "id": 4, "name": "Bob" },
          "body": "コメント本文",
          "createdAt": "2025-11-13T09:15:00+09:00"
        }
      ],
      "meta": {
        "page": 1,
        "perPage": 20,
        "total": 37,
        "totalPages": 2
      }
    }
    ```

- **POST /posts/{postId}/comments** (`api:auth`)
  - リクエスト: `body`（必須、トリム後 1〜2000 文字）のみを JSON で受け付ける。`postId` は URL パラメータで指定するためボディには含めない。`author` や `userId` を送ってきた場合は 400 にする。
  - レスポンス: 新規コメントを `201 Created` で返し、`Location: /comments/{id}` を付与する。

    ```json
    {
      "id": 38,
      "postId": 1,
      "author": { "id": 4, "name": "Bob" },
      "body": "投稿した本文",
      "createdAt": "2025-11-13T09:15:00+09:00"
    }
    ```

- **DELETE /comments/{commentId}** (`api:auth:comment-owner`)
  - リクエストボディは不要。URL の `{commentId}` で対象を特定し、`AuthMiddleware` → `CommentAuthorOrAdminMiddleware` の順でガードする。
  - レスポンス: 成功時は `204 No Content`。存在しない ID の場合は 404、投稿者・管理者以外が削除しようとした場合は `{"error":"forbidden"}` 形式の 403 を返却。

### コメント系バリデーション

- `postId`/`commentId` は URL パスで 1 以上の整数のみ許可。該当リソースがなければ 404。
- `body`: トリム後 1〜2000 文字。HTML は許可しない（サニタイザか Markdown の SafeMode を必須化）。
- 連投対策として 1 ユーザーあたり一定時間のクールダウン（例: 5 秒）を実装する場合は 429 を返す運用を追加で検討。
- コメント削除は投稿者本人か管理者のみ許可。`CommentAuthorOrAdminMiddleware` はコメント存在チェックと権限チェックを同一トランザクション内で行い、レスポンス形式を API 用の JSON に統一する。

## バリデーション方針

- **共通**
  - URL パスの ID は 1 以上の整数。`PostMapper` で該当記事が見つからない場合は 404 を返す。
  - クライアントから `author`, `commentCount`, `createdAt`, `updatedAt` などサーバ定義フィールドが送信された場合は 400（`validation_error`）で弾く。
  - JSON ボディの Content-Type は `application/json` を必須とし、パースできなければ 415 or 400。
- **POST /posts**
  - `title`: トリム後 1〜255 文字。含まれていない／空の場合は `errors.title` にメッセージを設定。
  - `body`: トリム後 1〜10000 文字。Markdown を許容するが、保存時は XSS 対策のためサニタイズ必須。
  - `categories`: 任意配列。要素は既知 slug のみ許可、重複があればユニーク化して 422。存在しない slug を含む場合は `errors.categories[n]` に個別エラー。
  - 受入れ JSON 例:

    ```json
    {
      "title": "タイトル",
      "body": "本文",
      "categories": ["release", "product"]
    }
    ```
- **PATCH /posts/{id}**
  - 最低 1 フィールド必須。空オブジェクト `{}` の場合は 400。
  - `title` / `body`: POST と同条件。
  - `categories`: POST と同条件。空配列 `[]` なら全カテゴリ解除として扱う。
  - `status`: `draft` / `published` のみ。`draft` へ変更する際は閲覧権限のあるユーザーかを別途チェック。
  - 受入れ JSON 例:

    ```json
    {
      "title": "修正後タイトル",
      "categories": []
    }
    ```

## 今後詰める事項

1. **ルーティング/コントローラ設計**: 既存ルートとの整合性、認証や CSRF 保護の要否（CSRF は専用ミドルウェアと `/csrf-token` で実装済み）、公開範囲。`config/routes.php` には `/posts` 系エンドポイントを正規表現マッチで登録済み。
2. **例外・エラーハンドリング**: ドメイン例外とインフラ例外の扱い、HTTP ステータスの割り当て、エラーメッセージ形式。
3. **テスト戦略**: ユニット・統合テストでカバーすべきシナリオ（一覧取得、部分更新、バリデーションエラー、削除済みの参照など）。

必要に応じて各セクションを肉付けし、問題 42 の実装方針を固めていく。
