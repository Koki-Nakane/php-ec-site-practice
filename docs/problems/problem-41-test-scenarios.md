# Problem 41 – 管理画面テストシナリオ

## シードスクリプトの実行手順
- コマンド: `php scripts/seed_admin_dashboard_test_data.php`
- 実行環境: Docker コンテナ内の PHP CLI またはホスト環境で PHP 8.3 が利用できるシェル
- 期待動作: 管理画面検証用のユーザー・商品・注文データを再作成し、処理結果を標準出力にサマリ表示

## 登録されるテストデータ概要
- 管理者ユーザー: `admin.manager@example.com` / パスワード `AdminPass123!`
- 一般ユーザー: `standard.user01@example.com`, `vip.user01@example.com`
- ソフト削除済みユーザー: `archived.user01@example.com`
- 商品: 公開中「エスプレッソマシン（管理テスト）」、「限定コーヒー豆（管理テスト）」、非公開「ハンドドリップセット（管理テスト）」、同豆はシード完了後にソフト削除済みとなる
- 注文タグ 001: 標準ユーザー、PROCESSING、非公開商品を含む（配送先に `[ADMIN-TEST-ORDER-001]`）
- 注文タグ 002: VIP ユーザー、SHIPPED（配送先に `[ADMIN-TEST-ORDER-002]`）
- 注文タグ 003: 標準ユーザー、COMPLETED、削除済み商品を含む（配送先に `[ADMIN-TEST-ORDER-003]`）
- 注文タグ 004: アーカイブ済みユーザー、CANCELED、注文自体もソフト削除済み（配送先に `[ADMIN-TEST-ORDER-004]`）

## 手動テストシナリオ
- **ログイン確認**: `admin.manager@example.com` / `AdminPass123!` でログインし `/admin` に遷移できること
- **ユーザー一覧**: `/admin/users` で 4 名が表示され、`archived.user01@example.com` に「削除済」が付与されていることを確認。フィルタ `?status=deleted` でアーカイブのみ抽出される。
- **ユーザー編集**: `standard.user01@example.com` の編集画面で権限切替・住所更新が可能、CSRF エラー時のフラッシュも表示されること。
- **商品一覧**: `/admin/products` で 3 商品が表示され、ハンドドリップセットが「非公開」、限定コーヒー豆が「削除済」と表示されること。
- **商品編集**: エスプレッソマシンの編集画面で価格更新→保存→一覧に反映されること。ハンドドリップセットを「公開」に切り替えられること。
- **注文一覧**: `/admin/orders` で 4 件が表示され、ステータスと削除状態バッジが想定どおりであること。
  - フィルタ `?status=2` で SHIPPED のみ (ORDER-002) が抽出される。
  - フィルタ `?deleted=deleted` で ORDER-004 のみ表示。
- **注文詳細更新**: ORDER-001 の編集画面でステータスを `COMPLETED` に変更して保存、フラッシュメッセージと一覧更新を確認。
- **注文削除/復元**: ORDER-004 の詳細で「注文を復元する」を実行し一覧で削除バッジが消えること、その後再度削除できること。
- **連携確認**: 注文詳細でユーザーリンク `/admin/users/edit?id=...` が正しく遷移し、削除済みユーザーのフラッシュ挙動も確認。
