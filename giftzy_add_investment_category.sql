-- Run this on an existing GiftZy database that predates the investment category field.
ALTER TABLE investments
  ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER purpose,
  ADD KEY idx_inv_category (category);
