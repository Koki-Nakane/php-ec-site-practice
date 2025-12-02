CREATE TABLE IF NOT EXISTS product_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(120) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_product_reviews_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE product_reviews
    ADD COLUMN active_flag TINYINT(1) AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) STORED;

CREATE INDEX idx_product_reviews_product_deleted ON product_reviews (product_id, deleted_at);
CREATE UNIQUE INDEX uq_product_reviews_active ON product_reviews (product_id, user_id, active_flag);
