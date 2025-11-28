-- Password reset tokens table keeps track of issued reset links
CREATE TABLE password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY password_resets_token_unique (token),
    KEY password_resets_user_id_idx (user_id),
    KEY password_resets_expires_at_idx (expires_at),
    CONSTRAINT password_resets_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
);
