-- Additive foundation for branch-scoped stock, staff assignments, payments and orders.
-- Back up the database before running. Existing inventory is copied to a default branch.

CREATE TABLE IF NOT EXISTS branches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  location VARCHAR(240) NULL,
  address TEXT NULL,
  phone VARCHAR(50) NULL,
  contact_email VARCHAR(190) NULL,
  finance_email VARCHAR(190) NULL,
  payment_instructions TEXT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS branch_inventory (
  branch_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  stock_units BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (branch_id, product_id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS user_branches (
  user_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, branch_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

ALTER TABLE orders ADD COLUMN branch_id BIGINT UNSIGNED NULL;
ALTER TABLE orders ADD CONSTRAINT fk_orders_branch FOREIGN KEY (branch_id) REFERENCES branches(id);
ALTER TABLE proforma_invoices ADD COLUMN branch_id BIGINT UNSIGNED NULL;
ALTER TABLE proforma_invoices ADD CONSTRAINT fk_proformas_branch FOREIGN KEY (branch_id) REFERENCES branches(id);
ALTER TABLE restocks ADD COLUMN branch_id BIGINT UNSIGNED NULL;
ALTER TABLE restocks ADD CONSTRAINT fk_restocks_branch FOREIGN KEY (branch_id) REFERENCES branches(id);
ALTER TABLE stock_movements ADD COLUMN branch_id BIGINT UNSIGNED NULL;
ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_branch FOREIGN KEY (branch_id) REFERENCES branches(id);
ALTER TABLE expenses ADD COLUMN branch_id BIGINT UNSIGNED NULL;
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_branch FOREIGN KEY (branch_id) REFERENCES branches(id);
ALTER TABLE order_items MODIFY box_price_snapshot DECIMAL(14,2) NULL;
ALTER TABLE order_items MODIFY line_total DECIMAL(14,2) NULL;

CREATE TABLE customer_order_payment_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('cash_at_branch','bank_transfer','mobile_money','other') NOT NULL,
  transaction_reference VARCHAR(120) NULL,
  evidence_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  rejection_reason VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(order_id) REFERENCES orders(id),
  FOREIGN KEY(branch_id) REFERENCES branches(id),
  FOREIGN KEY(customer_id) REFERENCES customers(id),
  FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX(branch_id,status)
);

INSERT INTO branches(name, location, address, phone, contact_email, finance_email, payment_instructions)
SELECT COALESCE(NULLIF((SELECT setting_value FROM pharmacy_settings WHERE setting_key='pharmacy_name' LIMIT 1),''),'Main Branch'),
       (SELECT setting_value FROM pharmacy_settings WHERE setting_key='location' LIMIT 1),
       (SELECT setting_value FROM pharmacy_settings WHERE setting_key='address' LIMIT 1),
       (SELECT setting_value FROM pharmacy_settings WHERE setting_key='contact_phone' LIMIT 1),
       (SELECT setting_value FROM pharmacy_settings WHERE setting_key='contact_email' LIMIT 1),
       (SELECT setting_value FROM pharmacy_settings WHERE setting_key='contact_email' LIMIT 1),
       ''
WHERE NOT EXISTS (SELECT 1 FROM branches);

INSERT IGNORE INTO branch_inventory(branch_id, product_id, stock_units)
SELECT (SELECT id FROM branches ORDER BY id LIMIT 1), product_id, stock_units FROM inventory;

UPDATE orders SET branch_id=(SELECT id FROM branches ORDER BY id LIMIT 1) WHERE branch_id IS NULL;
UPDATE proforma_invoices SET branch_id=(SELECT id FROM branches ORDER BY id LIMIT 1) WHERE branch_id IS NULL;
UPDATE restocks SET branch_id=(SELECT id FROM branches ORDER BY id LIMIT 1) WHERE branch_id IS NULL;
UPDATE stock_movements SET branch_id=(SELECT id FROM branches ORDER BY id LIMIT 1) WHERE branch_id IS NULL;
UPDATE expenses SET branch_id=(SELECT id FROM branches ORDER BY id LIMIT 1) WHERE branch_id IS NULL;
INSERT IGNORE INTO user_branches(user_id,branch_id)
SELECT u.id,(SELECT id FROM branches ORDER BY id LIMIT 1) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name<>'Administrator';

