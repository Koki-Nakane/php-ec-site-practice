-- Problem 42 schema updates: add slugs, status, comment counts

-- Extend posts table with slug, status, comment_count, updated_at
ALTER TABLE posts
    ADD COLUMN slug VARCHAR(255) NULL AFTER title,
    ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'published' AFTER content,
    ADD COLUMN comment_count INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- Ensure updated_at has a value for existing rows
UPDATE posts
SET updated_at = created_at
WHERE updated_at IS NULL;

-- Populate slug values based on title (fallback to post-{id})
UPDATE posts
SET slug = CONCAT(
        COALESCE(NULLIF(LOWER(REPLACE(REPLACE(REPLACE(title, '　', ' '), ' ', '-'), '--', '-')), ''), 'post'),
        '-',
        id
    )
WHERE slug IS NULL OR slug = '';

-- Guarantee slug is not empty even if title is missing
UPDATE posts
SET slug = CONCAT('post-', id)
WHERE slug = '' OR slug IS NULL;

-- Enforce NOT NULL + unique constraint on slug
ALTER TABLE posts
    MODIFY COLUMN slug VARCHAR(255) NOT NULL,
    ADD UNIQUE KEY posts_slug_unique (slug);

-- Helpful index for status filtering
CREATE INDEX IF NOT EXISTS posts_status_index ON posts (status);

-- Extend categories with slug column
ALTER TABLE categories
    ADD COLUMN slug VARCHAR(255) NULL AFTER name;

-- Populate category slugs from name
UPDATE categories
SET slug = LOWER(REPLACE(REPLACE(REPLACE(name, '　', ' '), ' ', '-'), '--', '-'))
WHERE slug IS NULL OR slug = '';

-- Fallback slug for categories in case sanitised name is empty
UPDATE categories
SET slug = CONCAT('category-', id)
WHERE slug = '' OR slug IS NULL;

-- Enforce NOT NULL + uniqueness on category slug
ALTER TABLE categories
    MODIFY COLUMN slug VARCHAR(255) NOT NULL,
    ADD UNIQUE KEY categories_slug_unique (slug);
