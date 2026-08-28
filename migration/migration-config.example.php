<?php
/**
 * Güven Hijyen -- Migration Configuration
 *
 * Copy this file to migration-config.php and update the values
 * for your environment.
 *
 * Usage:
 *   wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=dry_run
 *   wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=import
 */

return [
    'source' => [
        'type' => 'xlsx', // Supported: 'xlsx', 'csv', 'json', 'woocommerce_export'
        'file' => '/path/to/source-data.xlsx',
    ],

    'target' => [
        'type' => 'wordpress',
    ],

    'options' => [
        // Import mode: 'dry_run' validates without writing; 'import' writes to database.
        'mode' => 'dry_run',

        // Number of rows to process per batch. Lower values use less memory.
        'batch_size' => 50,

        // Set to true to skip image upload/attachment during import.
        'skip_images' => false,

        // Set to true to skip document upload/attachment during import.
        'skip_documents' => false,

        // Default WordPress post_status for imported products.
        // Recommended: 'draft' so products go through review before publishing.
        'default_publication_status' => 'draft',

        // Default procurement status for imported products.
        // Blank string means the product needs manual review.
        // Options: '', 'active', 'temporarily_unavailable', 'discontinued'
        'default_procurement_status' => '',

        // Absolute path to the directory containing import images.
        // The importer expects subdirectories: products/, categories/, brands/, sectors/, blog/
        'image_base_path' => '/path/to/import/images/',

        // Absolute path to the directory containing import documents.
        // The importer expects subdirectories: technical/, safety/, certificates/, catalogs/
        'document_base_path' => '/path/to/import/documents/',

        // If true, categories referenced by products but not found in the
        // 03_CATEGORIES sheet or WordPress will be created automatically.
        'create_missing_categories' => true,

        // If true, brands referenced by products but not found in the
        // 04_BRANDS sheet or WordPress will be created automatically.
        'create_missing_brands' => true,

        // If true, sectors referenced in 09_PRODUCT_SECTORS but not found
        // in 08_SECTORS or WordPress will be created automatically.
        // Recommended: false -- sectors should be defined explicitly.
        'create_missing_sectors' => false,

        // Log verbosity.
        // 'debug': every operation logged
        // 'info': summaries and notable events
        // 'warning': potential issues only
        // 'error': failures only
        'log_level' => 'info',
    ],

    'mapping' => [
        // Custom field mapping overrides.
        // Use this if your source XLSX column headers differ from the expected names.
        // Format: 'expected_field_name' => 'actual_column_header_in_xlsx'
        //
        // Example:
        // 'product_name' => 'Ürün Adı',
        // 'sku'          => 'Stok Kodu',
        // 'brand'        => 'Marka Adı',
    ],
];
