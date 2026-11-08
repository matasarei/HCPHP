-- Why: lookups by code; MariaDB, so the migration can be applied twice.
CREATE INDEX IF NOT EXISTS `idx_item_code` ON `item` (`code`) ALGORITHM=INPLACE LOCK=NONE;
