<?php
/**
 * Classe per la diagnostica completa dell'ambiente
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Diagnostics {
    
    private static $page_load_time = null;
    
    public static function check_dependencies() {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>WSD:</strong> La tua versione PHP (' . PHP_VERSION . ') è obsoleta. Aggiorna a PHP 7.4+ per migliori performance.</p>';
                echo '</div>';
            });
        }
        
        $memory_limit = ini_get('memory_limit');
        if (intval($memory_limit) < 256) {
            add_action('admin_notices', function() use ($memory_limit) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>WSD:</strong> Memory limit PHP troppo basso (' . $memory_limit . '). Raccomandato: 256MB+</p>';
                echo '</div>';
            });
        }
    }
    
    public static function set_page_load_time($time) {
        self::$page_load_time = $time;
    }
    
    public static function get_environment_info() {
        global $wpdb;
        
        return array(
            'php_version' => array(
                'label' => __('Versione PHP', 'wc-speed-doctor'),
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4', '<') ? 'warning' : 'ok',
                'recommendation' => version_compare(PHP_VERSION, '7.4', '<') ? 
                    __('Aggiorna a PHP 7.4+ per migliori performance e sicurezza.', 'wc-speed-doctor') : null
            ),
            'php_memory_limit' => array(
                'label' => __('Memory Limit PHP', 'wc-speed-doctor'),
                'value' => ini_get('memory_limit'),
                'status' => (intval(ini_get('memory_limit')) < 256) ? 'warning' : 'ok',
                'recommendation' => (intval(ini_get('memory_limit')) < 256) ? 
                    __('Raccomandato 256MB+ per WooCommerce.', 'wc-speed-doctor') : null
            ),
            'php_max_execution_time' => array(
                'label' => __('Max Execution Time', 'wc-speed-doctor'),
                'value' => ini_get('max_execution_time') . 's',
                'status' => (intval(ini_get('max_execution_time')) < 120) ? 'warning' : 'ok',
                'recommendation' => (intval(ini_get('max_execution_time')) < 120) ? 
                    __('Considera 120-300s per operazioni complesse.', 'wc-speed-doctor') : null
            ),
            'mysql_version' => array(
                'label' => __('Versione MySQL', 'wc-speed-doctor'),
                'value' => $wpdb->db_version(),
                'status' => version_compare($wpdb->db_version(), '5.6', '<') ? 'warning' : 'ok',
                'recommendation' => version_compare($wpdb->db_version(), '5.6', '<') ? 
                    __('MySQL 5.6+ raccomandato per performance ottimali.', 'wc-speed-doctor') : null
            ),
            'server_software' => array(
                'label' => __('Software Server', 'wc-speed-doctor'),
                'value' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'status' => 'ok',
                'recommendation' => null
            ),
            'wp_cron_status' => array(
                'label' => __('WP-Cron Status', 'wc-speed-doctor'),
                'value' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'Disabilitato (Ottimo!)' : 'Attivo (Problematico)',
                'status' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'ok' : 'warning',
                'recommendation' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 
                    __('Disabilita WP-Cron interno e usa cron server per migliori performance.', 'wc-speed-doctor') : null
            ),
            'opcache_status' => array(
                'label' => __('OPcache Status', 'wc-speed-doctor'),
                'value' => function_exists('opcache_get_status') && opcache_get_status() ? 'Attivo' : 'Non Attivo',
                'status' => (function_exists('opcache_get_status') && opcache_get_status()) ? 'ok' : 'warning',
                'recommendation' => !(function_exists('opcache_get_status') && opcache_get_status()) ? 
                    __('Attiva OPcache per migliorare drasticamente le performance PHP.', 'wc-speed-doctor') : null
            ),
            'wp_debug_status' => array(
                'label' => __('WP Debug Status', 'wc-speed-doctor'),
                'value' => (defined('WP_DEBUG') && WP_DEBUG) ? 'Attivo' : 'Disattivo',
                'status' => (defined('WP_DEBUG') && WP_DEBUG) ? 'warning' : 'ok',
                'recommendation' => (defined('WP_DEBUG') && WP_DEBUG) ? 
                    __('Disabilita WP_DEBUG in produzione per migliori performance.', 'wc-speed-doctor') : null
            ),
            'object_cache_status' => array(
                'label' => __('Object Cache', 'wc-speed-doctor'),
                'value' => wp_using_ext_object_cache() ? 'Attivo (Esterno)' : 'Interno',
                'status' => wp_using_ext_object_cache() ? 'ok' : 'warning',
                'recommendation' => !wp_using_ext_object_cache() ? 
                    __('Considera Redis o Memcached per object cache esterno.', 'wc-speed-doctor') : null
            )
        );
    }
    
    public static function get_wordpress_info() {
        $active_plugins = get_option('active_plugins', array());
        $plugins_count = count($active_plugins);
        $current_theme = wp_get_theme();
        
        return array(
            'wp_version' => array(
                'label' => __('Versione WordPress', 'wc-speed-doctor'),
                'value' => get_bloginfo('version'),
                'status' => version_compare(get_bloginfo('version'), '6.0', '<') ? 'warning' : 'ok',
                'recommendation' => version_compare(get_bloginfo('version'), '6.0', '<') ? 
                    __('Aggiorna WordPress all\'ultima versione.', 'wc-speed-doctor') : null
            ),
            'wc_version' => array(
                'label' => __('Versione WooCommerce', 'wc-speed-doctor'),
                'value' => defined('WC_VERSION') ? WC_VERSION : 'N/A',
                'status' => (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) ? 'warning' : 'ok',
                'recommendation' => (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) ? 
                    __('Aggiorna WooCommerce all\'ultima versione.', 'wc-speed-doctor') : null
            ),
            'active_theme' => array(
                'label' => __('Tema Attivo', 'wc-speed-doctor'),
                'value' => $current_theme->get('Name') . ' (' . $current_theme->get('Version') . ')',
                'status' => 'ok',
                'recommendation' => null
            ),
            'plugins_count' => array(
                'label' => __('Plugin Attivi', 'wc-speed-doctor'),
                'value' => $plugins_count,
                'status' => ($plugins_count > 25) ? 'warning' : (($plugins_count > 35) ? 'critical' : 'ok'),
                'recommendation' => ($plugins_count > 25) ? 
                    __('Troppi plugin attivi possono rallentare il sito. Rivedi e disattiva quelli non necessari.', 'wc-speed-doctor') : null
            ),
            'page_load_time' => array(
                'label' => __('Tempo Caricamento Server', 'wc-speed-doctor'),
                'value' => is_null(self::$page_load_time) ? 'N/A' : round(self::$page_load_time, 3) . 's',
                'status' => (!is_null(self::$page_load_time) && self::$page_load_time > 3) ? 'critical' : 
                           ((!is_null(self::$page_load_time) && self::$page_load_time > 1.5) ? 'warning' : 'ok'),
                'recommendation' => (!is_null(self::$page_load_time) && self::$page_load_time > 1.5) ? 
                    __('Tempo di generazione server elevato. Controlla query database, plugin e tema.', 'wc-speed-doctor') : null
            ),
            'database_size' => array(
                'label' => __('Dimensione Database', 'wc-speed-doctor'),
                'value' => self::get_database_size(),
                'status' => 'info',
                'recommendation' => null
            ),
            'uploads_size' => array(
                'label' => __('Dimensione Upload', 'wc-speed-doctor'),
                'value' => self::get_uploads_size(),
                'status' => 'info',
                'recommendation' => null
            )
        );
    }
    
    public static function get_woocommerce_specific_info() {
        if (!class_exists('WooCommerce')) {
            return array();
        }
        
        $product_count = wp_count_posts('product');
        $order_count = wp_count_posts('shop_order');
        
        return array(
            'products_count' => array(
                'label' => __('Prodotti Totali', 'wc-speed-doctor'),
                'value' => $product_count->publish ?? 0,
                'status' => ($product_count->publish > 5000) ? 'warning' : 'ok',
                'recommendation' => ($product_count->publish > 5000) ? 
                    __('Molti prodotti possono influire sulle performance. Considera ottimizzazioni database.', 'wc-speed-doctor') : null
            ),
            'orders_count' => array(
                'label' => __('Ordini Totali', 'wc-speed-doctor'),
                'value' => ($order_count->wc_completed ?? 0) + ($order_count->wc_processing ?? 0),
                'status' => (($order_count->wc_completed + $order_count->wc_processing) > 10000) ? 'warning' : 'ok',
                'recommendation' => (($order_count->wc_completed + $order_count->wc_processing) > 10000) ? 
                    __('Molti ordini possono rallentare admin. Considera archiviazione ordini vecchi.', 'wc-speed-doctor') : null
            ),
            'wc_sessions' => array(
                'label' => __('Sessioni WooCommerce', 'wc-speed-doctor'),
                'value' => self::get_wc_sessions_count(),
                'status' => 'info',
                'recommendation' => null
            ),
            'wc_logs' => array(
                'label' => __('Log WooCommerce', 'wc-speed-doctor'),
                'value' => self::get_wc_logs_count(),
                'status' => 'info',
                'recommendation' => null
            )
        );
    }
    
    public static function display_diagnostics() {
        $env_info = self::get_environment_info();
        $wp_info = self::get_wordpress_info();
        $wc_info = self::get_woocommerce_specific_info();
        
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-admin-tools"></span> ' . esc_html__('Diagnostica Ambiente Server', 'wc-speed-doctor') . '</h2>';
        self::render_diagnostics_table($env_info);
        echo '</div>';
        
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-wordpress-alt"></span> ' . esc_html__('Diagnostica WordPress & WooCommerce', 'wc-speed-doctor') . '</h2>';
        self::render_diagnostics_table($wp_info);
        echo '</div>';
        
        if (!empty($wc_info)) {
            echo '<div class="wsd-section">';
            echo '<h2><span class="dashicons dashicons-store"></span> ' . esc_html__('Diagnostica WooCommerce Specifica', 'wc-speed-doctor') . '</h2>';
            self::render_diagnostics_table($wc_info);
            echo '</div>';
        }
    }
    
    private static function render_diagnostics_table($data) {
        echo '<table class="wp-list-table widefat fixed striped wsd-diagnostics-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Parametro', 'wc-speed-doctor') . '</th>';
        echo '<th>' . esc_html__('Valore', 'wc-speed-doctor') . '</th>';
        echo '<th>' . esc_html__('Stato', 'wc-speed-doctor') . '</th>';
        echo '<th>' . esc_html__('Raccomandazione', 'wc-speed-doctor') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($data as $key => $info) {
            $status_icon = self::get_status_icon($info['status']);
            $status_class = 'wsd-status-' . $info['status'];
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($info['label']) . '</strong></td>';
            echo '<td>' . esc_html($info['value']) . '</td>';
            echo '<td class="' . esc_attr($status_class) . '">' . $status_icon . '</td>';
            echo '<td>' . ($info['recommendation'] ? esc_html($info['recommendation']) : '—') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private static function get_status_icon($status) {
        switch ($status) {
            case 'ok':
                return '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> OK';
            case 'warning':
                return '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> Attenzione';
            case 'critical':
                return '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> Critico';
            case 'info':
                return '<span class="dashicons dashicons-info" style="color: #00a0d2;"></span> Info';
            default:
                return '<span class="dashicons dashicons-info" style="color: #00a0d2;"></span> Info';
        }
    }
    
    /**
     * Utility functions
     */
    
    private static function get_database_size() {
        global $wpdb;
        
        $size = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
            FROM information_schema.tables 
            WHERE table_schema='{$wpdb->dbname}'
        ");
        
        return $size ? $size . ' MB' : 'N/A';
    }
    
    private static function get_uploads_size() {
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (!is_dir($uploads_path)) {
            return 'N/A';
        }
        
        $size = self::get_directory_size($uploads_path);
        return self::format_bytes($size);
    }
    
    private static function get_directory_size($directory) {
        $size = 0;
        
        if (!is_dir($directory)) {
            return $size;
        }
        
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private static function format_bytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    private static function get_wc_sessions_count() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_wc_session_%'");
        return $count ? $count : 0;
    }
    
    private static function get_wc_logs_count() {
        $log_dir = WC_LOG_DIR;
        
        if (!is_dir($log_dir)) {
            return 0;
        }
        
        $files = glob($log_dir . '*.log');
        return count($files);
    }
    
    /**
     * Get system recommendations
     */
    public static function get_system_recommendations() {
        $env_info = self::get_environment_info();
        $wp_info = self::get_wordpress_info();
        $recommendations = array();
        
        foreach (array_merge($env_info, $wp_info) as $check) {
            if (!empty($check['recommendation'])) {
                $recommendations[] = $check['recommendation'];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get overall system health score
     */
    public static function get_health_score() {
        $env_info = self::get_environment_info();
        $wp_info = self::get_wordpress_info();
        
        $total_checks = 0;
        $passed_checks = 0;
        
        foreach (array_merge($env_info, $wp_info) as $check) {
            $total_checks++;
            if ($check['status'] === 'ok') {
                $passed_checks++;
            }
        }
        
        return $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;
    }
}