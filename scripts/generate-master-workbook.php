<?php
/**
 * Generate the Master XLSX Import Workbook for Güven Hijyen.
 *
 * Usage (standalone):   php generate-master-workbook.php [output_path]
 * Usage (WP CLI):       wp eval-file scripts/generate-master-workbook.php
 *
 * Requires PhpSpreadsheet OR falls back to a simple XLSX writer included below.
 */

if ( php_sapi_name() !== 'cli' ) {
    die( 'CLI only.' );
}

$output_path = $argv[1] ?? __DIR__ . '/../import/guven-hijyen-master.xlsx';

class GH_Simple_Xlsx_Writer {

    private array $sheets = [];

    public function add_sheet( string $name, array $headers, array $data = [], array $column_widths = [] ): void {
        $this->sheets[] = [
            'name'    => $name,
            'headers' => $headers,
            'data'    => $data,
            'widths'  => $column_widths,
        ];
    }

    public function save( string $path ): bool {
        if ( class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
            return $this->save_with_phpspreadsheet( $path );
        }
        return $this->save_native( $path );
    }

    private function save_with_phpspreadsheet( string $path ): bool {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex( 0 );

        foreach ( $this->sheets as $i => $sheet_data ) {
            $ws = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet( $spreadsheet, $sheet_data['name'] );
            $spreadsheet->addSheet( $ws, $i );

            $col = 1;
            foreach ( $sheet_data['headers'] as $header ) {
                $ws->setCellValueByColumnAndRow( $col, 1, $header );
                $ws->getStyleByColumnAndRow( $col, 1 )->getFont()->setBold( true );
                $ws->getStyleByColumnAndRow( $col, 1 )->getFill()
                    ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                    ->getStartColor()->setARGB( 'FF2E5090' );
                $ws->getStyleByColumnAndRow( $col, 1 )->getFont()->getColor()->setARGB( 'FFFFFFFF' );

                if ( isset( $sheet_data['widths'][ $col - 1 ] ) ) {
                    $ws->getColumnDimensionByColumn( $col )->setWidth( $sheet_data['widths'][ $col - 1 ] );
                } else {
                    $ws->getColumnDimensionByColumn( $col )->setAutoSize( true );
                }
                $col++;
            }

            foreach ( $sheet_data['data'] as $row_idx => $row ) {
                $col = 1;
                foreach ( $row as $value ) {
                    $ws->setCellValueByColumnAndRow( $col, $row_idx + 2, $value );
                    $col++;
                }
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( $path );
        return true;
    }

    private function save_native( string $path ): bool {
        $zip = new ZipArchive();
        if ( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return false;
        }

        $zip->addFromString( '[Content_Types].xml', $this->content_types_xml() );
        $zip->addFromString( '_rels/.rels', $this->rels_xml() );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->workbook_rels_xml() );
        $zip->addFromString( 'xl/styles.xml', $this->styles_xml() );
        $zip->addFromString( 'xl/workbook.xml', $this->workbook_xml() );

        $shared_strings = [];
        $ss_index       = [];

        foreach ( $this->sheets as $si => $sheet_data ) {
            foreach ( $sheet_data['headers'] as $h ) {
                if ( ! isset( $ss_index[ $h ] ) ) {
                    $ss_index[ $h ]  = count( $shared_strings );
                    $shared_strings[] = $h;
                }
            }
            foreach ( $sheet_data['data'] as $row ) {
                foreach ( $row as $val ) {
                    $val = (string) $val;
                    if ( $val !== '' && ! is_numeric( $val ) && ! isset( $ss_index[ $val ] ) ) {
                        $ss_index[ $val ]  = count( $shared_strings );
                        $shared_strings[] = $val;
                    }
                }
            }
        }

        $zip->addFromString( 'xl/sharedStrings.xml', $this->shared_strings_xml( $shared_strings ) );

        foreach ( $this->sheets as $si => $sheet_data ) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ( $si + 1 ) . '.xml',
                $this->sheet_xml( $sheet_data, $ss_index )
            );
        }

        $zip->close();
        return true;
    }

    private function col_letter( int $col ): string {
        $letter = '';
        while ( $col >= 0 ) {
            $letter = chr( 65 + ( $col % 26 ) ) . $letter;
            $col    = intdiv( $col, 26 ) - 1;
        }
        return $letter;
    }

    private function esc( string $s ): string {
        return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    private function content_types_xml(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $xml .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        foreach ( $this->sheets as $i => $_ ) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ( $i + 1 ) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '</Types>';
        return $xml;
    }

    private function rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook_rels_xml(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ( $this->sheets as $i => $_ ) {
            $xml .= '<Relationship Id="rId' . ( $i + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ( $i + 1 ) . '.xml"/>';
        }
        $n = count( $this->sheets );
        $xml .= '<Relationship Id="rId' . ( $n + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '<Relationship Id="rId' . ( $n + 2 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private function workbook_xml(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheets>';
        foreach ( $this->sheets as $i => $sheet_data ) {
            $xml .= '<sheet name="' . $this->esc( $sheet_data['name'] ) . '" sheetId="' . ( $i + 1 ) . '" r:id="rId' . ( $i + 1 ) . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2E5090"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '</styleSheet>';
    }

    private function shared_strings_xml( array $strings ): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '" uniqueCount="' . count( $strings ) . '">';
        foreach ( $strings as $s ) {
            $xml .= '<si><t>' . $this->esc( $s ) . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    private function sheet_xml( array $sheet_data, array $ss_index ): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        $max_col = count( $sheet_data['headers'] );
        $max_row = count( $sheet_data['data'] ) + 1;
        $xml .= '<dimension ref="A1:' . $this->col_letter( $max_col - 1 ) . $max_row . '"/>';

        if ( ! empty( $sheet_data['widths'] ) ) {
            $xml .= '<cols>';
            foreach ( $sheet_data['widths'] as $ci => $w ) {
                $xml .= '<col min="' . ( $ci + 1 ) . '" max="' . ( $ci + 1 ) . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        $xml .= '<row r="1">';
        foreach ( $sheet_data['headers'] as $ci => $h ) {
            $ref = $this->col_letter( $ci ) . '1';
            $xml .= '<c r="' . $ref . '" t="s" s="1"><v>' . $ss_index[ $h ] . '</v></c>';
        }
        $xml .= '</row>';

        foreach ( $sheet_data['data'] as $ri => $row ) {
            $row_num = $ri + 2;
            $xml .= '<row r="' . $row_num . '">';
            $ci = 0;
            foreach ( $row as $val ) {
                $ref = $this->col_letter( $ci ) . $row_num;
                $val = (string) $val;
                if ( $val === '' ) {
                    $xml .= '<c r="' . $ref . '"/>';
                } elseif ( is_numeric( $val ) ) {
                    $xml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
                } else {
                    $idx = $ss_index[ $val ] ?? 0;
                    $xml .= '<c r="' . $ref . '" t="s"><v>' . $idx . '</v></c>';
                }
                $ci++;
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }
}

$writer = new GH_Simple_Xlsx_Writer();

$writer->add_sheet( '01_PRODUCTS', [
    'migration_key', 'source_product_id', 'existing_wp_post_id',
    'product_name', 'sku', 'slug', 'product_type',
    'parent_sku', 'short_description', 'long_description',
    'category', 'subcategory', 'brand',
    'sales_unit', 'minimum_quantity', 'quantity_step',
    'procurement_status', 'featured_image', 'gallery_images',
    'publication_status', 'seo_title', 'meta_description',
], [], array_fill( 0, 22, 20 ) );

$writer->add_sheet( '02_VARIATIONS', [
    'parent_sku', 'variation_sku', 'variation_name',
    'attribute_1_name', 'attribute_1_value',
    'attribute_2_name', 'attribute_2_value',
    'sales_unit', 'minimum_quantity', 'quantity_step',
    'procurement_status', 'featured_image',
], [], array_fill( 0, 12, 18 ) );

$writer->add_sheet( '03_CATEGORIES', [
    'category_name', 'parent_category', 'slug',
    'description', 'image', 'display_order',
    'seo_title', 'meta_description',
], [], array_fill( 0, 8, 20 ) );

$writer->add_sheet( '04_BRANDS', [
    'brand_name', 'slug', 'description',
    'logo', 'website', 'verified', 'ready',
], [], array_fill( 0, 7, 18 ) );

$writer->add_sheet( '05_ATTRIBUTES', [
    'attribute_name', 'attribute_slug', 'type',
    'values', 'display_order',
], [], [ 20, 20, 12, 40, 12 ] );

$writer->add_sheet( '06_PRODUCT_ATTRIBUTES', [
    'sku', 'attribute_name', 'attribute_value',
    'visible', 'filterable',
], [], array_fill( 0, 5, 18 ) );

$writer->add_sheet( '07_COMPATIBILITY', [
    'source_sku', 'target_sku', 'relationship_type',
], [], [ 18, 18, 25 ] );

$writer->add_sheet( '08_SECTORS', [
    'sector_name', 'slug', 'description',
    'image', 'icon', 'ready',
], [], array_fill( 0, 6, 18 ) );

$writer->add_sheet( '09_PRODUCT_SECTORS', [
    'sku', 'sector_name',
], [], [ 18, 25 ] );

$writer->add_sheet( '10_DOCUMENTS', [
    'document_key', 'title', 'type', 'description',
    'file_path', 'version', 'document_date',
    'document_code', 'revision_code',
], [], array_fill( 0, 9, 18 ) );

$writer->add_sheet( '11_DOCUMENT_RELATIONS', [
    'document_key', 'relation_type', 'relation_identifier',
], [], [ 18, 18, 25 ] );

$writer->add_sheet( '12_IMAGES', [
    'sku_or_identifier', 'image_type', 'filename',
    'display_order', 'alt_text',
], [], [ 18, 15, 25, 12, 30 ] );

$writer->add_sheet( '13_BLOG', [
    'post_title', 'slug', 'content', 'excerpt',
    'category', 'featured_image', 'author',
    'publication_status', 'content_quality_status',
    'seo_title', 'meta_description',
    'related_products', 'related_categories',
], [], array_fill( 0, 13, 20 ) );

$writer->add_sheet( '14_REDIRECTS', [
    'source_url', 'target_url', 'redirect_type', 'notes',
], [], [ 30, 30, 12, 25 ] );

$writer->add_sheet( '15_IMPORT_ERRORS', [
    'row_number', 'sheet', 'migration_key', 'sku',
    'field', 'error_code', 'message',
    'severity', 'recommended_action',
], [], array_fill( 0, 9, 18 ) );

$dir = dirname( $output_path );
if ( ! is_dir( $dir ) ) {
    mkdir( $dir, 0755, true );
}

if ( $writer->save( $output_path ) ) {
    echo "Master XLSX workbook generated: {$output_path}\n";
    echo 'File size: ' . number_format( filesize( $output_path ) ) . " bytes\n";
    echo "Sheets: 15\n";
} else {
    echo "ERROR: Failed to generate workbook.\n";
    exit( 1 );
}
