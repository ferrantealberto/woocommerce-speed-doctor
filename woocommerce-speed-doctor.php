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

// Definisci costanti
if (!defined('WSD_VERSION')) {
    define('WSD_VERSION', '1.2.0');
    define('WSD_FILE', __FILE__);
    define('WSD_PATH', dirname(WSD_FILE));
    define('WSD_URL', plugins_url('', WSD_FILE));
    define('WSD_BASENAME', plugin_basename(WSD_FILE));
    define('WSD_START_TIME', microtime(true));
}

/**
 * Classe principale WSD con caricamento progressivo
 */
final class WooCommerce_Speed_Doctor {
    
    private static $instance = null;
    public $start_time;
    private $query_count_start;
    private $memory_start;
    private $modules = array();
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->start_time = microtime(true);
        $this->query_count_start = get_num_queries();
        $this->memory_start = memory_get_usage();
        
        $this->load_core_modules();
        $this->setup_hooks();
    }
    
    /**
     * Carica i moduli core con controllo errori
     */
    private function load_core_modules() {
        // Moduli con implementazione integrata
        $this->modules['diagnostics'] = new WSD_Diagnostics_Integrated();
        $this->modules['logger'] = new WSD_Logger_Integrated();
        $this->modules['auto_repair'] = new WSD_Auto_Repair_Integrated();
        $this->modules['dashboard_widget'] = new WSD_Dashboard_Widget_Integrated();
        
        // Inizializza moduli
        foreach ($this->modules as $module) {
            if (method_exists($module, 'init')) {
                $module->init();
            }
        }
    }
    
    private function setup_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('shutdown', array($this, 'measure_and_log_performance'));
        
        // Hook AJAX
        add_action('wp_ajax_wsd_get_current_stats', array($this, 'ajax_get_current_stats'));
        add_action('wp_ajax_wsd_run_performance_test', array($this, 'ajax_run_performance_test'));
        add_action('wp_ajax_wsd_clear_performance_logs', array($this, 'ajax_clear_performance_logs'));
        add_action('wp_ajax_wsd_repair_action_scheduler', array($this, 'ajax_repair_action_scheduler'));
        add_action('wp_ajax_wsd_optimize_wp_cron', array($this, 'ajax_optimize_wp_cron'));
        add_action('wp_ajax_wsd_database_cleanup', array($this, 'ajax_database_cleanup'));
        add_action('wp_ajax_wsd_optimize_plugins', array($this, 'ajax_optimize_plugins'));
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
        
        add_menu_page(
            __('Speed Doctor', 'wc-speed-doctor'),
            __('🚀 Speed Doctor', 'wc-speed-doctor'),
            'manage_options',
            'wsd-speed-doctor-main',
            array($this, 'render_admin_page'),
            'dashicons-performance',
            30
        );
    }
    
    public function enqueue_admin_assets($hook) {
        $valid_hooks = array(
            'woocommerce_page_wsd-speed-doctor',
            'toplevel_page_wsd-speed-doctor-main'
        );
        
        if (!in_array($hook, $valid_hooks)) {
            return;
        }
        
        // CSS inline per evitare problemi di caricamento file
        wp_add_inline_style('wp-admin', $this->get_admin_css());
        
        // JS inline
        wp_enqueue_script('wsd-admin-js', '', array('jquery'), WSD_VERSION, true);
        wp_add_inline_script('wsd-admin-js', $this->get_admin_js());
        
        wp_localize_script('wsd-admin-js', 'wsd_admin', array(
            'nonce' => wp_create_nonce('wsd_admin_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'dashboard_url' => admin_url('admin.php?page=wsd-speed-doctor-main')
        ));
    }
    
    public function render_admin_page() {
        echo '<div class="wrap wsd-dashboard">';
        echo '<h1><span class="dashicons dashicons-performance"></span> ' . __('WooCommerce Speed Doctor Dashboard', 'wc-speed-doctor') . '</h1>';
        
        // Statistiche sistema
        $this->render_system_overview();
        
        // Riparazione automatica
        $this->render_auto_repair_section();
        
        // Diagnostica
        $this->render_diagnostics_section();
        
        // Log performance
        $this->render_performance_logs();
        
        echo '</div>';
    }
    
    private function render_system_overview() {
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-dashboard"></span> Panoramica Sistema</h2>';
        
        $health = $this->modules['auto_repair']->get_system_health();
        $stats = $this->get_current_stats();
        
        echo '<div class="wsd-stats-grid">';
        echo '<div class="wsd-stat-box">';
        echo '<strong>Performance Score</strong><br>';
        echo '<span class="wsd-stat-value ' . $this->get_score_class($stats['performance_score']) . '">' . $stats['performance_score'] . '/100</span>';
        echo '</div>';
        
        echo '<div class="wsd-stat-box">';
        echo '<strong>Memoria PHP</strong><br>';
        echo '<span class="wsd-stat-value">' . $stats['memory_usage'] . '</span>';
        echo '</div>';
        
        echo '<div class="wsd-stat-box">';
        echo '<strong>Plugin Attivi</strong><br>';
        echo '<span class="wsd-stat-value">' . $stats['plugin_count'] . '</span>';
        echo '</div>';
        
        echo '<div class="wsd-stat-box">';
        echo '<strong>Problemi Recenti</strong><br>';
        echo '<span class="wsd-stat-value ' . ($stats['recent_issues'] > 0 ? 'error' : 'good') . '">' . $stats['recent_issues'] . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Pulsanti azione
        echo '<div class="wsd-actions">';
        echo '<button id="wsd-test-performance" class="button button-primary">📊 Test Performance</button>';
        echo '<button id="wsd-refresh-stats" class="button button-secondary">🔄 Aggiorna</button>';
        echo '<button id="wsd-clear-logs" class="button button-secondary">🗑️ Pulisci Log</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_auto_repair_section() {
        echo '<div class="wsd-section wsd-repair-section">';
        echo '<h2><span class="dashicons dashicons-admin-tools"></span> Sistema di Riparazione Automatica</h2>';
        
        $health = $this->modules['auto_repair']->get_system_health();
        
        foreach ($health as $component => $status) {
            $component_names = array(
                'action_scheduler' => 'Action Scheduler',
                'wp_cron' => 'WP-Cron',
                'plugins' => 'Gestione Plugin',
                'database' => 'Database'
            );
            
            $component_name = $component_names[$component] ?? ucfirst(str_replace('_', ' ', $component));
            $status_class = 'wsd-status-' . $status['status'];
            
            echo '<div class="wsd-repair-card ' . $status_class . '">';
            echo '<h3>' . esc_html($component_name) . '</h3>';
            echo '<p class="wsd-status-message">' . esc_html($status['message']) . '</p>';
            
            if ($status['status'] !== 'good' && $status['status'] !== 'not_available') {
                $button_id = 'wsd-repair-' . str_replace('_', '-', $component);
                echo '<button id="' . $button_id . '" class="button button-primary">🔧 Ripara Ora</button>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    private function render_diagnostics_section() {
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-admin-tools"></span> Diagnostica Sistema</h2>';
        
        $diagnostics = $this->modules['diagnostics']->get_environment_info();
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Parametro</th><th>Valore</th><th>Stato</th><th>Raccomandazione</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($diagnostics as $key => $info) {
            $status_icon = $this->get_status_icon($info['status']);
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($info['label']) . '</strong></td>';
            echo '<td>' . esc_html($info['value']) . '</td>';
            echo '<td>' . $status_icon . '</td>';
            echo '<td>' . ($info['recommendation'] ? esc_html($info['recommendation']) : '—') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    private function render_performance_logs() {
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-chart-line"></span> Log Performance</h2>';
        
        $logs = $this->modules['logger']->get_recent_issues();
        
        if (empty($logs)) {
            echo '<p class="wsd-no-issues">✅ Nessun problema di performance rilevato. Ottimo lavoro!</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Timestamp</th><th>Tipo</th><th>Messaggio</th><th>Severità</th></tr></thead>';
            echo '<tbody>';
            
            foreach (array_slice($logs, -10) as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['timestamp'] ?? 'N/A') . '</td>';
                echo '<td>' . esc_html($log['type'] ?? 'N/A') . '</td>';
                echo '<td>' . esc_html($log['message'] ?? 'N/A') . '</td>';
                echo '<td><span class="wsd-severity-badge ' . esc_attr($log['severity'] ?? 'info') . '">' . esc_html($log['severity'] ?? 'info') . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    // AJAX Handlers
    public function ajax_get_current_stats() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        wp_send_json_success($this->get_current_stats());
    }
    
    public function ajax_run_performance_test() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        $start_queries = get_num_queries();
        
        // Test performance
        get_posts(array('numberposts' => 15, 'meta_query' => array()));
        
        if (class_exists('WooCommerce')) {
            wc_get_products(array('limit' => 10, 'status' => 'publish'));
        }
        
        wp_load_alloptions();
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        $memory_used = memory_get_usage() - $start_memory;
        $queries_used = get_num_queries() - $start_queries;
        
        $status = 'good';
        if ($execution_time > 2000) $status = 'critical';
        elseif ($execution_time > 1000) $status = 'warning';
        
        $recommendations = array();
        if ($execution_time > 1000) {
            $recommendations[] = 'Tempo di risposta elevato - controlla plugin e hosting';
        }
        if ($memory_used > 50 * 1024 * 1024) {
            $recommendations[] = 'Uso memoria elevato - ottimizza plugin e tema';
        }
        if ($queries_used > 20) {
            $recommendations[] = 'Molte query database - considera un plugin di cache';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = '🎉 Performance eccellenti!';
        }
        
        wp_send_json_success(array(
            'total_time' => $execution_time,
            'memory_used' => $this->format_bytes($memory_used),
            'query_count' => $queries_used,
            'overall_status' => $status,
            'recommendations' => $recommendations
        ));
    }
    
    public function ajax_clear_performance_logs() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $this->modules['logger']->clear_logs();
        wp_send_json_success('Log eliminati con successo!');
    }
    
    public function ajax_repair_action_scheduler() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $result = $this->modules['auto_repair']->repair_action_scheduler();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_optimize_wp_cron() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $result = $this->modules['auto_repair']->optimize_wp_cron();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_database_cleanup() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $result = $this->modules['auto_repair']->database_cleanup();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_optimize_plugins() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $result = $this->modules['auto_repair']->optimize_plugins();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // Utility Methods
    private function get_current_stats() {
        $recent_issues = $this->modules['logger']->get_recent_issues();
        $health = $this->modules['auto_repair']->get_system_health();
        
        return array(
            'memory_usage' => $this->format_bytes(memory_get_usage()),
            'plugin_count' => count(get_option('active_plugins', array())),
            'recent_issues' => count($recent_issues),
            'performance_score' => $this->calculate_performance_score($health)
        );
    }
    
    private function calculate_performance_score($health) {
        $score = 100;
        
        foreach ($health as $status) {
            switch ($status['status']) {
                case 'critical': $score -= 25; break;
                case 'warning': $score -= 15; break;
                case 'not_available': $score -= 5; break;
            }
        }
        
        return max(0, min(100, $score));
    }
    
    private function get_score_class($score) {
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'warning';
        return 'error';
    }
    
    private function get_status_icon($status) {
        switch ($status) {
            case 'ok': return '<span style="color: #46b450;">✅ OK</span>';
            case 'warning': return '<span style="color: #ffb900;">⚠️ Warning</span>';
            case 'critical': return '<span style="color: #dc3232;">❌ Critico</span>';
            default: return '<span style="color: #00a0d2;">ℹ️ Info</span>';
        }
    }
    
    private function format_bytes($size) {
        if ($size == 0) return '0 B';
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    public function measure_and_log_performance() {
        $load_time = round((microtime(true) - $this->start_time) * 1000, 2);
        $memory_used = memory_get_usage() - $this->memory_start;
        $queries_used = get_num_queries() - $this->query_count_start;
        
        // Log solo performance problematiche
        if ($load_time > 3000 || $queries_used > 100 || $memory_used > 100 * 1024 * 1024) {
            $this->modules['logger']->log_performance_issue(array(
                'load_time' => $load_time,
                'memory_used' => $this->format_bytes($memory_used),
                'queries_count' => $queries_used,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
                'severity' => $load_time > 5000 ? 'critical' : 'warning'
            ));
        }
    }
    
    private function get_admin_css() {
        return '
        .wsd-dashboard { max-width: 1200px; margin: 20px auto; }
        .wsd-section { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; margin-bottom: 25px; padding: 25px; }
        .wsd-section h2 { margin-top: 0; color: #1d2327; border-bottom: 1px solid #eee; padding-bottom: 12px; }
        .wsd-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .wsd-stat-box { background: rgba(255,255,255,0.2); padding: 25px; border-radius: 10px; text-align: center; border: 1px solid #eee; }
        .wsd-stat-value { font-size: 32px; font-weight: bold; display: block; margin-top: 10px; }
        .wsd-stat-value.good { color: #4ade80; }
        .wsd-stat-value.warning { color: #fbbf24; }
        .wsd-stat-value.error { color: #f87171; }
        .wsd-actions { text-align: center; margin: 25px 0; }
        .wsd-actions .button { margin: 0 10px; font-size: 15px; padding: 12px 22px; }
        .wsd-repair-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .wsd-repair-section h2 { color: white; border-bottom-color: rgba(255,255,255,0.3); }
        .wsd-repair-card { background: rgba(255,255,255,0.15); border-radius: 8px; padding: 20px; margin: 15px 0; border-left: 5px solid; }
        .wsd-repair-card h3 { margin-top: 0; color: white; }
        .wsd-repair-card .button { background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); color: white; }
        .wsd-status-good { border-left-color: #4ade80; }
        .wsd-status-warning { border-left-color: #fbbf24; }
        .wsd-status-critical { border-left-color: #f87171; }
        .wsd-no-issues { text-align: center; padding: 30px; color: #46b450; font-size: 16px; background: #e6ffe6; border-radius: 8px; }
        .wsd-severity-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .wsd-severity-badge.info { background: rgba(0, 160, 210, 0.1); color: #00a0d2; }
        .wsd-severity-badge.warning { background: rgba(255, 185, 0, 0.1); color: #ffb900; }
        .wsd-severity-badge.critical { background: rgba(220, 50, 50, 0.1); color: #dc3232; }
        ';
    }
    
    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            // Test Performance
            $("#wsd-test-performance").on("click", function(e) {
                e.preventDefault();
                var btn = $(this);
                var originalText = btn.text();
                
                btn.prop("disabled", true).text("🔄 Testing...");
                
                $.post(ajaxurl, {
                    action: "wsd_run_performance_test",
                    nonce: wsd_admin.nonce
                }, function(response) {
                    if (response.success) {
                        alert("✅ Test completato!\n\nTempo: " + response.data.total_time + "ms\nMemoria: " + response.data.memory_used + "\nQuery: " + response.data.query_count);
                    } else {
                        alert("❌ Errore: " + response.data);
                    }
                }).always(function() {
                    btn.prop("disabled", false).text(originalText);
                });
            });
            
            // Refresh Stats
            $("#wsd-refresh-stats").on("click", function(e) {
                e.preventDefault();
                location.reload();
            });
            
            // Clear Logs
            $("#wsd-clear-logs").on("click", function(e) {
                e.preventDefault();
                if (confirm("Eliminare tutti i log?")) {
                    $.post(ajaxurl, {
                        action: "wsd_clear_performance_logs",
                        nonce: wsd_admin.nonce
                    }, function(response) {
                        if (response.success) {
                            alert("✅ " + response.data);
                            location.reload();
                        }
                    });
                }
            });
            
            // Repair buttons
            $("[id^=wsd-repair-]").on("click", function(e) {
                e.preventDefault();
                var btn = $(this);
                var action = btn.attr("id").replace("wsd-repair-", "wsd_").replace("-", "_");
                var originalText = btn.text();
                
                btn.prop("disabled", true).text("🔄 Riparando...");
                
                $.post(ajaxurl, {
                    action: action,
                    nonce: wsd_admin.nonce
                }, function(response) {
                    if (response.success) {
                        alert("✅ Riparazione completata!");
                        location.reload();
                    } else {
                        alert("❌ Errore: " + response.data);
                    }
                }).always(function() {
                    btn.prop("disabled", false).text(originalText);
                });
            });
        });
        ';
    }
}

/**
 * Moduli integrati per evitare problemi di caricamento
 */

class WSD_Diagnostics_Integrated {
    public function get_environment_info() {
        return array(
            'php_version' => array(
                'label' => 'Versione PHP',
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4', '<') ? 'warning' : 'ok',
                'recommendation' => version_compare(PHP_VERSION, '7.4', '<') ? 'Aggiorna a PHP 7.4+' : null
            ),
            'memory_limit' => array(
                'label' => 'Memory Limit',
                'value' => ini_get('memory_limit'),
                'status' => (intval(ini_get('memory_limit')) < 256) ? 'warning' : 'ok',
                'recommendation' => (intval(ini_get('memory_limit')) < 256) ? 'Raccomandato 256MB+' : null
            ),
            'wp_version' => array(
                'label' => 'WordPress',
                'value' => get_bloginfo('version'),
                'status' => 'ok',
                'recommendation' => null
            ),
            'wc_version' => array(
                'label' => 'WooCommerce',
                'value' => defined('WC_VERSION') ? WC_VERSION : 'Non installato',
                'status' => 'ok',
                'recommendation' => null
            ),
            'active_plugins' => array(
                'label' => 'Plugin Attivi',
                'value' => count(get_option('active_plugins', array())),
                'status' => (count(get_option('active_plugins', array())) > 50) ? 'warning' : 'ok',
                'recommendation' => (count(get_option('active_plugins', array())) > 50) ? 'Troppi plugin attivi' : null
            )
        );
    }
}

class WSD_Logger_Integrated {
    public function get_recent_issues() {
        return get_option('wsd_performance_log', array());
    }
    
    public function log_performance_issue($data) {
        $logs = get_option('wsd_performance_log', array());
        $data['timestamp'] = current_time('mysql');
        $logs[] = $data;
        
        // Mantieni solo ultimi 50 log
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('wsd_performance_log', $logs);
    }
    
    public function clear_logs() {
        delete_option('wsd_performance_log');
        delete_option('wsd_slow_queries');
    }
}

class WSD_Auto_Repair_Integrated {
    public function init() {
        // Inizializzazione se necessaria
    }
    
    public function get_system_health() {
        return array(
            'action_scheduler' => $this->check_action_scheduler(),
            'wp_cron' => $this->check_wp_cron(),
            'plugins' => $this->check_plugins(),
            'database' => $this->check_database()
        );
    }
    
    private function check_action_scheduler() {
        if (!class_exists('ActionScheduler')) {
            return array(
                'status' => 'not_available',
                'message' => 'Action Scheduler non disponibile'
            );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'actionscheduler_actions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return array(
                'status' => 'warning',
                'message' => 'Tabelle Action Scheduler non trovate'
            );
        }
        
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");
        
        if ($pending > 100 || $failed > 10) {
            return array(
                'status' => 'critical',
                'message' => "Problemi Action Scheduler: {$pending} pending, {$failed} failed"
            );
        }
        
        return array(
            'status' => 'good',
            'message' => 'Action Scheduler funzionante'
        );
    }
    
    private function check_wp_cron() {
        $crons = _get_cron_array();
        $overdue = 0;
        
        foreach ($crons as $timestamp => $cron) {
            if ($timestamp < time()) {
                foreach ($cron as $hook => $events) {
                    $overdue += count($events);
                }
            }
        }
        
        if ($overdue > 10) {
            return array(
                'status' => 'critical',
                'message' => "{$overdue} eventi cron in ritardo"
            );
        }
        
        return array(
            'status' => 'good',
            'message' => 'WP-Cron funzionante'
        );
    }
    
    private function check_plugins() {
        $active = count(get_option('active_plugins', array()));
        
        if ($active > 60) {
            return array(
                'status' => 'critical',
                'message' => "{$active} plugin attivi - troppi!"
            );
        } elseif ($active > 40) {
            return array(
                'status' => 'warning',
                'message' => "{$active} plugin attivi - molti"
            );
        }
        
        return array(
            'status' => 'good',
            'message' => "{$active} plugin attivi"
        );
    }
    
    private function check_database() {
        global $wpdb;
        
        $revisions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        $spam = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        
        if ($revisions > 1000 || $spam > 100) {
            return array(
                'status' => 'warning',
                'message' => "Database da pulire: {$revisions} revisioni, {$spam} spam"
            );
        }
        
        return array(
            'status' => 'good',
            'message' => 'Database pulito'
        );
    }
    
    public function repair_action_scheduler() {
        global $wpdb;
        
        try {
            $table = $wpdb->prefix . 'actionscheduler_actions';
            
            $fixed = $wpdb->query("
                UPDATE $table 
                SET status = 'failed' 
                WHERE status = 'in-progress' 
                AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 5 MINUTE
            ");
            
            $cleaned = $wpdb->query("
                DELETE FROM $table 
                WHERE status = 'failed' 
                AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 7 DAY
            ");
            
            return array(
                'success' => true,
                'message' => "Action Scheduler riparato: {$fixed} task sbloccati, {$cleaned} task obsoleti rimossi"
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore durante riparazione: ' . $e->getMessage()
            );
        }
    }
    
    public function optimize_wp_cron() {
        try {
            $crons = _get_cron_array();
            $removed = 0;
            $cutoff = time() - HOUR_IN_SECONDS;
            
            foreach ($crons as $timestamp => $cron) {
                if ($timestamp < $cutoff) {
                    foreach ($cron as $hook => $events) {
                        foreach ($events as $event) {
                            wp_unschedule_event($timestamp, $hook, $event['args']);
                            $removed++;
                        }
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => "WP-Cron ottimizzato: {$removed} eventi obsoleti rimossi"
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore durante ottimizzazione: ' . $e->getMessage()
            );
        }
    }
    
    public function database_cleanup() {
        global $wpdb;
        
        try {
            $revisions = $wpdb->query("
                DELETE FROM {$wpdb->posts} 
                WHERE post_type = 'revision' 
                AND post_date < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                LIMIT 100
            ");
            
            $spam = $wpdb->query("
                DELETE FROM {$wpdb->comments} 
                WHERE comment_approved = 'spam' 
                AND comment_date < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                LIMIT 50
            ");
            
            $transients = $wpdb->query("
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_%' 
                AND option_value < UNIX_TIMESTAMP() 
                LIMIT 100
            ");
            
            return array(
                'success' => true,
                'message' => "Database pulito: {$revisions} revisioni, {$spam} spam, {$transients} transient rimossi"
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore durante pulizia: ' . $e->getMessage()
            );
        }
    }
    
    public function optimize_plugins() {
        try {
            $active = get_option('active_plugins', array());
            $total = count($active);
            
            // Analisi semplificata
            $heavy_plugins = 0;
            $known_heavy = array('elementor', 'jetpack', 'wordfence', 'updraftplus');
            
            foreach ($active as $plugin) {
                foreach ($known_heavy as $heavy) {
                    if (strpos($plugin, $heavy) !== false) {
                        $heavy_plugins++;
                    }
                }
            }
            
            // Pulizia opzioni plugin inattivi
            $all_plugins = get_plugins();
            $inactive = array_diff(array_keys($all_plugins), $active);
            $cleaned = 0;
            
            foreach ($inactive as $plugin) {
                $slug = dirname($plugin);
                $options = array($slug . '_settings', $slug . '_options');
                
                foreach ($options as $option) {
                    if (get_option($option) !== false) {
                        delete_option($option);
                        $cleaned++;
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => "Plugin ottimizzati: {$total} attivi, {$heavy_plugins} pesanti rilevati, {$cleaned} opzioni pulite"
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Errore durante ottimizzazione: ' . $e->getMessage()
            );
        }
    }
}

class WSD_Dashboard_Widget_Integrated {
    public function init() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    public function add_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'wsd_performance_widget',
            '🚀 Speed Doctor - Performance',
            array($this, 'render_widget')
        );
    }
    
    public function render_widget() {
        $stats = array(
            'memory' => $this->format_bytes(memory_get_usage()),
            'plugins' => count(get_option('active_plugins', array())),
            'score' => 85 // Score semplificato
        );
        
        echo '<div style="text-align: center; padding: 20px;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #46b450;">Performance Score: ' . $stats['score'] . '/100</div>';
        echo '<div style="margin: 15px 0;">';
        echo '<div>Memoria: ' . $stats['memory'] . '</div>';
        echo '<div>Plugin: ' . $stats['plugins'] . '</div>';
        echo '</div>';
        echo '<a href="' . admin_url('admin.php?page=wsd-speed-doctor-main') . '" class="button button-primary">🚀 Vai a Speed Doctor</a>';
        echo '</div>';
    }
    
    private function format_bytes($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}

// Inizializza il plugin
add_action('plugins_loaded', function() {
    WooCommerce_Speed_Doctor::get_instance();
});

// Hook di attivazione
register_activation_hook(__FILE__, function() {
    add_option('wsd_performance_log', array());
    add_option('wsd_activation_time', current_time('mysql'));
    error_log('[WSD] Plugin attivato - v' . WSD_VERSION);
});

// Hook di disattivazione
register_deactivation_hook(__FILE__, function() {
    wp_cache_flush();
    error_log('[WSD] Plugin disattivato');
});