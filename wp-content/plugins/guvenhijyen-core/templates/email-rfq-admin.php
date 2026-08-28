<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * @var array $rfq  RFQ data array
 * @var array $items RFQ items array
 */

$type_labels = [
    'general'     => 'Genel Teklif Talebi',
    'quick_quote' => 'Hızlı Teklif',
    'quote_list'  => 'Teklif Listesi',
];

$company_name = '';
if ( class_exists( 'GH_Company_Settings' ) ) {
    $company_name = GH_Company_Settings::get( 'company_name' );
}
if ( empty( $company_name ) ) {
    $company_name = get_bloginfo( 'name' );
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

<tr><td style="background:#2E5090;padding:24px 32px;">
    <h1 style="margin:0;color:#ffffff;font-size:20px;">Yeni Teklif Talebi</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:13px;"><?php echo esc_html( $company_name ); ?></p>
</td></tr>

<tr><td style="padding:24px 32px;">

<table width="100%" cellpadding="6" cellspacing="0" style="margin-bottom:20px;">
    <tr>
        <td style="font-weight:bold;color:#666;width:140px;">Referans:</td>
        <td style="font-weight:bold;color:#2E5090;font-size:16px;font-family:monospace;"><?php echo esc_html( $rfq['reference'] ?? '' ); ?></td>
    </tr>
    <tr>
        <td style="font-weight:bold;color:#666;">Tür:</td>
        <td><?php echo esc_html( $type_labels[ $rfq['type'] ?? '' ] ?? $rfq['type'] ?? '' ); ?></td>
    </tr>
    <tr>
        <td style="font-weight:bold;color:#666;">Firma:</td>
        <td><?php echo esc_html( $rfq['company'] ?? '' ); ?></td>
    </tr>
    <tr>
        <td style="font-weight:bold;color:#666;">Yetkili:</td>
        <td><?php echo esc_html( $rfq['contact_name'] ?? '' ); ?></td>
    </tr>
    <tr>
        <td style="font-weight:bold;color:#666;">Telefon:</td>
        <td><?php echo esc_html( $rfq['phone'] ?? '' ); ?></td>
    </tr>
    <tr>
        <td style="font-weight:bold;color:#666;">E-posta:</td>
        <td><a href="mailto:<?php echo esc_attr( $rfq['email'] ?? '' ); ?>"><?php echo esc_html( $rfq['email'] ?? '' ); ?></a></td>
    </tr>
    <?php if ( ! empty( $rfq['sector'] ) ) : ?>
    <tr>
        <td style="font-weight:bold;color:#666;">Sektör:</td>
        <td><?php echo esc_html( $rfq['sector'] ); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ( ! empty( $rfq['subject'] ) ) : ?>
    <tr>
        <td style="font-weight:bold;color:#666;">Konu:</td>
        <td><?php echo esc_html( $rfq['subject'] ); ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td style="font-weight:bold;color:#666;">Tarih:</td>
        <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $rfq['created_at'] ?? 'now' ) ) ); ?></td>
    </tr>
</table>

<?php if ( ! empty( $rfq['message'] ) ) : ?>
<div style="background:#f8f9fa;border-left:3px solid #2E5090;padding:14px 18px;margin-bottom:20px;border-radius:4px;">
    <strong style="display:block;margin-bottom:6px;color:#666;">Mesaj:</strong>
    <?php echo nl2br( esc_html( $rfq['message'] ) ); ?>
</div>
<?php endif; ?>

<?php if ( ! empty( $items ) ) : ?>
<h3 style="margin:20px 0 10px;color:#2E5090;border-bottom:2px solid #2E5090;padding-bottom:8px;">Talep Edilen Ürünler</h3>
<table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #dee2e6;border-radius:4px;">
    <thead>
        <tr style="background:#f0f2f5;">
            <th style="text-align:left;border-bottom:1px solid #dee2e6;font-size:12px;text-transform:uppercase;color:#666;">#</th>
            <th style="text-align:left;border-bottom:1px solid #dee2e6;font-size:12px;text-transform:uppercase;color:#666;">Ürün</th>
            <th style="text-align:left;border-bottom:1px solid #dee2e6;font-size:12px;text-transform:uppercase;color:#666;">SKU</th>
            <th style="text-align:center;border-bottom:1px solid #dee2e6;font-size:12px;text-transform:uppercase;color:#666;">Miktar</th>
            <th style="text-align:left;border-bottom:1px solid #dee2e6;font-size:12px;text-transform:uppercase;color:#666;">Birim</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $items as $i => $item ) : ?>
        <tr style="border-bottom:1px solid #f0f2f5;">
            <td style="color:#999;"><?php echo (int) ( $i + 1 ); ?></td>
            <td>
                <strong><?php echo esc_html( $item['snapshot_product_name'] ?? '' ); ?></strong>
                <?php if ( ! empty( $item['snapshot_variation'] ) ) : ?>
                <br><span style="color:#666;font-size:13px;"><?php echo esc_html( $item['snapshot_variation'] ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $item['snapshot_verified_brand'] ) ) : ?>
                <br><span style="color:#2E5090;font-size:12px;"><?php echo esc_html( $item['snapshot_verified_brand'] ); ?></span>
                <?php endif; ?>
            </td>
            <td style="font-family:monospace;font-size:13px;"><?php echo esc_html( $item['snapshot_sku'] ?? '' ); ?></td>
            <td style="text-align:center;font-weight:bold;"><?php echo esc_html( $item['quantity'] ?? 1 ); ?></td>
            <td><?php echo esc_html( $item['snapshot_sales_unit_label'] ?? $item['sales_unit'] ?? '' ); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div style="margin-top:24px;text-align:center;">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=guvenhijyen-rfq&action=view&ref=' . urlencode( $rfq['reference'] ?? '' ) ) ); ?>"
       style="display:inline-block;background:#2E5090;color:#ffffff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">
        Teklif Talebini Görüntüle
    </a>
</div>

</td></tr>

<tr><td style="background:#f8f9fa;padding:16px 32px;text-align:center;font-size:12px;color:#999;">
    <p style="margin:0;"><?php echo esc_html( $company_name ); ?> &mdash; Teklif Yönetim Sistemi</p>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
