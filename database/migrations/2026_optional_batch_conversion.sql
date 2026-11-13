-- AIA PHARMACY: allow packaging counts to remain unknown until verified.
-- Run once against the aia_pharmacy database in phpMyAdmin.
-- Existing conversion values (for example 10 pieces/pack) are preserved.
ALTER TABLE products
  MODIFY COLUMN pieces_per_pack INT(10) UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN packs_per_carton INT(10) UNSIGNED NULL DEFAULT NULL;

ALTER TABLE product_batches
  MODIFY COLUMN pieces_per_pack INT(10) UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN packs_per_carton INT(10) UNSIGNED NULL DEFAULT NULL;
