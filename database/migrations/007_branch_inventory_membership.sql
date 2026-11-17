-- Track whether a shared product belongs in each branch inventory.
-- Existing branch inventory rows remain available by default.
ALTER TABLE branch_inventory ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER stock_units;
