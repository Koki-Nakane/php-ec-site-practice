-- usersテーブルに最終ログイン日時を追加
ALTER TABLE users
  ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- 既存ユーザーには updated_at を初期値として設定
UPDATE users
  SET last_login_at = updated_at
  WHERE last_login_at IS NULL;
