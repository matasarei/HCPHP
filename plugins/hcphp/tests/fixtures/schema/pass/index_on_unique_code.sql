-- Why: lookups by code.
CREATE INDEX `idx_item_unique_code` ON `item` (`unique_code`) ALGORITHM=INPLACE LOCK=NONE;
