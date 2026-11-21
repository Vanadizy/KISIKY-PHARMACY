ALTER TABLE customers
  ADD COLUMN pharmacy_name VARCHAR(200) NULL AFTER full_name,
  ADD COLUMN pharmacy_address VARCHAR(500) NULL AFTER pharmacy_name;
