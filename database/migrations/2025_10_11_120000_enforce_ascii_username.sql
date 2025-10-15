ALTER TABLE `users`
    ADD CONSTRAINT `users_name_ascii` CHECK (`name` REGEXP '^[A-Za-z0-9_]+$');
