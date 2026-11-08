-- Why: /rating/list/ filters by faculty and year; EXPLAIN showed a full scan of 41k rows.
/* Undo: ALTER TABLE `rating` DROP INDEX `idx_rating_faculty_year`; */
ALTER TABLE `rating`
    ADD INDEX IF NOT EXISTS `idx_rating_faculty_year` (`faculty_id`, `year`),
    ALGORITHM = INPLACE, LOCK = NONE;

# A second index, the CREATE INDEX form.
CREATE INDEX `idx_log_time` ON `log` (`time`) ALGORITHM = INPLACE LOCK = NONE;
