-- productsテーブルに is_active, deleted_at カラムを追加
ALTER TABLE products
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER stock,
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER is_active;

-- 既存データの初期化（is_active=1, deleted_at=NULL）
UPDATE products SET is_active = 1, deleted_at = NULL WHERE is_active IS NULL OR deleted_at IS NOT NULL;
