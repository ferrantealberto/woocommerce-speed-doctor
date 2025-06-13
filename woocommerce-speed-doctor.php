<?php
/**
 * Plugin Name: WooCommerce Speed Doctor Pro
 * Description: Plugin avanzato per diagnosticare e risolvere problemi di performance su WooCommerce. Il tuo detective personale per la velocità!
 * Version: 1.2.0
 * Author: Alby Dev Team
 * Text Domain: wc-speed-doctor
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti all'inizio del file
if (!defined('WSD_VERSION')) {
    define('WSD_VERSION', '1.2.0');
    define('WSD_FILE', __FILE__);
    define('WSD_PATH', dirname(WSD_FILE));
    define('WSD_URL', plugins_url('', WSD_FILE));
    define('WSD_BASENAME', plugin_basename(WSD_FILE));
    define('WSD_START_TIME', microtime(true)); // Per tracking performance globale
}

final class WooCommerce_Speed_Doctor {
    
    private static $instance = null;
    public $start_time;
    private $query_count_start;
    private $memory_start;
    
    private function __construct() {
        $this->start_time = microtime(true);
        $this->query_count_start = get_num_queries();
        $this->memory_start = memory_get_usage();
        
        $this->includes();
        $this->setup_hooks();
    }
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function includes() {
        $includes = array(
            '/includes/class-wsd-diagnostics.php',
            '/includes/class-wsd-logger.php',
            '/includes/class-wsd-auto-repair.php',
            '/includes/class-wsd-dashboard-widget.php',
            '/includes/class-wsd-email-notifications.php',
            '/includes/class-wsd-auto-scheduler.php',
            '/includes/class-wsd-settings-manager.php',
            '/includes/class-wsd-developer-api.php',
            '/includes/class-wsd-deployment-config.php'
        );
        
        foreach ($includes as $file) {
            $file_path = WSD_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log('[WSD] File non trovato: ' . $file_path);
            }
        }
    }
    
    private function setup_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('shutdown', array($this, 'measure_and_log_performance'));
        
        // Inizializza componenti solo se le classi esistono
        if (class_exists('WSD_Logger')) {
            WSD_Logger::init();
        }
        if (class_exists('WSD_Dashboard_Widget')) {
            WSD_Dashboard_Widget::init();
        }
        if (class_exists('WSD_Email_Notifications')) {
            WSD_Email_Notifications::init();
        }
        if (class_exists('WSD_Auto_Scheduler')) {
            WSD_Auto_Scheduler::init();
        }
        if (class_exists('WSD_Settings_Manager')) {
            WSD_Settings_Manager::init();
        }
        if (class_exists('WSD_Developer_API')) {
            WSD_Developer_API::init();
        }
        
        if (class_exists('WSD_Diagnostics')) {
            add_action('admin_init', array('WSD_Diagnostics', 'check_dependencies'));
        }
        
        // Hook AJAX per statistiche e test
        add_action('wp_ajax_wsd_get_current_stats', array($this, 'ajax_get_current_stats'));
        add_action('wp_ajax_wsd_run_performance_test', array($this, 'ajax_run_performance_test'));
        add_action('wp_ajax_wsd_clear_performance_logs', array($this, 'ajax_clear_performance_logs'));
        
        // Hook AJAX per auto-riparazione
        if (class_exists('WSD_Auto_Repair')) {
            add_action('wp_ajax_wsd_repair_action_scheduler', array('WSD_Auto_Repair', 'repair_action_scheduler'));
            add_action('wp_ajax_wsd_optimize_wp_cron', array('WSD_Auto_Repair', 'optimize_wp_cron'));
            add_action('wp_ajax_wsd_database_cleanup', array('WSD_Auto_Repair', 'database_cleanup'));
            add_action('wp_ajax_wsd_optimize_plugins', array('WSD_Auto_Repair', 'optimize_plugins'));
        }
        
        // Hook AJAX per notifiche email
        if (class_exists('WSD_Email_Notifications')) {
            add_action('wp_ajax_wsd_test_email_notification', array('WSD_Email_Notifications', 'test_email_notification'));
        }
        
        // Hook AJAX per scheduler
        if (class_exists('WSD_Auto_Scheduler')) {
            add_action('wp_ajax_wsd_toggle_auto_scheduler', array('WSD_Auto_Scheduler', 'ajax_toggle_scheduler'));
            add_action('wp_ajax_wsd_run_manual_maintenance', array('WSD_Auto_Scheduler', 'ajax_run_manual_maintenance'));
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wc-speed-doctor', false, WSD_PATH . '/languages/');
    }
    
    public function add_admin_menu() {
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                'woocommerce',
                __('Speed Doctor', 'wc-speed-doctor'),
                __('🚀 Speed Doctor', 'wc-speed-doctor'),
                'manage_options',
                'wsd-speed-doctor',
                array($this, 'render_admin_page')
            );
        }
        
        $main_page = add_menu_page(
            __('Speed Doctor', 'wc-speed-doctor'),
            __('🚀 Speed Doctor', 'wc-speed-doctor'),
            'manage_options',
            'wsd-speed-doctor-main',
            array($this, 'render_admin_page'),
            'dashicons-performance',
            30
        );
        
        // Sottopagine
        add_submenu_page(
            'wsd-speed-doctor-main',
            __('Dashboard', 'wc-speed-doctor'),
            __('📊 Dashboard', 'wc-speed-doctor'),
            'manage_options',
            'wsd-speed-doctor-main',
            array($this, 'render_admin_page')
        );
        
        if (class_exists('WSD_Email_Notifications')) {
            add_submenu_page(
                'wsd-speed-doctor-main',
                __('Email Settings', 'wc-speed-doctor'),
                __('📧 Email & Notifiche', 'wc-speed-doctor'),
                'manage_options',
                'wsd-email-settings',
                array('WSD_Email_Notifications', 'render_email_settings')
            );
        }
        
        if (class_exists('WSD_Auto_Scheduler')) {
            add_submenu_page(
                'wsd-speed-doctor-main',
                __('Scheduler', 'wc-speed-doctor'),
                __('⏰ Scheduler', 'wc-speed-doctor'),
                'manage_options',
                'wsd-scheduler',
                array('WSD_Auto_Scheduler', 'render_scheduler_dashboard')
            );
        }
        
        if (class_exists('WSD_Settings_Manager')) {
            add_submenu_page(
                'wsd-speed-doctor-main',
                __('Impostazioni', 'wc-speed-doctor'),
                __('⚙️ Impostazioni', 'wc-speed-doctor'),
                'manage_options',
                'wsd-settings',
                array('WSD_Settings_Manager', 'render_settings_page')
            );
        }
    }
    
    public function enqueue_admin_assets($hook) {
        $valid_hooks = array(
            'woocommerce_page_wsd-speed-doctor',
            'toplevel_page_wsd-speed-doctor-main',
            'speed-doctor_page_wsd-email-settings',
            'speed-doctor_page_wsd-scheduler',
            'speed-doctor_page_wsd-settings'
        );
        
        if (!in_array($hook, $valid_hooks)) {
            return;
        }
        
        wp_enqueue_style('wsd-admin-css', WSD_URL . '/assets/admin.css', array(), WSD_VERSION);
        wp_enqueue_script('wsd-admin-js', WSD_URL . '/assets/admin.js', array('jquery'), WSD_VERSION, true);
        
        // Enqueue widget assets se siamo nella dashboard
        if ($hook === 'index.php') {
            wp_enqueue_style('wsd-widget-css', WSD_URL . '/assets/widget.css', array(), WSD_VERSION);
            wp_enqueue_script('wsd-widget-js', WSD_URL . '/assets/widget.js', array('jquery'), WSD_VERSION, true);
        }
        
        wp_localize_script('wsd-admin-js', 'wsd_admin', array(
            'nonce' => wp_create_nonce('wsd_admin_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'dashboard_url' => admin_url('admin.php?page=wsd-speed-doctor-main'),
            'plugin_url' => WSD_URL
        ));
    }
    
    public function render_admin_page() {
        echo '<div class="wrap wsd-dashboard">';
        echo '<h1><span class="dashicons dashicons-performance"></span> ' . esc_html__('WooCommerce Speed Doctor Dashboard', 'wc-speed-doctor') . '</h1>';
        
        echo '<div class="notice notice-success"><p>';
        echo '<strong>✅ Plugin Attivo!</strong> Puoi accedere a Speed Doctor da: ';
        if (class_exists('WooCommerce')) {
            echo '<strong>WooCommerce > Speed Doctor</strong> oppure dal ';
        }
        echo '<strong>menu principale della sidebar</strong> (icona 🚀)';
        echo '</p></div>';
        
        $this->show_performance_alerts();
        $this->show_version_info();
        
        if (class_exists('WSD_Auto_Repair')) {
            WSD_Auto_Repair::display_repair_dashboard();
        }
        if (class_exists('WSD_Diagnostics')) {
            WSD_Diagnostics::display_diagnostics();
        }
        if (class_exists('WSD_Logger')) {
            WSD_Logger::display_performance_logs();
        }
        
        echo '</div>';
    }
    
    public function ajax_get_current_stats() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi necessari.', 'wc-speed-doctor'));
        }
        
        $recent_issues = array();
        $health = array();
        
        if (class_exists('WSD_Logger')) {
            $recent_issues = WSD_Logger::get_recent_issues();
        }
        if (class_exists('WSD_Auto_Repair')) {
            $health = WSD_Auto_Repair::get_system_health();
        }
        
        $stats = array(
            'memory_usage' => $this->format_bytes(memory_get_usage()),
            'query_count' => get_num_queries(),
            'error_count' => count($recent_issues),
            'performance_score' => $this->calculate_comprehensive_score($health)
        );
        
        wp_send_json_success($stats);
    }
    
    public function ajax_run_performance_test() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi necessari.', 'wc-speed-doctor'));
        }
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        $start_queries = get_num_queries();
        
        // Test completo del sistema più approfondito
        get_posts(array('numberposts' => 15, 'meta_query' => array()));
        
        if (class_exists('WooCommerce')) {
            wc_get_products(array('limit' => 10, 'status' => 'publish'));
            // Test query WooCommerce più complesse
            global $wpdb;
            $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_order_items LIMIT 5");
        }
        
        // Test caricamento opzioni
        wp_load_alloptions();
        
        // Test transient
        set_transient('wsd_test_transient', 'test_data', 60);
        get_transient('wsd_test_transient');
        delete_transient('wsd_test_transient');
        
        $end_time = microtime(true);
        $total_time = round(($end_time - $start_time) * 1000, 2);
        $memory_used = memory_get_usage() - $start_memory;
        $queries_used = get_num_queries() - $start_queries;
        
        $overall_status = 'good';
        if ($total_time > 2000 || $memory_used > 100 * 1024 * 1024 || $queries_used > 30) {
            $overall_status = 'critical';
        } elseif ($total_time > 1000 || $memory_used > 50 * 1024 * 1024 || $queries_used > 20) {
            $overall_status = 'warning';
        }
        
        $recommendations = array();
        
        if ($total_time > 2000) {
            $recommendations[] = '🐌 Tempo di risposta critico - verifica hosting e ottimizza database';
        } elseif ($total_time > 1000) {
            $recommendations[] = '⏰ Tempo di risposta elevato - controlla plugin e query database';
        }
        
        if ($memory_used > 100 * 1024 * 1024) {
            $recommendations[] = '🧠 Uso memoria eccessivo - alcuni plugin consumano troppa RAM';
        } elseif ($memory_used > 50 * 1024 * 1024) {
            $recommendations[] = '📊 Uso memoria elevato - ottimizza tema e plugin';
        }
        
        if ($queries_used > 30) {
            $recommendations[] = '🗄️ Troppe query database - implementa caching aggressivo';
        } elseif ($queries_used > 20) {
            $recommendations[] = '📈 Query database elevate - considera un plugin di cache';
        }
        
        // Analisi specifica per WooCommerce
        if (class_exists('WooCommerce')) {
            $product_count = wp_count_posts('product')->publish;
            $order_count = wp_count_posts('shop_order')->publish;
            
            if ($product_count > 1000) {
                $recommendations[] = '🛍️ Molti prodotti (' . $product_count . ') - ottimizza query prodotti';
            }
            
            if ($order_count > 5000) {
                $recommendations[] = '📦 Molti ordini (' . $order_count . ') - considera l\'archiviazione ordini vecchi';
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = '🎉 Performance eccellenti! Il sito è ben ottimizzato!';
            if ($total_time < 300) {
                $recommendations[] = '⚡ Velocità di risposta superba - sotto i 300ms!';
            }
        }
        
        $results = array(
            'total_time' => $total_time,
            'memory_used' => $this->format_bytes($memory_used),
            'query_count' => $queries_used,
            'overall_status' => $overall_status,
            'recommendations' => $recommendations
        );
        
        wp_send_json_success($results);
    }
    
    public function ajax_clear_performance_logs() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi necessari.', 'wc-speed-doctor'));
        }
        
        delete_option('wsd_performance_log');
        delete_option('wsd_slow_queries');
        delete_option('wsd_plugin_backup'); // Pulisci anche i backup plugin
        
        // Pulisci file di log se esistono
        $log_files = array(
            WP_CONTENT_DIR . '/wsd-performance.log',
            WP_CONTENT_DIR . '/wsd-errors.log'
        );
        
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                @unlink($log_file);
            }
        }
        
        wp_send_json_success(__('Log e backup eliminati con successo!', 'wc-speed-doctor'));
    }
    
    private function calculate_comprehensive_score($health) {
        if (empty($health)) {
            return 75; // Score di default se non ci sono dati
        }
        
        $score = 100;
        
        // Valuta ogni componente del sistema
        foreach ($health as $component => $status) {
            switch ($status['status']) {
                case 'critical':
                    $score -= 25;
                    break;
                case 'warning':
                    $score -= 15;
                    break;
                case 'not_available':
                    $score -= 5;
                    break;
            }
        }
        
        // Penalità aggiuntive per problemi specifici
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $score -= 20;
        }
        
        if (intval(ini_get('memory_limit')) < 256) {
            $score -= 15;
        }
        
        // Bonus per ottimizzazioni
        if (function_exists('opcache_get_status') && opcache_get_status()) {
            $score += 5;
        }
        
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $score += 5;
        }
        
        $recent_issues = array();
        if (class_exists('WSD_Logger')) {
            $recent_issues = WSD_Logger::get_recent_issues();
        }
        
        if (!empty($recent_issues)) {
            $score -= min(count($recent_issues) * 3, 30);
        }
        
        return max(0, min(100, $score));
    }
    
    public function measure_and_log_performance() {
        $end_time = microtime(true);
        $load_time = round(($end_time - $this->start_time) * 1000, 2);
        $memory_used = memory_get_usage() - $this->memory_start;
        $queries_used = get_num_queries() - $this->query_count_start;
        
        if (class_exists('WSD_Diagnostics')) {
            WSD_Diagnostics::set_page_load_time($load_time / 1000);
        }
        
        // Log solo performance problematiche per evitare spam
        if (($load_time > 3000 || $queries_used > 100 || $memory_used > 100 * 1024 * 1024) && class_exists('WSD_Logger')) {
            WSD_Logger::log_performance_issue(array(
                'load_time' => $load_time,
                'memory_used' => $this->format_bytes($memory_used),
                'queries_count' => $queries_used,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
                'timestamp' => current_time('mysql'),
                'severity' => $this->get_severity_level($load_time, $queries_used, $memory_used)
            ));
        }
    }
    
    private function get_severity_level($load_time, $queries, $memory) {
        if ($load_time > 5000 || $queries > 200 || $memory > 200 * 1024 * 1024) {
            return 'critical';
        } elseif ($load_time > 3000 || $queries > 100 || $memory > 100 * 1024 * 1024) {
            return 'high';
        } else {
            return 'medium';
        }
    }
    
    private function show_performance_alerts() {
        if (!class_exists('WSD_Logger')) {
            return;
        }
        
        $recent_issues = WSD_Logger::get_recent_issues();
        $critical_issues = array_filter($recent_issues, function($issue) {
            return isset($issue['severity']) && $issue['severity'] === 'critical';
        });
        
        if (!empty($critical_issues)) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>🚨 Performance Alert Critico!</strong> Rilevati ' . count($critical_issues) . ' problemi critici nelle ultime 24 ore. ';
            echo '<a href="#wsd-performance-logs">Intervento urgente necessario</a>';
            echo '</p></div>';
        } elseif (!empty($recent_issues)) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>⚠️ Performance Alert!</strong> Rilevati ' . count($recent_issues) . ' problemi di performance nelle ultime 24 ore. ';
            echo '<a href="#wsd-performance-logs">Vedi dettagli sotto</a>';
            echo '</p></div>';
        }
    }
    
    private function show_version_info() {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>🚀 WSD Pro v' . WSD_VERSION . '</strong> - Nuove funzionalità: ';
        echo '<strong>✨ Ottimizzazione Plugin Automatica</strong>, ';
        echo '<strong>🔧 Riparazione Avanzata Database</strong>, ';
        echo '<strong>📊 Scoring Intelligente Performance</strong>';
        echo '</p></div>';
    }
    
    private function format_bytes($size) {
        if ($size == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}

// Funzione per inizializzare il plugin
function run_woocommerce_speed_doctor() {
    WooCommerce_Speed_Doctor::get_instance();
}

// Hook di attivazione con controllo errori
register_activation_hook(__FILE__, function() {
    try {
        // Crea tabelle/opzioni necessarie se non esistono
        $default_options = array(
            'wsd_performance_log' => array(),
            'wsd_slow_queries' => array(),
            'wsd_settings' => array(
                'monitoring_enabled' => true,
                'auto_optimization' => false,
                'log_retention_days' => 7
            )
        );
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
        
        // Log con costante verificata
        $version = defined('WSD_VERSION') ? WSD_VERSION : '1.2.0';
        error_log('[WSD] Plugin attivato con successo il ' . date('Y-m-d H:i:s') . ' - v' . $version);
        
    } catch (Exception $e) {
        error_log('[WSD] Errore durante attivazione: ' . $e->getMessage());
        wp_die('Errore durante l\'attivazione del plugin WSD: ' . $e->getMessage());
    }
});

// Hook di disattivazione
register_deactivation_hook(__FILE__, function() {
    try {
        // Pulisci cache e transient temporanei
        delete_transient('wsd_test_transient');
        wp_cache_flush();
        
        error_log('[WSD] Plugin disattivato il ' . date('Y-m-d H:i:s'));
        
    } catch (Exception $e) {
        error_log('[WSD] Errore durante disattivazione: ' . $e->getMessage());
    }
});

// Inizializza il plugin su plugins_loaded
add_action('plugins_loaded', 'run_woocommerce_speed_doctor');

// Verifica che il plugin sia configurato correttamente
add_action('admin_notices', function() {
    if (!defined('WSD_VERSION')) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>WSD Error:</strong> Il plugin non è stato inizializzato correttamente. Prova a disattivar e riattivare il plugin.';
        echo '</p></div>';
    }
});
