-- Problem 42: posts に author を紐づける
ALTER TABLE posts
    ADD COLUMN user_id INT NULL AFTER id;

ALTER TABLE posts
    ADD CONSTRAINT posts_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL;
