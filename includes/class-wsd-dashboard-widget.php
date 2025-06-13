<?php
/**
 * Dashboard Widget per WordPress - Monitoraggio Performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Dashboard_Widget {
    
    public static function init() {
        add_action('wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widget'));
        add_action('wp_ajax_wsd_widget_refresh', array(__CLASS__, 'ajax_widget_refresh'));
        
        // Aggiungi CSS/JS per il widget
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_widget_assets'));
    }
    
    public static function add_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'wsd_performance_widget',
            '🚀 Speed Doctor - Performance Monitor',
            array(__CLASS__, 'render_widget'),
            array(__CLASS__, 'render_widget_config')
        );
    }
    
    public static function enqueue_widget_assets($hook) {
        if ($hook !== 'index.php') {
            return;
        }
        
        wp_enqueue_style('wsd-widget-css', WSD_URL . '/assets/widget.css', array(), WSD_VERSION);
        wp_enqueue_script('wsd-widget-js', WSD_URL . '/assets/widget.js', array('jquery'), WSD_VERSION, true);
        
        wp_localize_script('wsd-widget-js', 'wsd_widget', array(
            'nonce' => wp_create_nonce('wsd_widget_nonce'),
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
    
    public static function render_widget() {
        $health = WSD_Auto_Repair::get_system_health();
        $recent_issues = WSD_Logger::get_recent_issues(24);
        $performance_score = self::calculate_widget_score($health);
        
        // Dati per le statistiche quick
        $stats = array(
            'memory_usage' => self::format_bytes(memory_get_usage()),
            'active_plugins' => count(get_option('active_plugins', array())),
            'recent_issues' => count($recent_issues),
            'performance_score' => $performance_score
        );
        
        echo '<div class="wsd-widget-container">';
        
        // Header con score
        echo '<div class="wsd-widget-header">';
        echo '<div class="wsd-score-circle ' . self::get_score_class($performance_score) . '">';
        echo '<span class="wsd-score-number">' . $performance_score . '</span>';
        echo '<span class="wsd-score-label">Performance</span>';
        echo '</div>';
        echo '<div class="wsd-widget-actions">';
        echo '<button class="button button-small wsd-widget-refresh" title="Aggiorna">🔄</button>';
        echo '<a href="' . admin_url('admin.php?page=wsd-speed-doctor-main') . '" class="button button-small button-primary">Dashboard</a>';
        echo '</div>';
        echo '</div>';
        
        // Statistiche rapide
        echo '<div class="wsd-widget-stats">';
        echo '<div class="wsd-stat-item">';
        echo '<span class="wsd-stat-label">Memoria PHP</span>';
        echo '<span class="wsd-stat-value">' . $stats['memory_usage'] . '</span>';
        echo '</div>';
        echo '<div class="wsd-stat-item">';
        echo '<span class="wsd-stat-label">Plugin Attivi</span>';
        echo '<span class="wsd-stat-value">' . $stats['active_plugins'] . '</span>';
        echo '</div>';
        echo '<div class="wsd-stat-item">';
        echo '<span class="wsd-stat-label">Issues 24h</span>';
        echo '<span class="wsd-stat-value ' . ($stats['recent_issues'] > 0 ? 'warning' : 'good') . '">' . $stats['recent_issues'] . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Status componenti critici
        echo '<div class="wsd-widget-components">';
        foreach ($health as $component => $status) {
            $icon = self::get_component_icon($component);
            $status_class = 'wsd-component-' . $status['status'];
            
            echo '<div class="wsd-component-status ' . $status_class . '" title="' . esc_attr($status['message']) . '">';
            echo '<span class="wsd-component-icon">' . $icon . '</span>';
            echo '<span class="wsd-component-name">' . self::get_component_name($component) . '</span>';
            echo '<span class="wsd-component-indicator"></span>';
            echo '</div>';
        }
        echo '</div>';
        
        // Alert se ci sono problemi critici
        $critical_issues = array_filter($health, function($status) {
            return $status['status'] === 'critical';
        });
        
        if (!empty($critical_issues)) {
            echo '<div class="wsd-widget-alert critical">';
            echo '<strong>🚨 ' . count($critical_issues) . ' problema/i critico/i rilevato/i!</strong>';
            echo '<a href="' . admin_url('admin.php?page=wsd-speed-doctor-main') . '" class="wsd-alert-link">Risolvi ora →</a>';
            echo '</div>';
        } elseif (!empty($recent_issues)) {
            echo '<div class="wsd-widget-alert warning">';
            echo '<strong>⚠️ ' . count($recent_issues) . ' issue/s performance nelle ultime 24h</strong>';
            echo '<a href="' . admin_url('admin.php?page=wsd-speed-doctor-main') . '" class="wsd-alert-link">Analizza →</a>';
            echo '</div>';
        }
        
        // Footer con ultimo aggiornamento
        echo '<div class="wsd-widget-footer">';
        echo '<small>Ultimo aggiornamento: <span class="wsd-last-update">' . current_time('H:i:s') . '</span></small>';
        echo '</div>';
        
        echo '</div>'; // Close widget container
    }
    
    public static function render_widget_config() {
        // Configurazioni del widget
        $options = get_option('wsd_widget_settings', array(
            'auto_refresh' => true,
            'refresh_interval' => 300, // 5 minuti
            'show_alerts' => true,
            'compact_mode' => false
        ));
        
        if (isset($_POST['wsd_widget_submit'])) {
            check_admin_referer('wsd_widget_config');
            
            $options['auto_refresh'] = isset($_POST['auto_refresh']);
            $options['refresh_interval'] = intval($_POST['refresh_interval']);
            $options['show_alerts'] = isset($_POST['show_alerts']);
            $options['compact_mode'] = isset($_POST['compact_mode']);
            
            update_option('wsd_widget_settings', $options);
            echo '<div class="notice notice-success"><p>Impostazioni salvate!</p></div>';
        }
        
        echo '<form method="post">';
        wp_nonce_field('wsd_widget_config');
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">Auto-refresh</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="auto_refresh" value="1" ' . checked($options['auto_refresh'], true, false) . '> Aggiorna automaticamente</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">Intervallo (secondi)</th>';
        echo '<td>';
        echo '<select name="refresh_interval">';
        $intervals = array(60 => '1 minuto', 300 => '5 minuti', 900 => '15 minuti', 1800 => '30 minuti');
        foreach ($intervals as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($options['refresh_interval'], $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">Mostra alert</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="show_alerts" value="1" ' . checked($options['show_alerts'], true, false) . '> Mostra notifiche problemi</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">Modalità compatta</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="compact_mode" value="1" ' . checked($options['compact_mode'], true, false) . '> Vista ridotta</label>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="wsd_widget_submit" class="button button-primary" value="Salva Impostazioni">';
        echo '</p>';
        
        echo '</form>';
    }
    
    public static function ajax_widget_refresh() {
        check_ajax_referer('wsd_widget_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $health = WSD_Auto_Repair::get_system_health();
        $recent_issues = WSD_Logger::get_recent_issues(24);
        $performance_score = self::calculate_widget_score($health);
        
        $data = array(
            'performance_score' => $performance_score,
            'memory_usage' => self::format_bytes(memory_get_usage()),
            'active_plugins' => count(get_option('active_plugins', array())),
            'recent_issues' => count($recent_issues),
            'health_status' => $health,
            'last_update' => current_time('H:i:s')
        );
        
        wp_send_json_success($data);
    }
    
    private static function calculate_widget_score($health) {
        $score = 100;
        
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
        
        // Bonus/Malus aggiuntivi
        if (function_exists('opcache_get_status') && opcache_get_status()) {
            $score += 5;
        }
        
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $score += 5;
        }
        
        $recent_issues = WSD_Logger::get_recent_issues(24);
        if (!empty($recent_issues)) {
            $score -= min(count($recent_issues) * 2, 20);
        }
        
        return max(0, min(100, $score));
    }
    
    private static function get_score_class($score) {
        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'warning';
        return 'critical';
    }
    
    private static function get_component_icon($component) {
        $icons = array(
            'action_scheduler' => '⚡',
            'wp_cron' => '⏰',
            'plugins' => '🔌',
            'database' => '🗄️'
        );
        
        return $icons[$component] ?? '🔧';
    }
    
    private static function get_component_name($component) {
        $names = array(
            'action_scheduler' => 'Scheduler',
            'wp_cron' => 'Cron',
            'plugins' => 'Plugin',
            'database' => 'Database'
        );
        
        return $names[$component] ?? ucfirst($component);
    }
    
    private static function format_bytes($size) {
        if ($size == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}