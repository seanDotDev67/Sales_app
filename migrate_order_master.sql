-- Run once on the sales_app database (already applied on the dev machine).
RENAME TABLE orders TO order_master;
ALTER TABLE order_master
    ADD COLUMN status ENUM('placed','completed','cancelled') NOT NULL DEFAULT 'placed' AFTER total;

-- Each product belongs to one owner (existing products are assigned to the admin, user 1).
ALTER TABLE products
    ADD COLUMN owner_id INT(11) NULL AFTER id,
    ADD KEY idx_products_owner (owner_id),
    ADD CONSTRAINT fk_products_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL;
UPDATE products SET owner_id = 1 WHERE owner_id IS NULL;
