-- Why: lookups by the owning record.
ALTER TABLE `item` ADD INDEX `idx_item_foreign_id` (`foreign_id`), ALGORITHM=INPLACE, LOCK=NONE;
