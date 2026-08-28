<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * @var array $rfq  RFQ data
 * @var array $items RFQ items
 */

$company_name = '';
$phone        = '';
$email        = '';
if ( class_exists( 'GH_Company_Settings' ) ) {
    $company_name = GH_Company_Settings::get( 'company_name' );
    $phone        = GH_Company_Settings::get( 'phone' );
    $email        = GH_Company_Settings::get( 'email' );
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
    <h1 style="margin:0;color:#ffffff;font-size:20px;">Teklif Talebiniz Alındı</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:13px;"><?php echo esc_html( $company_name ); ?></p>
</td></tr>

<tr><td style="padding:24px 32px;">

<p>Sayın <?php echo esc_html( $rfq['contact_name'] ?? '' ); ?>,</p>

<p>Teklif talebiniz başarıyla alınmıştır. En kısa sürede sizinle iletişime geçeceğiz.</p>

<div style="background:#e8eef7;border-radius:8px;padding:20px;text-align:center;margin:20px 0;">
    <div style="font-size:13px;color:#666;margin-bottom:4px;">Referans Numaranız</div>
    <div style="font-size:28px;font-weight:bold;color:#2E5090;font-family:monospace;">
        <?php echo esc_html( $rfq['reference'] ?? '' ); ?>
    </div>
</div>

<?php if ( ! empty( $items ) ) : ?>
<h3 style="color:#2E5090;border-bottom:2px solid #2E5090;padding-bottom:8px;">Talep Ettiğiniz Ürünler</h3>
<table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #dee2e6;">
    <thead>
        <tr style="background:#f0f2f5;">
            <th style="text-align:left;border-bottom:1px solid #dee2e6;font-size:12px;">Ürün</th>
            <th style="text-align:center;border-bottom:1px solid #dee2e6;font-size:12px;">Miktar</th>
            <th style="text-align:left;border-bottom:1px solid #dee2e6;font-size:12px;">Birim</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $items as $item ) : ?>
        <tr>
            <td>
                <?php echo esc_html( $item['snapshot_product_name'] ?? '' ); ?>
                <?php if ( ! empty( $item['snapshot_variation'] ) ) : ?>
                <br><span style="color:#666;font-size:13px;"><?php echo esc_html( $item['snapshot_variation'] ); ?></span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;font-weight:bold;"><?php echo esc_html( $item['quantity'] ?? 1 ); ?></td>
            <td><?php echo esc_html( $item['snapshot_sales_unit_label'] ?? '' ); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ( ! empty( $rfq['message'] ) ) : ?>
<div style="background:#f8f9fa;border-left:3px solid #2E5090;padding:14px 18px;margin:20px 0;border-radius:4px;">
    <strong style="display:block;margin-bottom:6px;color:#666;">Mesajınız:</strong>
    <?php echo nl2br( esc_html( $rfq['message'] ) ); ?>
</div>
<?php endif; ?>

<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">

<p style="color:#666;font-size:13px;">
    Herhangi bir sorunuz varsa bizimle iletişime geçebilirsiniz:
</p>

<?php if ( ! empty( $phone ) ) : ?>
<p style="margin:4px 0;">
    <strong>Telefon:</strong> <?php echo esc_html( $phone ); ?>
</p>
<?php endif; ?>

<?php if ( ! empty( $email ) ) : ?>
<p style="margin:4px 0;">
    <strong>E-posta:</strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
</p>
<?php endif; ?>

</td></tr>

<tr><td style="background:#f8f9fa;padding:16px 32px;text-align:center;font-size:12px;color:#999;">
    <p style="margin:0;"><?php echo esc_html( $company_name ); ?></p>
    <p style="margin:4px 0 0;">Bu e-posta teklif talebiniz üzerine otomatik olarak gönderilmiştir.</p>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
