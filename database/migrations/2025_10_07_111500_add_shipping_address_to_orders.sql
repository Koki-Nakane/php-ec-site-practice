ALTER TABLE `orders`
    ADD COLUMN `shipping_address` text NOT NULL AFTER `total_price`;

UPDATE `orders` AS o
INNER JOIN `users` AS u ON u.id = o.user_id
SET o.shipping_address = COALESCE(u.address, '');
