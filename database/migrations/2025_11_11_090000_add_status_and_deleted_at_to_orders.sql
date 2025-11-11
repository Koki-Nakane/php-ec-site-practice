-- 管理画面での運用に備えて注文ステータスとソフトデリート用カラムを追加
ALTER TABLE `orders`
  ADD COLUMN `status` TINYINT NOT NULL DEFAULT 0 AFTER `shipping_address`,
  ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`;

-- 既存データのステータスを初期値に統一
UPDATE `orders`
SET `status` = 0
WHERE `status` IS NULL;

-- 状態管理で利用する複合インデックス
CREATE INDEX `orders_status_deleted_at_idx` ON `orders` (`status`, `deleted_at`);
