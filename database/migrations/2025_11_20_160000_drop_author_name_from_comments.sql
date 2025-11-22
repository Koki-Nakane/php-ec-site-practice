-- Remove legacy author_name snapshot column from comments
ALTER TABLE comments
    DROP COLUMN author_name;
