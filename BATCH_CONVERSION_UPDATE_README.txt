AIA PHARMACY - Batch Conversion and Pricing Update

What changed:
- Each product batch stores its own pieces_per_pack and packs_per_carton conversion.
- Each batch can store its own retail pack/carton and wholesale piece/pack/carton prices.
- POS uses the selected batch conversion and batch-specific prices, falling back to product prices when batch price overrides are NULL.
- New opening-stock batches created from the medicine form save the conversion and price fields.
- Purchases can specify optional batch-specific retail/wholesale prices.
- Removed a duplicated barcode input from POS.
- Stock remains tracked in individual pieces; selling a pack/carton deducts the corresponding number of pieces from the selected batch.
- Expired batches are excluded from POS and stock changes are done inside a transaction.

IMPORTANT DATABASE STEP (for the current live database):
1. Make a fresh phpMyAdmin export backup of aia_pharmacy before proceeding.
2. The conversion columns pieces_per_pack and packs_per_carton were already added in the earlier step.
3. Open database aia_pharmacy in phpMyAdmin, click SQL, and run database/migrations/2026_batch_pricing.sql ONCE.
4. Do not run that migration a second time: ALTER TABLE will report duplicate columns.
5. Then replace the matching project files from this ZIP, keeping a backup of each old file.
6. Test with a non-expired test batch and do not complete a sale until the selected unit, conversion, price, and stock are confirmed.

Notes:
- Blank batch price overrides use the corresponding product-level price.
- Retail piece price is stored in product_batches.selling_price (existing field).
- This update does not rewrite old sales, receipts, or stock movement history.
