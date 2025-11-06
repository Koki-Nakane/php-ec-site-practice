# Problem 41 – 管理ダッシュボード設計メモ

## 目的
- 管理者のみがアクセスできるダッシュボードを提供し、ユーザー・商品・注文の閲覧と基本的な更新／削除を可能にする。

## 前提・既存調査
- Userデータに管理者判定用のプロパティは未定義。`is_admin` (bool) フラグを追加し、DBスキーマとエンティティを拡張する。
- 既存の認証フローは `AuthController` が `isAuthenticated()` まで提供。ロール判定は別途仕組みが必要。
- Mapper クラスは実装済み。管理画面では「既定は公開／未削除のみ返す」「管理用には別メソッドで全件取得」を基本方針にし、単一メソッドへフラグ渡しする実装は避ける。
- ビューはコントローラ内で `ob_start()` によるインラインHTMLを返すスタイル。管理画面ではテンプレート再利用性を高めるため、`views/admin` のような専用ディレクトリを新設して分離する。併せて既存画面も段階的にテンプレート方式へ移行し統一する。
- 認証ミドルウェアは `AuthController::isAuthenticated()` の真偽のみを確認し、管理者ロールの判定は現状存在しない。

## 導入方針（叩き台）
1. **アクセス制御**: 認証済みかつ管理者ロールを持つユーザーのみ許可するフローを定義。
2. **ルーティング**: `/admin` プレフィックス配下にダッシュボード用ルートを追加し、一覧／編集／削除のエンドポイントを整理。
3. **一覧表示**: User・Product・Orderの一覧を取得し、表示するビューを作成。ページングや表示件数は後述の設計で決定。
4. **編集／削除**: 最小限の編集（例: 名前・価格など）と削除操作の処理フローを設計。フォーム送信・CSRF対策・バリデーションを含めて検討。
5. **UI構成**: 管理者向けに共通レイアウトを用意し、一覧と編集画面を遷移しやすい構造にする。

## 未確定事項・確認事項
- 一覧表示の件数／ページネーションの要否は将来的に検討。現時点では全件表示で着手する。
- 管理画面のURL設計・テンプレート配置場所（例: `src/Controller/Admin`、`views/admin`）。

## 編集許可ポリシー
- ユーザー: `name` / `email` / `address` / `is_admin` を編集可。パスワード編集は別機能で扱う。
- 商品: `name` / `price` / `stock` / `description` / `is_active` を編集可。
- 注文: 進捗管理を想定し、`status`（仮）と誤入力防止のための `shipping_address` のみ編集可。金額や注文アイテムは変更不可。

## 管理画面特有の入力・バリデーション着眼点
- 管理者権限の付与／剥奪（`is_admin`）、商品の公開／非公開（`is_active`）、注文ステータスなどフロントから送られない操作を想定。
- ソフトデリート／復元操作は管理画面経由のみ。必要に応じて `deleted_at` を立てる／解除するボタンを設ける。
- 共通バリデーションは既存ロジックを再利用し、管理専用項目にだけ追加のチェック（定義済み値か、ブール値か等）を加える。
- CSRFトークンの発行／検証は既存の `OrderController` 実装を抽出して共通ヘルパ化し、管理画面のフォームでも再利用する。

## アクセス制御
- 管理画面専用ミドルウェア（仮称 `AdminAuthMiddleware`）を用意し、ログイン済みかつ `is_admin` が true のユーザーのみ通過させる。
- ミドルウェアは認可判定に専念し、入力バリデーションはコントローラ／サービスで個別に実施する。

## Mapper 共通仕様（ドラフト）
- すべての `find*` 系メソッドは既定で `deleted_at IS NULL` / `is_active = 1` を適用する。管理向けに削除済みまたは非公開も扱う場合は別メソッド（例: `findIncludingDeleted`）を用意する。
- ソフトデリートを行う Mapper は `markDeleted(int $id, DateTimeImmutable $now)` と `restore(int $id)` を基本実装とし、SQL は `UPDATE ... SET deleted_at = :now` / `SET deleted_at = NULL` を用いる。
- 物理削除は行わず、`DELETE` 文は使用禁止。レコード削除時は `updated_at` も更新するかは別途検討。
- 更新処理は必ず `updated_at` を更新し、該当カラムを持たないテーブルはマイグレーションで追加を検討する。ハイドレーションは専用メソッドで一元化し、型変換漏れを防ぐ。
- 一括取得は `ORDER BY created_at DESC` をデフォルトとし、必要なら Mapper 側でソートを差し替え可能にする。
- トランザクションで行ロックが必要な操作（例: 編集確認後の更新）は `SELECT ... FOR UPDATE` を使って対象行を確保し、二重更新を防ぐ。必要なら行ロック専用メソッドを公開し、呼び出し側でフローを組み立てられるようにする。

## ProductMapper 詳細仕様（案）
- **モデル拡張**: `Product` は `isActive`、`deletedAt`、`createdAt`、`updatedAt` を保持。`hydrateProduct(array $row): Product` で型変換・NULL処理・必須値検証を集中させる。
- **単体取得**: 既定の `find(int $id)` は公開かつ未削除のみ返す。管理向けに `findIncludingDeleted(int $id)`、ストアフロント用に `findActive(int $id)` を追加。
- **一覧取得**: 管理画面は `listForAdmin(?bool $onlyActive, int $limit, int $offset)`、フロントは `listForStorefront(int $limit, int $offset)` を用意し、既定で `updated_at` 降順。ID 配列でまとめて取得する `findAllByIds(array $ids, bool $forUpdate = false)` を追加。
- **状態変化**: `save(Product $product)` で `is_active`・`updated_at` を確実に更新し、削除済みのまま保存されないようガード。`enable(int $id)` / `disable(int $id)`、`markDeleted(int $id)` / `restore(int $id)` を提供し、ソフトデリートと公開制御を分離する。
- **在庫処理**: `updateStock(int $id, int $adjustment, bool $forUpdate = true)` を基本とし、必要に応じて `SELECT ... FOR UPDATE` でロック取得。`decreaseStock` は互換性維持の薄いラッパーにする。
- **例外とバリデーション**: 存在しない場合は `ProductNotFoundException`、並行更新時は `ConcurrentUpdateException` を投げる想定。DB投入前に価格・在庫の非負チェックやテキスト正規化を Mapper 層で実施。
- **トランザクション前提**: 変化を伴うメソッドは呼び出し元でトランザクション開始が必須である旨をコメント化し、必要に応じて `lockProduct(int $id)` のような補助メソッドも検討する。

## Productモデル設計（案）
- **保持プロパティ**: `id`、`name`、`price`、`description`、`stock` に加え、`isActive`（公開フラグ）、`deletedAt`、`createdAt`、`updatedAt` を保持する。
- **コンストラクタ**: すべてのプロパティを引数で受け取り、価格・在庫の非負チェック、名称文字数チェック、`deletedAt` と `updatedAt` の整合などを行う。新規作成用にはデフォルトを与える `createNew(...)` 静的ファクトリを用意する。
- **アクセサ**: 各プロパティの `get*`／`isActive()`／`isDeleted()`／`getDeletedAt()` を提供し、日時は `DateTimeImmutable` で返す。
- **ドメイン操作**: `rename()`、`changePrice()`、`changeDescription()`、`changeStock()`、`adjustStock()`、`activate()`、`deactivate()`、`markDeleted()`、`restore()`、`touch()` を備え、ビジネスルールに基づく状態遷移のみ許可する。
- **シリアライズ**: `toArray()` を拡張し、公開用と管理用で必要に応じて `toPublicPayload()` など用途別メソッドを用意する。
- **例外ポリシー**: 値が不正な場合は `InvalidArgumentException`、状態遷移違反は `DomainException` を投げる。
- **テスト観点**: バリデーション、在庫調整、公開／非公開、ソフト削除、タイムスタンプ更新が期待どおり動くことを単体テストで検証する。

## AdminProductController 設計（案）
- **責務**: `/admin/products` 配下の一覧・編集フォーム・更新・公開/非公開切替・ソフト削除/復元を担当し、低レベルなデータ操作は `ProductMapper` に委譲する。
- **依存**: `ProductMapper`、テンプレート描画（`TemplateRenderer` 仮称）、`CsrfTokenManager`、`FlashMessageService`、`ClockInterface` を DI で受け取る。入力バリデーションは `AdminProductValidator`（新設予定）に切り出す。
- **ルート対応**: `GET /admin/products`（一覧）、`GET /admin/products/{id}`（編集フォーム）、`POST /admin/products/{id}`（更新）、`POST /admin/products/{id}/toggle-active`、`POST /admin/products/{id}/delete`、`POST /admin/products/{id}/restore` を提供。新規作成は後続検討。
- **バリデーション**: `name` 必須・1〜255 文字、`price` は 0 以上の数値（小数第2位まで）、`stock` は 0 以上の整数、`description` は最大 2000 文字。切替系は ID と CSRF のみ検証する。
- **ビューモデル**: 一覧用 `ProductSummary` と編集用 `ProductDetail` を想定し、CSRF トークンやフラッシュメッセージをテンプレートへ引き渡す。
- **エラーハンドリング**: `ProductNotFoundException` → 404、`ConcurrentUpdateException` → 409 相当メッセージ、バリデーション失敗 → フォーム再描画（422）、その他 → ダッシュボード共通の 500。
- **ミドルウェア前提**: すべてのルートに `AdminAuthMiddleware` を適用し、`POST` は CSRF 検証も必須。ルータ設定時に `/admin/products` グループへ登録する。
- **テスト観点**: 認可の有無、各操作の成功/失敗、競合発生時の挙動、バリデーションエラー時の戻り値を確認する機能／統合テストを用意する。

## DB変更メモ（随時更新）
- `users` テーブル: `is_admin TINYINT(1) NOT NULL DEFAULT 0`、`deleted_at TIMESTAMP NULL` を追加予定。管理検索用に `INDEX users_is_admin_deleted_at_idx (is_admin, deleted_at)` を検討。
	- 2025-11-05: `2025_11_05_000000_add_is_admin_and_deleted_at_to_users.sql` を追加し、上記カラムと複合インデックスを実装済み。
- `products` テーブル: 公開状態を制御する `is_active TINYINT(1) NOT NULL DEFAULT 1` を追加し、既存の `updated_at` と連動させる。
- `orders` テーブル: 状態管理のための `status TINYINT NOT NULL DEFAULT 0`、論理削除用の `deleted_at TIMESTAMP NULL`、更新追跡用の `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` を追加する。`shipping_address` は既存マイグレーションで実装済み。

## 削除・公開ポリシー
- ユーザーと注文はソフトデリートを採用し、`deleted_at` カラムを追加して論理削除状態を管理する（履歴保全・復元対応）。
- 商品は削除操作で `is_active`（仮称）フラグを切り替え、利用者側には非公開化する。注文履歴との整合性は保つ。
- `deleted_at` は `TIMESTAMP NULL` 型で未削除を NULL 管理する。
- `is_active` は `TINYINT(1) NOT NULL DEFAULT 1` を想定し、PHP側でboolアクセサを用意する。
- `Product` には `isActive()` と `setActive(bool $state)` を追加し、管理画面から公開状態を切り替えられるようにする。
- `User` には `isAdmin()`・`isDeleted()`・`markDeleted()`・`restore()` を追加し、`is_admin` ブール値と `deleted_at`（`DateTimeImmutable|null`）を保持する。
- `Order` モデルも `deleted_at` を保持し、`isDeleted()`・`markDeleted()`・`restore()` と `status` 用アクセサ（定数＋ `getStatus()` / `setStatus(int)`）を追加する。
- 管理者自身および他の管理者は削除不可とし、運用事故を防止する。
- 注文履歴のあるユーザーはソフトデリートのみ許可し、関連注文を保持する。
- 注文済みの商品は削除ボタンで非公開化に切り替え、物理削除は行わない。
- 注文にはソフトデリート済みユーザーや非公開商品が含まれる可能性があるため、表示時には「削除済みユーザー」「非公開商品」等のラベルで区別を付ける。

## タスク分解（初版）
1. 管理者ロールの取り扱い方を決定し、必要ならDB・エンティティ・マッパーを拡張。
2. `/admin` 用のルーティングとコントローラ骨組みを追加し、認証＋権限チェックを組み込む。
3. Product モデルの拡張（新プロパティ・アクセサ・ドメイン操作）とテスト追加。
4. ソフトデリート／非公開化に必要なDB変更（`deleted_at`、`is_active` 等）と各モデル更新を行う。
5. User・Product・Orderの一覧取得処理を実装し、簡易テンプレートで表示。
6. 編集フォームと更新処理を追加（各エンティティごとに最小単位から着手）。
7. 削除／非公開切替の処理と確認フローを実装。
8. UIの体裁調整、共通レイアウト／ナビゲーションの追加。
9. 動作確認およびテストシナリオ整理（手動でもOK）。

## メモ
- `is_admin` 追加は新規マイグレーションで対応し、既存レコードの初期値は `false`（0）とする。想定ファイル名: `2025_10_16_090000_add_is_admin_to_users`。
- 管理者アカウントの初期投入は `scripts/seed_admin_user.php`（仮称）で行い、必要な環境で手動実行する。
- 実装を進めながら本メモに決定事項と変更履歴を追記する。
- 一覧画面は初期実装で全件表示とし、データ量が増えた段階でページング導入を検討する。

## `/admin` ルート素案
| HTTP | パス | 役割 |
| ---- | ---- | ---- |
| GET | /admin | ダッシュボードトップ（概要表示） |
| GET | /admin/users | ユーザー一覧 |
| GET | /admin/users/{id} | ユーザー編集フォーム |
| POST | /admin/users/{id} | ユーザー更新 |
| POST | /admin/users/{id}/delete | ユーザー削除 |
| GET | /admin/products | 商品一覧 |
| GET | /admin/products/{id} | 商品編集フォーム |
| POST | /admin/products/{id} | 商品更新 |
| POST | /admin/products/{id}/delete | 商品削除 |
| GET | /admin/orders | 注文一覧 |
| GET | /admin/orders/{id} | 注文詳細（必要に応じて更新フォーム） |
| POST | /admin/orders/{id} | 注文ステータス更新 |

各リソースごとに `App\Controller\Admin\UserController`、`ProductController`、`OrderController` を用意し、対応する `/admin/users`・`/admin/products`・`/admin/orders` 系ルートを担当させる。
