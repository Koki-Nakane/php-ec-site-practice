# Problem 44 – レビュー機能 設計メモ

## 機能概要
- 対象: 購入済み商品のレビュー（評価 + コメント）投稿・閲覧機能を追加する。
- 利用者ロール:
  - ログイン済み一般ユーザー: 自分が購入した商品のみレビュー投稿/編集/削除可。
  - 管理者: すべてのレビューを閲覧・削除可能。評価の変更は原則不可（証跡保持）。

## データモデル案
- 新テーブル `product_reviews`
  - `id (PK)`
  - `product_id (FK -> products.id)`
  - `user_id (FK -> users.id)`
  - `title` (VARCHAR 120)
  - `rating` (TINYINT 1-5)
  - `comment` (TEXT)
  - `created_at` / `updated_at`
  - `deleted_at` (ソフトデリートで荒らし対応)
- `FK` は Foreign Key（外部キー）の略で、子テーブルの値を親テーブルの主キーに紐付けて参照整合性を保つための制約。
- `deleted_at` に日時が入っているレコードは論理削除（画面上非表示）とし、実データは残すことで荒らし発生時に証跡を保ちながら後から復元・監査できる。
- 付随インデックス
  - `(product_id, deleted_at)` … 商品詳細でレビュー一覧を取得する際に「削除されていない最新レビュー」を高速に検索するための複合インデックス。
  - `(user_id, product_id)` unique + `deleted_at IS NULL` … 同一ユーザーが同一商品へ複数投稿するのを防ぐ一意制約。論理削除後は `deleted_at` が NULL でなくなるため、再投稿が可能になる。

## バリデーション方針
- `rating`: 整数, 1〜5 のみ許可。
- `title`: 2〜60 文字程度。必須、過度に長いタイトルは弾く。
- `comment`: 500文字以内、HTML不可（エスケープ表示）。
- レビュー投稿時に `orders` / `order_items` を参照し、購入実績があるかをチェック。
- 同一商品に対して既存レビューがある場合は投稿不可とし、削除済みでなければ 1レビュー制約エラーを表示。

## アーキテクチャ設計
- **Mapper**: `ReviewMapper`（新規）
  - `findByProduct(int $productId, int $page, int $perPage)`
  - `findByUserAndProduct(int $userId, int $productId)`
  - `save(Review $review)` / `softDelete(int $reviewId)`
- **Service層**: `ReviewService`
  - 購入済み判定ロジック／平均評価計算
- **Controller**: `ProductController`
  - 商品詳細 `/products/{id}` にレビュー一覧 + 投稿フォームを統合。
  - もしくは `/products/{id}/reviews` GET/POST で責務切り出し。
- **編集機能**: 今回のスコープでは実装しない（削除→再投稿で対応）。
- **Middleware**
  - 投稿系は CSRF + 認証必須。

## 画面/UXメモ
- 商品詳細に以下を追加:
  1. 平均評価 (★表示) + 件数
  2. レビューリスト（最新順, ページング）
  3. 自身のレビューがあれば「削除」ボタンと注意書きのみ表示（編集なし）、未投稿なら入力フォーム
- エラー表示: 既存フォーム同様フラッシュメッセージ活用。

## API / ルーティング案
- GET `/products/{id}/reviews` : JSON API（Problem 42のAPI規約に合わせる）
- POST `/products/{id}/reviews` : 新規作成
- DELETE `/reviews/{id}` : 削除（ソフトデリート）

## テスト観点
1. 購入者のみが投稿できる（未購入ユーザーは 403）。
2. 1ユーザー1レビュー制約が機能する（投稿済みならエラー表示）。
3. レビュー削除後に再投稿できる（`deleted_at` を考慮）。
4. 平均評価計算が正しい（小数点第1位まで表示）。
5. API レスポンスが CSRF / 認可ルールを遵守している。

## 今後のタスク
- [ ] `product_reviews` マイグレーション作成
- [ ] `Review` エンティティ + Mapper 実装
- [ ] 購入判定を行う `ReviewService` 実装
- [ ] 商品詳細テンプレート改修（一覧・フォーム）
- [ ] PHPUnit / Feature テスト追加
