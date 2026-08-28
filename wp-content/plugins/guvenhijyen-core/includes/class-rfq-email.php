<?php

defined('ABSPATH') || exit;

class GH_RFQ_Email {

    public static function init(): void {
        add_action('gh_rfq_created', [__CLASS__, 'on_rfq_created'], 10, 3);
    }

    public static function on_rfq_created(string $reference, int $rfq_id, array $data): void {
        $rfq = GH_RFQ_Domain::get($reference);
        if (!$rfq) {
            self::log($reference, 'error', 'RFQ not found for email dispatch.');
            return;
        }

        self::send_admin_notification($rfq);
        self::send_customer_confirmation($rfq);
    }

    public static function send_admin_notification(array $rfq): void {
        $admin_email = GH_Company_Settings::get('email');
        if (empty($admin_email)) {
            $admin_email = get_option('admin_email');
        }

        if (empty($admin_email)) {
            self::log($rfq['reference'], 'error', 'No admin email configured.');
            return;
        }

        $from_name  = GH_Company_Settings::get('company_name') ?: get_bloginfo('name');
        $from_email = $admin_email;

        $subject = sprintf(
            '[%s] %s - %s',
            esc_html($from_name),
            __('Yeni Teklif Talebi', 'guvenhijyen'),
            esc_html($rfq['reference'])
        );

        $body = self::build_admin_email_body($rfq);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
            sprintf('Reply-To: %s <%s>', esc_html($rfq['contact_name']), $rfq['email']),
        ];

        $sent = wp_mail($admin_email, $subject, $body, $headers);

        self::log(
            $rfq['reference'],
            $sent ? 'success' : 'error',
            $sent ? 'Admin notification sent.' : 'Admin notification failed.'
        );
    }

    public static function send_customer_confirmation(array $rfq): void {
        $customer_email = $rfq['email'] ?? '';
        if (empty($customer_email) || !is_email($customer_email)) {
            self::log($rfq['reference'], 'error', 'Invalid customer email.');
            return;
        }

        $from_name  = GH_Company_Settings::get('company_name') ?: get_bloginfo('name');
        $from_email = GH_Company_Settings::get('email') ?: get_option('admin_email');

        $subject = sprintf(
            '%s - %s %s',
            esc_html($from_name),
            __('Teklif Talebiniz Alindi', 'guvenhijyen'),
            esc_html($rfq['reference'])
        );

        $body = self::build_customer_email_body($rfq);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        ];

        $sent = wp_mail($customer_email, $subject, $body, $headers);

        self::log(
            $rfq['reference'],
            $sent ? 'success' : 'error',
            $sent ? 'Customer confirmation sent.' : 'Customer confirmation failed.'
        );
    }

    private static function build_admin_email_body(array $rfq): string {
        $company_name = esc_html(GH_Company_Settings::get('company_name') ?: get_bloginfo('name'));
        $type_label   = esc_html(GH_RFQ_Domain::get_type_label($rfq['type']));
        $reference    = esc_html($rfq['reference']);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:20px 0;">
        <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:4px;overflow:hidden;">

        <tr><td style="background-color:#0d47a1;padding:20px 30px;">
            <h1 style="color:#ffffff;margin:0;font-size:18px;"><?php echo $company_name; ?> - <?php esc_html_e('Yeni Teklif Talebi', 'guvenhijyen'); ?></h1>
        </td></tr>

        <tr><td style="padding:30px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                <td style="padding:8px 0;font-weight:bold;width:140px;vertical-align:top;"><?php esc_html_e('Referans:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;font-weight:bold;color:#0d47a1;"><?php echo $reference; ?></td>
            </tr>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Talep Tipi:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo $type_label; ?></td>
            </tr>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Firma:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo esc_html($rfq['company']); ?></td>
            </tr>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Yetkili:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo esc_html($rfq['contact_name']); ?></td>
            </tr>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Telefon:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo esc_html($rfq['phone']); ?></td>
            </tr>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('E-posta:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo esc_html($rfq['email']); ?></td>
            </tr>
            <?php if (!empty($rfq['sector'])) : ?>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Sektor:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo esc_html($rfq['sector']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($rfq['subject'])) : ?>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Konu:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo esc_html($rfq['subject']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($rfq['message'])) : ?>
            <tr>
                <td style="padding:8px 0;font-weight:bold;vertical-align:top;"><?php esc_html_e('Mesaj:', 'guvenhijyen'); ?></td>
                <td style="padding:8px 0;"><?php echo nl2br(esc_html($rfq['message'])); ?></td>
            </tr>
            <?php endif; ?>
            </table>

            <?php if (!empty($rfq['items'])) : ?>
            <h3 style="margin:20px 0 10px;font-size:14px;border-bottom:1px solid #e0e0e0;padding-bottom:8px;">
                <?php esc_html_e('Talep Edilen Urunler', 'guvenhijyen'); ?>
            </h3>
            <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e0e0e0;border-collapse:collapse;font-size:13px;">
            <thead>
            <tr style="background-color:#f5f5f5;">
                <th style="border:1px solid #e0e0e0;text-align:left;"><?php esc_html_e('Urun', 'guvenhijyen'); ?></th>
                <th style="border:1px solid #e0e0e0;text-align:left;"><?php esc_html_e('SKU', 'guvenhijyen'); ?></th>
                <th style="border:1px solid #e0e0e0;text-align:left;"><?php esc_html_e('Varyant', 'guvenhijyen'); ?></th>
                <th style="border:1px solid #e0e0e0;text-align:center;"><?php esc_html_e('Miktar', 'guvenhijyen'); ?></th>
                <th style="border:1px solid #e0e0e0;text-align:left;"><?php esc_html_e('Birim', 'guvenhijyen'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rfq['items'] as $item) :
                $snapshot = $item['snapshot'] ?? [];
            ?>
            <tr>
                <td style="border:1px solid #e0e0e0;"><?php echo esc_html($snapshot['product_name'] ?? ''); ?></td>
                <td style="border:1px solid #e0e0e0;"><?php echo esc_html($snapshot['sku'] ?? ''); ?></td>
                <td style="border:1px solid #e0e0e0;"><?php echo esc_html($snapshot['variation'] ?? '-'); ?></td>
                <td style="border:1px solid #e0e0e0;text-align:center;"><?php echo esc_html($item['quantity']); ?></td>
                <td style="border:1px solid #e0e0e0;"><?php echo esc_html($snapshot['sales_unit_label'] ?? $item['sales_unit']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            <?php endif; ?>

            <p style="margin-top:20px;font-size:12px;color:#888;">
                <?php echo esc_html(sprintf(__('IP: %s', 'guvenhijyen'), $rfq['ip_address'])); ?>
                &nbsp;|&nbsp;
                <?php echo esc_html(sprintf(__('Tarih: %s', 'guvenhijyen'), $rfq['created_at'])); ?>
            </p>
        </td></tr>

        <tr><td style="background-color:#f5f5f5;padding:15px 30px;text-align:center;font-size:12px;color:#888;">
            <?php echo esc_html($company_name); ?>
        </td></tr>

        </table>
        </td></tr>
        </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private static function build_customer_email_body(array $rfq): string {
        $company_name  = esc_html(GH_Company_Settings::get('company_name') ?: get_bloginfo('name'));
        $company_phone = esc_html(GH_Company_Settings::get('phone'));
        $company_email = esc_html(GH_Company_Settings::get('email') ?: get_option('admin_email'));
        $reference     = esc_html($rfq['reference']);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:20px 0;">
        <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:4px;overflow:hidden;">

        <tr><td style="background-color:#0d47a1;padding:20px 30px;">
            <h1 style="color:#ffffff;margin:0;font-size:18px;"><?php echo $company_name; ?></h1>
        </td></tr>

        <tr><td style="padding:30px;">
            <p style="margin:0 0 15px;font-size:15px;">
                <?php echo esc_html(sprintf(__('Sayin %s,', 'guvenhijyen'), $rfq['contact_name'])); ?>
            </p>
            <p style="margin:0 0 15px;">
                <?php esc_html_e('Teklif talebiniz basariyla alindi. En kisa surede sizinle iletisime gececegiz.', 'guvenhijyen'); ?>
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;border-radius:4px;padding:15px;margin:15px 0;">
            <tr>
                <td style="padding:5px 10px;font-weight:bold;"><?php esc_html_e('Referans Numaraniz:', 'guvenhijyen'); ?></td>
                <td style="padding:5px 10px;font-weight:bold;color:#0d47a1;font-size:16px;"><?php echo $reference; ?></td>
            </tr>
            <tr>
                <td style="padding:5px 10px;font-weight:bold;"><?php esc_html_e('Firma:', 'guvenhijyen'); ?></td>
                <td style="padding:5px 10px;"><?php echo esc_html($rfq['company']); ?></td>
            </tr>
            </table>

            <?php if (!empty($rfq['items'])) : ?>
            <h3 style="margin:20px 0 10px;font-size:14px;"><?php esc_html_e('Talep Edilen Urunler', 'guvenhijyen'); ?></h3>
            <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #e0e0e0;border-collapse:collapse;font-size:13px;">
            <thead>
            <tr style="background-color:#f5f5f5;">
                <th style="border:1px solid #e0e0e0;text-align:left;"><?php esc_html_e('Urun', 'guvenhijyen'); ?></th>
                <th style="border:1px solid #e0e0e0;text-align:center;"><?php esc_html_e('Miktar', 'guvenhijyen'); ?></th>
                <th style="border:1px solid #e0e0e0;text-align:left;"><?php esc_html_e('Birim', 'guvenhijyen'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rfq['items'] as $item) :
                $snapshot = $item['snapshot'] ?? [];
            ?>
            <tr>
                <td style="border:1px solid #e0e0e0;">
                    <?php echo esc_html($snapshot['product_name'] ?? ''); ?>
                    <?php if (!empty($snapshot['variation'])) : ?>
                        <br><small style="color:#666;"><?php echo esc_html($snapshot['variation']); ?></small>
                    <?php endif; ?>
                </td>
                <td style="border:1px solid #e0e0e0;text-align:center;"><?php echo esc_html($item['quantity']); ?></td>
                <td style="border:1px solid #e0e0e0;"><?php echo esc_html($snapshot['sales_unit_label'] ?? $item['sales_unit']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            <?php endif; ?>

            <p style="margin:20px 0 10px;"><?php esc_html_e('Herhangi bir sorunuz icin bizimle iletisime gecebilirsiniz:', 'guvenhijyen'); ?></p>
            <p style="margin:0;font-size:13px;">
                <?php if ($company_phone) : ?>
                    <?php esc_html_e('Telefon:', 'guvenhijyen'); ?> <?php echo $company_phone; ?><br>
                <?php endif; ?>
                <?php esc_html_e('E-posta:', 'guvenhijyen'); ?> <?php echo $company_email; ?>
            </p>
        </td></tr>

        <tr><td style="background-color:#f5f5f5;padding:15px 30px;text-align:center;font-size:12px;color:#888;">
            <?php echo esc_html($company_name); ?>
        </td></tr>

        </table>
        </td></tr>
        </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private static function log(string $reference, string $level, string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log(sprintf(
            '[GH_RFQ_Email] [%s] [%s] %s',
            $reference,
            $level,
            $message
        ));
    }
}
