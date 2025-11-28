# Problem 44 – レビュー機能 設計メモ

## 機能概要
- 対象: 購入済み商品のレビュー（評価 + コメント）投稿・閲覧機能を追加する。
- 目的: 購入者によるフィードバックを表示し、商品詳細ページに信頼度を付与する。
- 利用者ロール:
  - ログイン済み一般ユーザー: 自分が購入した商品のみレビュー投稿/編集/削除可。
  - 管理者: すべてのレビューを閲覧・削除可能。評価の変更は原則不可（証跡保持）。

## データモデル案
- 新テーブル `product_reviews`
  - `id (PK)`
  - `product_id (FK -> products.id)`
  - `user_id (FK -> users.id)`
  - `rating` (TINYINT 1-5)
  - `comment` (TEXT)
  - `created_at` / `updated_at`
  - `deleted_at` (ソフトデリートで荒らし対応)
- 付随インデックス
  - `(product_id, deleted_at)` for listing reviews quickly.
  - `(user_id, product_id)` unique + `deleted_at IS NULL` で「1ユーザー1商品1レビュー」を担保。

## バリデーション方針
- `rating`: 整数, 1〜5 のみ許可。
- `comment`: 500文字以内、HTML不可（エスケープ表示）。
- レビュー投稿時に `orders` / `order_items` を参照し、購入実績があるかをチェック。
- 同一商品に対して既存レビューがある場合は編集フローへ誘導。

## アーキテクチャ設計
- **Mapper**: `ReviewMapper`（新規）
  - `findByProduct(int $productId, int $page, int $perPage)`
  - `findByUserAndProduct(int $userId, int $productId)`
  - `save(Review $review)` / `softDelete(int $reviewId)`
- **Service層**: `ReviewService`
  - 購入済み判定ロジック／平均評価計算／通知（今後拡張）
- **Controller**: `ProductController`
  - 商品詳細 `/products/{id}` にレビュー一覧 + 投稿フォームを統合。
  - もしくは `/products/{id}/reviews` GET/POST で責務切り出し。
- **Middleware**
  - 投稿系は CSRF + 認証必須。

## 画面/UXメモ
- 商品詳細に以下を追加:
  1. 平均評価 (★表示) + 件数
  2. レビューリスト（最新順, ページング）
  3. 自身のレビューがあれば「編集」「削除」ボタン表示、未投稿なら入力フォーム
- エラー表示: 既存フォーム同様フラッシュメッセージ活用。

## API / ルーティング案
- GET `/products/{id}/reviews` : JSON API（Problem 42のAPI規約に合わせる）
- POST `/products/{id}/reviews` : 新規作成
- PATCH `/reviews/{id}` : 編集（所有者 or 管理者）
- DELETE `/reviews/{id}` : 削除（ソフトデリート）

## テスト観点
1. 購入者のみが投稿できる（未購入ユーザーは 403）。
2. 1ユーザー1レビュー制約が機能する（編集パスに誘導）。
3. レビュー削除後に再投稿できる（`deleted_at` を考慮）。
4. 平均評価計算が正しい（小数点第1位まで表示）。
5. API レスポンスが CSRF / 認可ルールを遵守している。

## 今後のタスク
- [ ] `product_reviews` マイグレーション作成
- [ ] `Review` エンティティ + Mapper 実装
- [ ] 購入判定を行う `ReviewService` 実装
- [ ] 商品詳細テンプレート改修（一覧・フォーム）
- [ ] PHPUnit / Feature テスト追加
