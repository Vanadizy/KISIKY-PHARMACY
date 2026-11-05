-- Store independent selling and buying prices per branch.
-- Back up first. Apply after migrations 002 and 003.
ALTER TABLE branch_inventory ADD COLUMN box_price DECIMAL(14,2) NULL AFTER stock_units;
ALTER TABLE branch_inventory ADD COLUMN cost_price DECIMAL(14,2) NULL AFTER box_price;
UPDATE branch_inventory bi JOIN products p ON p.id=bi.product_id SET bi.box_price=p.box_price,bi.cost_price=p.cost_price;
ALTER TABLE branch_inventory MODIFY box_price DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE branch_inventory MODIFY cost_price DECIMAL(14,2) NOT NULL DEFAULT 0;
