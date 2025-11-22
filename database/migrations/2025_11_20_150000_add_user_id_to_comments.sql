-- Add user_id reference to comments for Problem 42
ALTER TABLE comments
    ADD COLUMN user_id INT NULL AFTER post_id;

-- Existingコメントはユーザー情報を持たないため、一旦 NULL 許容で追加する
-- 将来的には user_id を埋めたうえで NOT NULL 制約に切り替えることを想定

ALTER TABLE comments
    ADD CONSTRAINT comments_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL;
