<?php
/**
 * Sistema di Notifiche Email per Alert Critici
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Email_Notifications {
    
    private static $last_alert_sent = null;
    private static $alert_cooldown = 3600; // 1 ora di cooldown tra alert simili
    
    public static function init() {
        // Hook per controlli periodici
        add_action('wsd_daily_health_check', array(__CLASS__, 'daily_health_check'));
        add_action('wsd_critical_alert_check', array(__CLASS__, 'check_critical_issues'));
        
        // Schedula eventi se non esistono
        if (!wp_next_scheduled('wsd_daily_health_check')) {
            wp_schedule_event(time(), 'daily', 'wsd_daily_health_check');
        }
        
        // Controllo critico ogni 15 minuti
        if (!wp_next_scheduled('wsd_critical_alert_check')) {
            wp_schedule_event(time(), 'wsd_15min', 'wsd_critical_alert_check');
        }
        
        // Registra intervalli personalizzati
        add_filter('cron_schedules', array(__CLASS__, 'add_custom_cron_intervals'));
        
        // Admin settings
        add_action('admin_init', array(__CLASS__, 'register_email_settings'));
    }
    
    public static function add_custom_cron_intervals($schedules) {
        $schedules['wsd_15min'] = array(
            'interval' => 900, // 15 minuti
            'display' => __('Ogni 15 minuti (WSD)', 'wc-speed-doctor')
        );
        
        $schedules['wsd_6hours'] = array(
            'interval' => 21600, // 6 ore
            'display' => __('Ogni 6 ore (WSD)', 'wc-speed-doctor')
        );
        
        return $schedules;
    }
    
    public static function register_email_settings() {
        register_setting('wsd_email_settings', 'wsd_email_notifications', array(
            'type' => 'array',
            'default' => array(
                'enabled' => true,
                'critical_alerts' => true,
                'daily_reports' => false,
                'recipient_email' => get_option('admin_email'),
                'alert_threshold' => 'critical', // critical, warning, all
                'cooldown_hours' => 1
            )
        ));
    }
    
    public static function daily_health_check() {
        $settings = get_option('wsd_email_notifications', array());
        
        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        if (!isset($settings['daily_reports']) || !$settings['daily_reports']) {
            return;
        }
        
        $health = WSD_Auto_Repair::get_system_health();
        $recent_issues = WSD_Logger::get_recent_issues(24);
        
        $report = self::generate_daily_report($health, $recent_issues);
        
        $recipient = $settings['recipient_email'] ?? get_option('admin_email');
        $subject = sprintf('[%s] WSD Daily Performance Report', get_bloginfo('name'));
        
        self::send_email($recipient, $subject, $report['html'], $report['text']);
    }
    
    public static function check_critical_issues() {
        $settings = get_option('wsd_email_notifications', array());
        
        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        if (!isset($settings['critical_alerts']) || !$settings['critical_alerts']) {
            return;
        }
        
        $health = WSD_Auto_Repair::get_system_health();
        $critical_issues = array();
        $warning_issues = array();
        
        foreach ($health as $component => $status) {
            if ($status['status'] === 'critical') {
                $critical_issues[] = array(
                    'component' => $component,
                    'message' => $status['message'],
                    'issues' => $status['issues'] ?? array()
                );
            } elseif ($status['status'] === 'warning') {
                $warning_issues[] = array(
                    'component' => $component,
                    'message' => $status['message'],
                    'issues' => $status['issues'] ?? array()
                );
            }
        }
        
        // Controlla anche performance recenti
        $recent_critical = WSD_Logger::get_recent_issues(1); // ultima ora
        $severe_performance = array_filter($recent_critical, function($issue) {
            return isset($issue['severity']) && $issue['severity'] === 'critical';
        });
        
        if (!empty($severe_performance)) {
            $critical_issues[] = array(
                'component' => 'performance',
                'message' => count($severe_performance) . ' problemi di performance critici nell\'ultima ora',
                'issues' => array_slice($severe_performance, 0, 3) // Primi 3
            );
        }
        
        // Determina se inviare alert
        $threshold = $settings['alert_threshold'] ?? 'critical';
        $should_alert = false;
        
        if ($threshold === 'critical' && !empty($critical_issues)) {
            $should_alert = true;
        } elseif ($threshold === 'warning' && (!empty($critical_issues) || !empty($warning_issues))) {
            $should_alert = true;
        } elseif ($threshold === 'all' && (!empty($critical_issues) || !empty($warning_issues))) {
            $should_alert = true;
        }
        
        if ($should_alert && self::should_send_alert($critical_issues, $warning_issues)) {
            self::send_critical_alert($critical_issues, $warning_issues);
        }
    }
    
    private static function should_send_alert($critical_issues, $warning_issues) {
        $settings = get_option('wsd_email_notifications', array());
        $cooldown_hours = $settings['cooldown_hours'] ?? 1;
        $cooldown_seconds = $cooldown_hours * 3600;
        
        $last_alert = get_option('wsd_last_email_alert', 0);
        $current_time = time();
        
        // Genera hash delle issue per evitare spam per gli stessi problemi
        $issues_hash = md5(serialize($critical_issues) . serialize($warning_issues));
        $last_hash = get_option('wsd_last_alert_hash', '');
        
        // Invia sempre se sono problemi nuovi o se è passato abbastanza tempo
        if ($issues_hash !== $last_hash || ($current_time - $last_alert) > $cooldown_seconds) {
            update_option('wsd_last_email_alert', $current_time);
            update_option('wsd_last_alert_hash', $issues_hash);
            return true;
        }
        
        return false;
    }
    
    private static function send_critical_alert($critical_issues, $warning_issues) {
        $settings = get_option('wsd_email_notifications', array());
        $recipient = $settings['recipient_email'] ?? get_option('admin_email');
        
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $dashboard_url = admin_url('admin.php?page=wsd-speed-doctor-main');
        
        $critical_count = count($critical_issues);
        $warning_count = count($warning_issues);
        
        if ($critical_count > 0) {
            $subject = sprintf('[URGENT] %s - %d Critical Performance Issue%s Detected', 
                $site_name, $critical_count, $critical_count > 1 ? 's' : '');
        } else {
            $subject = sprintf('[WARNING] %s - Performance Issues Detected', $site_name);
        }
        
        // Email HTML
        $html_content = self::generate_alert_email_html($site_name, $site_url, $dashboard_url, $critical_issues, $warning_issues);
        
        // Email text
        $text_content = self::generate_alert_email_text($site_name, $site_url, $dashboard_url, $critical_issues, $warning_issues);
        
        self::send_email($recipient, $subject, $html_content, $text_content);
        
        // Log l'invio
        error_log(sprintf('[WSD] Alert email sent to %s - Critical: %d, Warning: %d', 
            $recipient, $critical_count, $warning_count));
    }
    
    private static function generate_alert_email_html($site_name, $site_url, $dashboard_url, $critical_issues, $warning_issues) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>WSD Performance Alert</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 20px; }
                .footer { background: #e9ecef; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; }
                .critical { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 4px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 4px; }
                .button { display: inline-block; background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 15px 0; }
                ul { margin: 10px 0; padding-left: 20px; }
                li { margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚨 WSD Performance Alert</h1>
                    <p>Sito: <strong><?php echo esc_html($site_name); ?></strong></p>
                    <p>Timestamp: <strong><?php echo current_time('d/m/Y H:i:s'); ?></strong></p>
                </div>
                
                <div class="content">
                    <?php if (!empty($critical_issues)): ?>
                        <div class="critical">
                            <h2>🚨 Problemi Critici (<?php echo count($critical_issues); ?>)</h2>
                            <p>I seguenti problemi critici richiedono intervento immediato:</p>
                            <?php foreach ($critical_issues as $issue): ?>
                                <h3><?php echo esc_html(ucfirst(str_replace('_', ' ', $issue['component']))); ?></h3>
                                <p><strong><?php echo esc_html($issue['message']); ?></strong></p>
                                <?php if (!empty($issue['issues'])): ?>
                                    <ul>
                                        <?php foreach ($issue['issues'] as $detail): ?>
                                            <li><?php echo esc_html($detail); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($warning_issues)): ?>
                        <div class="warning">
                            <h2>⚠️ Warning Issues (<?php echo count($warning_issues); ?>)</h2>
                            <p>I seguenti problemi dovrebbero essere risolti presto:</p>
                            <?php foreach ($warning_issues as $issue): ?>
                                <h3><?php echo esc_html(ucfirst(str_replace('_', ' ', $issue['component']))); ?></h3>
                                <p><?php echo esc_html($issue['message']); ?></p>
                                <?php if (!empty($issue['issues']) && count($issue['issues']) <= 3): ?>
                                    <ul>
                                        <?php foreach ($issue['issues'] as $detail): ?>
                                            <li><?php echo esc_html($detail); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3>🔧 Azioni Raccomandate:</h3>
                    <ul>
                        <li>Accedi alla <a href="<?php echo esc_url($dashboard_url); ?>">Dashboard WSD</a> per dettagli completi</li>
                        <li>Usa il sistema di auto-riparazione per risolvere problemi automatici</li>
                        <li>Controlla i log delle performance per pattern ricorrenti</li>
                        <li>Considera ottimizzazioni hosting se i problemi persistono</li>
                    </ul>
                    
                    <center>
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="button">🚀 Vai alla Dashboard WSD</a>
                    </center>
                </div>
                
                <div class="footer">
                    <p>Questo alert è stato generato automaticamente da <strong>WSD Speed Doctor Pro</strong></p>
                    <p>Sito: <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
                    <p><small>Per disabilitare questi alert, vai in WSD Settings > Email Notifications</small></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private static function generate_alert_email_text($site_name, $site_url, $dashboard_url, $critical_issues, $warning_issues) {
        $content = "WSD PERFORMANCE ALERT\n";
        $content .= "==========================================\n\n";
        $content .= "Sito: " . $site_name . "\n";
        $content .= "URL: " . $site_url . "\n";
        $content .= "Timestamp: " . current_time('d/m/Y H:i:s') . "\n\n";
        
        if (!empty($critical_issues)) {
            $content .= "🚨 PROBLEMI CRITICI (" . count($critical_issues) . ")\n";
            $content .= "====================================\n";
            foreach ($critical_issues as $issue) {
                $content .= "\n" . strtoupper(str_replace('_', ' ', $issue['component'])) . ":\n";
                $content .= "- " . $issue['message'] . "\n";
                if (!empty($issue['issues'])) {
                    foreach ($issue['issues'] as $detail) {
                        $content .= "  * " . $detail . "\n";
                    }
                }
            }
            $content .= "\n";
        }
        
        if (!empty($warning_issues)) {
            $content .= "⚠️  WARNING ISSUES (" . count($warning_issues) . ")\n";
            $content .= "====================================\n";
            foreach ($warning_issues as $issue) {
                $content .= "\n" . strtoupper(str_replace('_', ' ', $issue['component'])) . ":\n";
                $content .= "- " . $issue['message'] . "\n";
            }
            $content .= "\n";
        }
        
        $content .= "🔧 AZIONI RACCOMANDATE:\n";
        $content .= "========================\n";
        $content .= "1. Accedi alla Dashboard WSD: " . $dashboard_url . "\n";
        $content .= "2. Usa il sistema di auto-riparazione\n";
        $content .= "3. Controlla i log delle performance\n";
        $content .= "4. Considera ottimizzazioni hosting\n\n";
        
        $content .= "---\n";
        $content .= "Questo alert è stato generato da WSD Speed Doctor Pro\n";
        $content .= "Per disabilitare: WSD Settings > Email Notifications\n";
        
        return $content;
    }
    
    private static function generate_daily_report($health, $recent_issues) {
        // Implementazione del report giornaliero
        // (Simile alla funzione alert ma con formato report completo)
        
        $html = "<!-- Daily Report HTML -->";
        $text = "Daily Performance Report\n========================\n";
        
        return array('html' => $html, 'text' => $text);
    }
    
    private static function send_email($to, $subject, $html_content, $text_content) {
        $headers = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: WSD Speed Doctor <' . get_option('admin_email') . '>';
        $headers[] = 'Reply-To: ' . get_option('admin_email');
        
        // Prova prima con HTML
        $sent = wp_mail($to, $subject, $html_content, $headers);
        
        // Se fallisce, prova con testo semplice
        if (!$sent) {
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            $sent = wp_mail($to, $subject, $text_content, $headers);
        }
        
        return $sent;
    }
    
    public static function test_email_notification() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $settings = get_option('wsd_email_notifications', array());
        $recipient = $settings['recipient_email'] ?? get_option('admin_email');
        
        $subject = '[TEST] WSD Email Notifications - ' . get_bloginfo('name');
        $message = "Questo è un test delle notifiche email WSD.\n\n";
        $message .= "Se ricevi questa email, le notifiche sono configurate correttamente.\n\n";
        $message .= "Timestamp: " . current_time('d/m/Y H:i:s') . "\n";
        $message .= "Sito: " . get_bloginfo('name') . "\n";
        $message .= "URL: " . get_bloginfo('url') . "\n";
        
        $sent = wp_mail($recipient, $subject, $message);
        
        if ($sent) {
            wp_send_json_success('Email di test inviata con successo a: ' . $recipient);
        } else {
            wp_send_json_error('Errore nell\'invio dell\'email di test');
        }
    }
    
    public static function render_email_settings() {
        $settings = get_option('wsd_email_notifications', array());
        
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-email-alt"></span> Impostazioni Email Notifications</h2>';
        
        echo '<form method="post" action="options.php">';
        settings_fields('wsd_email_settings');
        
        echo '<table class="form-table">';
        
        // Abilita notifiche
        echo '<tr>';
        echo '<th scope="row">Abilita Notifiche Email</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="wsd_email_notifications[enabled]" value="1" ' . 
             checked($settings['enabled'] ?? true, true, false) . '> Abilita notifiche email automatiche</label>';
        echo '</td>';
        echo '</tr>';
        
        // Email destinatario
        echo '<tr>';
        echo '<th scope="row">Email Destinatario</th>';
        echo '<td>';
        echo '<input type="email" name="wsd_email_notifications[recipient_email]" value="' . 
             esc_attr($settings['recipient_email'] ?? get_option('admin_email')) . '" class="regular-text">';
        echo '<p class="description">Email a cui inviare gli alert (default: admin email)</p>';
        echo '</td>';
        echo '</tr>';
        
        // Alert critici
        echo '<tr>';
        echo '<th scope="row">Alert Critici</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="wsd_email_notifications[critical_alerts]" value="1" ' . 
             checked($settings['critical_alerts'] ?? true, true, false) . '> Invia alert per problemi critici</label>';
        echo '</td>';
        echo '</tr>';
        
        // Report giornalieri
        echo '<tr>';
        echo '<th scope="row">Report Giornalieri</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="wsd_email_notifications[daily_reports]" value="1" ' . 
             checked($settings['daily_reports'] ?? false, true, false) . '> Invia report performance giornaliero</label>';
        echo '</td>';
        echo '</tr>';
        
        // Soglia alert
        echo '<tr>';
        echo '<th scope="row">Soglia Alert</th>';
        echo '<td>';
        echo '<select name="wsd_email_notifications[alert_threshold]">';
        $thresholds = array('critical' => 'Solo Critici', 'warning' => 'Critici + Warning', 'all' => 'Tutti');
        foreach ($thresholds as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($settings['alert_threshold'] ?? 'critical', $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        // Cooldown
        echo '<tr>';
        echo '<th scope="row">Cooldown Alert (ore)</th>';
        echo '<td>';
        echo '<input type="number" name="wsd_email_notifications[cooldown_hours]" value="' . 
             esc_attr($settings['cooldown_hours'] ?? 1) . '" min="0.5" max="24" step="0.5" class="small-text">';
        echo '<p class="description">Tempo minimo tra alert simili</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        submit_button('Salva Impostazioni Email');
        echo '</form>';
        
        // Test button
        echo '<div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border: 1px solid #007cba; border-radius: 4px;">';
        echo '<h4>Test Email</h4>';
        echo '<p>Invia un\'email di test per verificare la configurazione:</p>';
        echo '<button id="wsd-test-email" class="button button-secondary">📧 Invia Email Test</button>';
        echo '<div id="wsd-test-email-result" style="margin-top: 10px;"></div>';
        echo '</div>';
        
        echo '</div>';
    }
}