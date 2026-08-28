<?php
/**
 * Güven Hijyen -- CLI Migration Runner
 *
 * Runs the Master XLSX Import System from the command line via WP-CLI.
 *
 * Usage:
 *   wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=dry_run
 *   wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=import
 *
 * Options:
 *   --config=<path>    Path to migration configuration file (required).
 *   --mode=<mode>      Override the mode in config. Options: dry_run, import.
 *   --sheet=<name>     Process only a specific sheet (e.g., 01_PRODUCTS).
 *   --skip-images      Skip image processing regardless of config.
 *   --skip-documents   Skip document processing regardless of config.
 *   --batch-size=<n>   Override batch size from config.
 *   --verbose          Enable debug-level logging regardless of config.
 */

defined('ABSPATH') || exit;

// -------------------------------------------------------------------------
// 1. Parse command-line arguments
// -------------------------------------------------------------------------

$args = gh_migration_parse_args($GLOBALS['argv'] ?? []);

if (empty($args['config'])) {
    gh_migration_error('Missing required --config argument.');
    gh_migration_usage();
    exit(1);
}

$config_path = $args['config'];
if (!file_exists($config_path)) {
    // Try relative to ABSPATH.
    $config_path = ABSPATH . ltrim($args['config'], '/');
}

if (!file_exists($config_path)) {
    gh_migration_error("Configuration file not found: {$args['config']}");
    exit(1);
}

// -------------------------------------------------------------------------
// 2. Load configuration
// -------------------------------------------------------------------------

$config = require $config_path;

if (!is_array($config)) {
    gh_migration_error('Configuration file must return an array.');
    exit(1);
}

// Apply CLI overrides.
if (!empty($args['mode'])) {
    $config['options']['mode'] = $args['mode'];
}
if (!empty($args['batch-size'])) {
    $config['options']['batch_size'] = (int) $args['batch-size'];
}
if (!empty($args['skip-images'])) {
    $config['options']['skip_images'] = true;
}
if (!empty($args['skip-documents'])) {
    $config['options']['skip_documents'] = true;
}
if (!empty($args['verbose'])) {
    $config['options']['log_level'] = 'debug';
}

$mode = $config['options']['mode'] ?? 'dry_run';

if (!in_array($mode, ['dry_run', 'import'], true)) {
    gh_migration_error("Invalid mode: {$mode}. Must be 'dry_run' or 'import'.");
    exit(1);
}

// -------------------------------------------------------------------------
// 3. Validate source file
// -------------------------------------------------------------------------

$source_file = $config['source']['file'] ?? '';

if (!file_exists($source_file)) {
    gh_migration_error("Source file not found: {$source_file}");
    exit(1);
}

$file_hash = hash_file('sha256', $source_file);
$file_size = filesize($source_file);

gh_migration_log("=================================================================");
gh_migration_log("Güven Hijyen Migration Runner");
gh_migration_log("=================================================================");
gh_migration_log("Source file : {$source_file}");
gh_migration_log("File hash   : {$file_hash}");
gh_migration_log("File size   : " . size_format($file_size));
gh_migration_log("Mode        : {$mode}");
gh_migration_log("Batch size  : " . ($config['options']['batch_size'] ?? 50));
gh_migration_log("Log level   : " . ($config['options']['log_level'] ?? 'info'));
gh_migration_log("Sheet filter: " . ($args['sheet'] ?? 'all'));
gh_migration_log("=================================================================");
gh_migration_log("");

// -------------------------------------------------------------------------
// 4. Verify WordPress environment
// -------------------------------------------------------------------------

if (!class_exists('WooCommerce')) {
    gh_migration_error('WooCommerce is not active. Cannot proceed with migration.');
    exit(1);
}

if (!class_exists('GH_Import_Error_Report')) {
    $error_report_path = WP_PLUGIN_DIR . '/guvenhijyen-core/import/class-import-error-report.php';
    if (file_exists($error_report_path)) {
        require_once $error_report_path;
    } else {
        gh_migration_error('GH_Import_Error_Report class not found. Is guvenhijyen-core active?');
        exit(1);
    }
}

// Ensure import tables exist.
GH_Import_Error_Report::create_tables();

// -------------------------------------------------------------------------
// 5. Create audit record
// -------------------------------------------------------------------------

$import_id = GH_Import_Error_Report::create_audit([
    'source_file' => basename($source_file),
    'file_hash'   => $file_hash,
    'mode'        => $mode,
]);

gh_migration_log("Import ID: {$import_id}");
gh_migration_log("");

// -------------------------------------------------------------------------
// 6. Define sheet processing order
// -------------------------------------------------------------------------

$sheet_order = [
    '03_CATEGORIES',
    '04_BRANDS',
    '05_ATTRIBUTES',
    '08_SECTORS',
    '01_PRODUCTS',
    '02_VARIATIONS',
    '06_PRODUCT_ATTRIBUTES',
    '07_COMPATIBILITY',
    '09_PRODUCT_SECTORS',
    '10_DOCUMENTS',
    '11_DOCUMENT_RELATIONS',
    '12_IMAGES',
    '13_BLOG',
    '14_REDIRECTS',
];

// Filter to specific sheet if requested.
if (!empty($args['sheet'])) {
    $requested_sheet = $args['sheet'];
    if (!in_array($requested_sheet, $sheet_order, true)) {
        gh_migration_error("Unknown sheet: {$requested_sheet}");
        gh_migration_log("Valid sheets: " . implode(', ', $sheet_order));
        exit(1);
    }
    $sheet_order = [$requested_sheet];
    gh_migration_log("Processing single sheet: {$requested_sheet}");
    gh_migration_log("");
}

// -------------------------------------------------------------------------
// 7. Run import engine
// -------------------------------------------------------------------------

$totals = [
    'total_rows'    => 0,
    'created'       => 0,
    'updated'       => 0,
    'skipped'       => 0,
    'manual_review' => 0,
    'failed'        => 0,
];

$start_time = microtime(true);

foreach ($sheet_order as $sheet_name) {
    gh_migration_log("--- Processing: {$sheet_name} ---");

    // In a full implementation, each sheet would have a dedicated processor class.
    // For example: GH_Import_Products_Processor, GH_Import_Categories_Processor, etc.
    // The processor would:
    //   1. Read rows from the sheet.
    //   2. Validate each row against the schema.
    //   3. In import mode, create/update WordPress entities.
    //   4. Report errors via GH_Import_Error_Report::add_error().
    //   5. Return counts for the reconciliation report.

    $processor_class = gh_migration_get_processor_class($sheet_name);

    if (!$processor_class || !class_exists($processor_class)) {
        gh_migration_log("  Processor not implemented for {$sheet_name}. Skipping.");
        gh_migration_log("");
        continue;
    }

    try {
        $processor = new $processor_class($source_file, $sheet_name, $config, $import_id);
        $result = $processor->process($mode);

        $totals['total_rows']    += $result['total_rows'] ?? 0;
        $totals['created']       += $result['created'] ?? 0;
        $totals['updated']       += $result['updated'] ?? 0;
        $totals['skipped']       += $result['skipped'] ?? 0;
        $totals['manual_review'] += $result['manual_review'] ?? 0;
        $totals['failed']        += $result['failed'] ?? 0;

        gh_migration_log("  Rows: {$result['total_rows']} | Created: {$result['created']} | Updated: {$result['updated']} | Skipped: {$result['skipped']} | Failed: {$result['failed']}");
    } catch (\Throwable $e) {
        gh_migration_error("  Fatal error processing {$sheet_name}: " . $e->getMessage());

        GH_Import_Error_Report::add_error($import_id, [
            'sheet_name'         => $sheet_name,
            'row_number'         => 0,
            'error_code'         => 'fatal_error',
            'message'            => $e->getMessage(),
            'recommended_action' => 'Check the source file and processor implementation.',
            'severity'           => 'error',
        ]);
    }

    gh_migration_log("");
}

$elapsed = round(microtime(true) - $start_time, 2);

// -------------------------------------------------------------------------
// 8. Update audit record
// -------------------------------------------------------------------------

GH_Import_Error_Report::update_audit($import_id, array_merge($totals, [
    'status'       => 'completed',
    'completed_at' => current_time('mysql', true),
]));

// -------------------------------------------------------------------------
// 9. Generate reconciliation report
// -------------------------------------------------------------------------

gh_migration_log("=================================================================");
gh_migration_log("RECONCILIATION REPORT");
gh_migration_log("=================================================================");
gh_migration_log("Import ID     : {$import_id}");
gh_migration_log("Mode          : {$mode}");
gh_migration_log("Duration      : {$elapsed}s");
gh_migration_log("-----------------------------------------------------------------");
gh_migration_log("Total rows    : {$totals['total_rows']}");
gh_migration_log("Created       : {$totals['created']}");
gh_migration_log("Updated       : {$totals['updated']}");
gh_migration_log("Skipped       : {$totals['skipped']}");
gh_migration_log("Manual review : {$totals['manual_review']}");
gh_migration_log("Failed        : {$totals['failed']}");
gh_migration_log("-----------------------------------------------------------------");

// Error summary.
$error_summary = GH_Import_Error_Report::get_error_summary($import_id);
gh_migration_log("Errors        : {$error_summary['error']}");
gh_migration_log("Warnings      : {$error_summary['warning']}");
gh_migration_log("Info          : {$error_summary['info']}");
gh_migration_log("=================================================================");

if ($error_summary['error'] > 0) {
    gh_migration_log("");
    gh_migration_log("ERROR DETAILS (first 20):");
    gh_migration_log("-----------------------------------------------------------------");

    $errors = GH_Import_Error_Report::get_errors($import_id, 'error', 1, 20);
    foreach ($errors['rows'] as $err) {
        gh_migration_log(sprintf(
            "  [%s] Row %d in %s: %s (field: %s)",
            $err['error_code'],
            $err['row_number'],
            $err['sheet_name'],
            $err['message'],
            $err['field'] ?: 'n/a'
        ));
        if (!empty($err['recommended_action'])) {
            gh_migration_log("    -> {$err['recommended_action']}");
        }
    }

    if ($errors['total'] > 20) {
        gh_migration_log("  ... and " . ($errors['total'] - 20) . " more errors.");
        gh_migration_log("  Run: wp eval 'print_r(GH_Import_Error_Report::get_errors(\"{$import_id}\", \"error\", 1, 500));'");
    }
}

if ($mode === 'dry_run') {
    gh_migration_log("");
    if ($error_summary['error'] === 0) {
        gh_migration_log("Dry run PASSED. No errors found. Safe to run with --mode=import.");
    } else {
        gh_migration_log("Dry run FAILED. Fix the errors above and re-run.");
    }
}

gh_migration_log("");
gh_migration_log("Done.");

// =========================================================================
// Helper functions
// =========================================================================

/**
 * Parse CLI arguments from $argv into a key-value array.
 */
function gh_migration_parse_args(array $argv): array {
    $args = [];

    foreach ($argv as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }

        $arg = ltrim($arg, '-');

        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $args[$key] = $value;
        } else {
            $args[$arg] = true;
        }
    }

    return $args;
}

/**
 * Map sheet name to processor class name.
 */
function gh_migration_get_processor_class(string $sheet_name): ?string {
    $map = [
        '01_PRODUCTS'          => 'GH_Import_Products_Processor',
        '02_VARIATIONS'        => 'GH_Import_Variations_Processor',
        '03_CATEGORIES'        => 'GH_Import_Categories_Processor',
        '04_BRANDS'            => 'GH_Import_Brands_Processor',
        '05_ATTRIBUTES'        => 'GH_Import_Attributes_Processor',
        '06_PRODUCT_ATTRIBUTES'=> 'GH_Import_Product_Attributes_Processor',
        '07_COMPATIBILITY'     => 'GH_Import_Compatibility_Processor',
        '08_SECTORS'           => 'GH_Import_Sectors_Processor',
        '09_PRODUCT_SECTORS'   => 'GH_Import_Product_Sectors_Processor',
        '10_DOCUMENTS'         => 'GH_Import_Documents_Processor',
        '11_DOCUMENT_RELATIONS'=> 'GH_Import_Document_Relations_Processor',
        '12_IMAGES'            => 'GH_Import_Images_Processor',
        '13_BLOG'              => 'GH_Import_Blog_Processor',
        '14_REDIRECTS'         => 'GH_Import_Redirects_Processor',
    ];

    return $map[$sheet_name] ?? null;
}

/**
 * Log a message to STDOUT.
 */
function gh_migration_log(string $message): void {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        echo $message . PHP_EOL;
    }
}

/**
 * Log an error message to STDERR.
 */
function gh_migration_error(string $message): void {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::error($message, false);
    } else {
        fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    }
}

/**
 * Print usage instructions.
 */
function gh_migration_usage(): void {
    gh_migration_log('');
    gh_migration_log('Usage:');
    gh_migration_log('  wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=dry_run');
    gh_migration_log('  wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=import');
    gh_migration_log('');
    gh_migration_log('Options:');
    gh_migration_log('  --config=<path>     Path to migration config file (required)');
    gh_migration_log('  --mode=<mode>       dry_run or import (overrides config)');
    gh_migration_log('  --sheet=<name>      Process only a specific sheet');
    gh_migration_log('  --skip-images       Skip image processing');
    gh_migration_log('  --skip-documents    Skip document processing');
    gh_migration_log('  --batch-size=<n>    Override batch size');
    gh_migration_log('  --verbose           Enable debug logging');
}
