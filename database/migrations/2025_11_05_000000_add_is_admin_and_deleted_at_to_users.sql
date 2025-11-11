-- usersテーブルに管理者フラグとソフトデリート用カラムを追加
ALTER TABLE users
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password,
  ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER is_admin;

-- 既存データを安全な初期値に揃える
UPDATE users SET is_admin = 0 WHERE is_admin IS NULL;
UPDATE users SET deleted_at = NULL WHERE deleted_at IS NOT NULL;

-- 管理者判定と削除状態を組み合わせた検索用インデックス
CREATE INDEX users_is_admin_deleted_at_idx ON users (is_admin, deleted_at);
