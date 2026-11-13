-- AIA PHARMACY: batch-specific retail/wholesale pricing
-- BACK UP the aia_pharmacy database before running this migration.
-- Run ONCE on the live database after pieces_per_pack and packs_per_carton
-- have already been added to product_batches.

ALTER TABLE product_batches
  ADD COLUMN retail_pack_price DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN retail_carton_price DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN wholesale_piece_price DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN wholesale_pack_price DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN wholesale_carton_price DECIMAL(12,2) NULL DEFAULT NULL;

-- Existing batches inherit the current medicine-level prices as their starting prices.
-- New purchases may override these prices for each batch; blank overrides use medicine defaults.
UPDATE product_batches b
JOIN products p ON p.id = b.product_id
SET b.retail_pack_price = p.retail_pack_price,
    b.retail_carton_price = p.retail_carton_price,
    b.wholesale_piece_price = p.wholesale_piece_price,
    b.wholesale_pack_price = p.wholesale_pack_price,
    b.wholesale_carton_price = p.wholesale_carton_price;
