<?php
/**
 * Classe per il logging delle performance e query lente
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Logger {
    
    private static $slow_queries = array();
    private static $log_file = '';
    
    public static function init() {
        // Imposta file di log
        self::$log_file = WP_CONTENT_DIR . '/wsd-performance.log';
        
        // Hook per monitorare le query
        add_filter('query', array(__CLASS__, 'monitor_query_start'));
        add_action('shutdown', array(__CLASS__, 'monitor_query_end'));
        
        // Setup logging
        if (!defined('WP_DEBUG_LOG')) {
            ini_set('log_errors', 1);
            ini_set('error_log', WP_CONTENT_DIR . '/wsd-errors.log');
        }
        
        // Hook per logging automatico
        add_action('wp_footer', array(__CLASS__, 'log_page_performance'));
        add_action('admin_footer', array(__CLASS__, 'log_page_performance'));
    }
    
    public static function monitor_query_start($query) {
        global $wsd_query_start_time;
        $wsd_query_start_time = microtime(true);
        return $query;
    }
    
    public static function monitor_query_end() {
        global $wpdb, $wsd_query_start_time;
        
        if (isset($wsd_query_start_time) && !empty($wpdb->last_query)) {
            $execution_time = (microtime(true) - $wsd_query_start_time) * 1000;
            
            // Log query lente (>100ms di default)
            $slow_threshold = get_option('wsd_slow_query_threshold', 100);
            
            if ($execution_time > $slow_threshold) {
                self::log_slow_query($wpdb->last_query, $execution_time);
                
                // Query molto lente (>500ms) hanno priorità
                if ($execution_time > 500) {
                    self::log_critical_query($wpdb->last_query, $execution_time);
                }
            }
        }
    }
    
    public static function log_performance_issue($data) {
        $performance_log = get_option('wsd_performance_log', array());
        
        // Aggiungi timestamp se non presente
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = current_time('mysql');
        }
        
        // Aggiungi severity se non presente
        if (!isset($data['severity'])) {
            $data['severity'] = self::determine_severity($data);
        }
        
        // Aggiungi URL se non presente
        if (!isset($data['url'])) {
            $data['url'] = $_SERVER['REQUEST_URI'] ?? '/';
        }
        
        // Aggiungi user agent per context
        if (!isset($data['user_agent']) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $data['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
        }
        
        $performance_log[] = $data;
        
        // Mantieni solo gli ultimi 100 log per performance
        if (count($performance_log) > 100) {
            $performance_log = array_slice($performance_log, -100);
        }
        
        update_option('wsd_performance_log', $performance_log);
        
        // Log anche su file per analisi esterne
        self::write_to_log_file($data);
        
        // Se è critico, logga anche nel log errori WordPress
        if (isset($data['severity']) && $data['severity'] === 'critical') {
            error_log('[WSD CRITICAL] ' . self::format_log_message($data));
        }
    }
    
    private static function log_slow_query($query, $time) {
        $slow_queries = get_option('wsd_slow_queries', array());
        
        $query_data = array(
            'query' => self::sanitize_query($query),
            'execution_time' => round($time, 2),
            'timestamp' => current_time('mysql'),
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'query_hash' => md5($query) // Per identificare query duplicate
        );
        
        $slow_queries[] = $query_data;
        
        // Mantieni solo le ultime 50 query lente
        if (count($slow_queries) > 50) {
            $slow_queries = array_slice($slow_queries, -50);
        }
        
        update_option('wsd_slow_queries', $slow_queries);
    }
    
    private static function log_critical_query($query, $time) {
        $critical_data = array(
            'type' => 'critical_query',
            'query' => self::sanitize_query($query),
            'execution_time' => $time,
            'severity' => 'critical',
            'timestamp' => current_time('mysql')
        );
        
        self::log_performance_issue($critical_data);
    }
    
    public static function log_page_performance() {
        // Non loggare se siamo in AJAX o cron
        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        // Calcola metriche di performance
        $load_time = 0;
        if (defined('WSD_START_TIME')) {
            $load_time = (microtime(true) - WSD_START_TIME) * 1000;
        }
        
        $memory_usage = memory_get_usage();
        $peak_memory = memory_get_peak_usage();
        $query_count = get_num_queries();
        
        // Log solo se ci sono problemi di performance
        $should_log = false;
        $severity = 'info';
        
        if ($load_time > 3000) {
            $should_log = true;
            $severity = 'critical';
        } elseif ($load_time > 1500) {
            $should_log = true;
            $severity = 'warning';
        }
        
        if ($memory_usage > 100 * 1024 * 1024) { // 100MB
            $should_log = true;
            if ($severity === 'info') $severity = 'warning';
        }
        
        if ($query_count > 50) {
            $should_log = true;
            if ($severity === 'info') $severity = 'warning';
        }
        
        // Log solo se necessario
        if ($should_log) {
            $performance_data = array(
                'type' => 'page_load',
                'load_time' => round($load_time, 2),
                'memory_used' => self::format_bytes($memory_usage),
                'peak_memory' => self::format_bytes($peak_memory),
                'query_count' => $query_count,
                'severity' => $severity,
                'is_admin' => is_admin(),
                'is_mobile' => wp_is_mobile(),
                'url' => $_SERVER['REQUEST_URI'] ?? '/'
            );
            
            self::log_performance_issue($performance_data);
        }
    }
    
    public static function get_recent_issues($hours = 24) {
        $performance_log = get_option('wsd_performance_log', array());
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return array_filter($performance_log, function($issue) use ($cutoff_time) {
            return isset($issue['timestamp']) && $issue['timestamp'] > $cutoff_time;
        });
    }
    
    public static function get_slow_queries($limit = 10) {
        $slow_queries = get_option('wsd_slow_queries', array());
        return array_slice(array_reverse($slow_queries), 0, $limit);
    }
    
    public static function get_performance_stats($days = 7) {
        $recent_issues = self::get_recent_issues($days * 24);
        
        $stats = array(
            'total_issues' => count($recent_issues),
            'critical_issues' => 0,
            'warning_issues' => 0,
            'info_issues' => 0,
            'avg_load_time' => 0,
            'max_load_time' => 0,
            'avg_memory' => 0,
            'avg_queries' => 0,
            'most_problematic_urls' => array()
        );
        
        $load_times = array();
        $memory_values = array();
        $query_counts = array();
        $url_counts = array();
        
        foreach ($recent_issues as $issue) {
            // Conta per severity
            if (isset($issue['severity'])) {
                $stats[$issue['severity'] . '_issues']++;
            }
            
            // Raccoglie metriche per calcoli
            if (isset($issue['load_time'])) {
                $load_times[] = $issue['load_time'];
                $stats['max_load_time'] = max($stats['max_load_time'], $issue['load_time']);
            }
            
            if (isset($issue['memory_used'])) {
                $memory_values[] = self::parse_memory_value($issue['memory_used']);
            }
            
            if (isset($issue['query_count'])) {
                $query_counts[] = $issue['query_count'];
            }
            
            // Conta URL problematiche
            if (isset($issue['url'])) {
                $url_counts[$issue['url']] = ($url_counts[$issue['url']] ?? 0) + 1;
            }
        }
        
        // Calcola medie
        if (!empty($load_times)) {
            $stats['avg_load_time'] = round(array_sum($load_times) / count($load_times), 2);
        }
        
        if (!empty($memory_values)) {
            $stats['avg_memory'] = self::format_bytes(array_sum($memory_values) / count($memory_values));
        }
        
        if (!empty($query_counts)) {
            $stats['avg_queries'] = round(array_sum($query_counts) / count($query_counts), 1);
        }
        
        // Top 5 URL problematiche
        arsort($url_counts);
        $stats['most_problematic_urls'] = array_slice($url_counts, 0, 5, true);
        
        return $stats;
    }
    
    public static function display_performance_logs() {
        echo '<div class="wsd-section" id="wsd-performance-logs">';
        echo '<h2><span class="dashicons dashicons-chart-line"></span> ' . esc_html__('Log Performance (Ultime 24h)', 'wc-speed-doctor') . '</h2>';
        
        $recent_issues = self::get_recent_issues();
        $slow_queries = self::get_slow_queries();
        $stats = self::get_performance_stats();
        
        // Mostra statistiche riassuntive
        if (!empty($recent_issues)) {
            echo '<div class="wsd-performance-summary">';
            echo '<h3>' . esc_html__('Riassunto Performance', 'wc-speed-doctor') . '</h3>';
            echo '<div class="wsd-stats-grid">';
            
            echo '<div class="wsd-stat-box">';
            echo '<strong>Problemi Totali</strong><br>';
            echo '<span class="wsd-stat-value">' . $stats['total_issues'] . '</span>';
            echo '</div>';
            
            echo '<div class="wsd-stat-box">';
            echo '<strong>Critici</strong><br>';
            echo '<span class="wsd-stat-value error">' . $stats['critical_issues'] . '</span>';
            echo '</div>';
            
            echo '<div class="wsd-stat-box">';
            echo '<strong>Tempo Medio</strong><br>';
            echo '<span class="wsd-stat-value">' . $stats['avg_load_time'] . 'ms</span>';
            echo '</div>';
            
            echo '<div class="wsd-stat-box">';
            echo '<strong>Query Medie</strong><br>';
            echo '<span class="wsd-stat-value">' . $stats['avg_queries'] . '</span>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
        }
        
        // Mostra problemi recenti
        if (!empty($recent_issues)) {
            echo '<h3>' . esc_html__('Problemi di Performance Recenti', 'wc-speed-doctor') . '</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Timestamp', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Tipo', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('URL', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Tempo (ms)', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Query DB', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Memoria', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Severity', 'wc-speed-doctor') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach (array_reverse(array_slice($recent_issues, -20)) as $issue) {
                echo '<tr>';
                echo '<td>' . esc_html($issue['timestamp']) . '</td>';
                echo '<td>' . esc_html($issue['type'] ?? 'N/A') . '</td>';
                echo '<td><code>' . esc_html(self::truncate_url($issue['url'] ?? '')) . '</code></td>';
                echo '<td><strong style="color: ' . self::get_severity_color($issue['load_time'] ?? 0) . ';">' . esc_html($issue['load_time'] ?? '0') . '</strong></td>';
                echo '<td>' . esc_html($issue['query_count'] ?? '0') . '</td>';
                echo '<td>' . esc_html($issue['memory_used'] ?? 'N/A') . '</td>';
                echo '<td><span class="wsd-severity-badge ' . esc_attr($issue['severity'] ?? 'info') . '">' . esc_html($issue['severity'] ?? 'info') . '</span></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Mostra query lente
        if (!empty($slow_queries)) {
            echo '<h3>' . esc_html__('Query Database Lente', 'wc-speed-doctor') . '</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Timestamp', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Tempo (ms)', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Query', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('URL', 'wc-speed-doctor') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($slow_queries as $query) {
                echo '<tr>';
                echo '<td>' . esc_html($query['timestamp']) . '</td>';
                echo '<td><strong style="color: ' . self::get_severity_color($query['execution_time']) . ';">' . esc_html($query['execution_time']) . '</strong></td>';
                echo '<td><code style="font-size: 11px;">' . esc_html(self::truncate_query($query['query'])) . '</code></td>';
                echo '<td><code>' . esc_html(self::truncate_url($query['url'] ?? '')) . '</code></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Mostra URL problematiche
        if (!empty($stats['most_problematic_urls'])) {
            echo '<h3>' . esc_html__('URL Più Problematiche', 'wc-speed-doctor') . '</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('URL', 'wc-speed-doctor') . '</th>';
            echo '<th>' . esc_html__('Problemi', 'wc-speed-doctor') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($stats['most_problematic_urls'] as $url => $count) {
                echo '<tr>';
                echo '<td><code>' . esc_html($url) . '</code></td>';
                echo '<td><strong>' . esc_html($count) . '</strong></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        if (empty($recent_issues) && empty($slow_queries)) {
            echo '<p class="wsd-no-issues"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Nessun problema di performance rilevato nelle ultime 24 ore. Ottimo lavoro!', 'wc-speed-doctor') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Utility functions
     */
    
    private static function sanitize_query($query) {
        // Tronca query molto lunghe
        if (strlen($query) > 500) {
            $query = substr($query, 0, 500) . '...';
        }
        
        // Rimuovi dati sensibili
        $query = preg_replace('/\'[^\']*\'/i', "'***'", $query);
        
        return $query;
    }
    
    private static function determine_severity($data) {
        // Determina severity basata sui dati
        if (isset($data['load_time']) && $data['load_time'] > 3000) {
            return 'critical';
        }
        
        if (isset($data['execution_time']) && $data['execution_time'] > 1000) {
            return 'critical';
        }
        
        if (isset($data['query_count']) && $data['query_count'] > 100) {
            return 'warning';
        }
        
        return 'info';
    }
    
    private static function format_bytes($size) {
        if ($size == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    private static function parse_memory_value($memory_string) {
        // Converte stringa memoria in bytes
        $units = array('B' => 1, 'KB' => 1024, 'MB' => 1024*1024, 'GB' => 1024*1024*1024);
        
        if (preg_match('/^([\d.]+)\s*([A-Z]{1,2})$/', $memory_string, $matches)) {
            $value = floatval($matches[1]);
            $unit = $matches[2];
            return $value * ($units[$unit] ?? 1);
        }
        
        return 0;
    }
    
    private static function truncate_url($url, $length = 50) {
        return strlen($url) > $length ? substr($url, 0, $length) . '...' : $url;
    }
    
    private static function truncate_query($query, $length = 100) {
        return strlen($query) > $length ? substr($query, 0, $length) . '...' : $query;
    }
    
    private static function get_severity_color($value) {
        if ($value > 3000) return '#dc3232';
        if ($value > 1500) return '#ffb900';
        return '#46b450';
    }
    
    private static function write_to_log_file($data) {
        $log_message = self::format_log_message($data);
        error_log($log_message, 3, self::$log_file);
    }
    
    private static function format_log_message($data) {
        $timestamp = $data['timestamp'] ?? current_time('mysql');
        $type = $data['type'] ?? 'performance';
        $severity = $data['severity'] ?? 'info';
        $url = $data['url'] ?? '/';
        
        $message = "[{$timestamp}] WSD {$severity}: {$type}";
        
        if (isset($data['load_time'])) {
            $message .= " | Load: {$data['load_time']}ms";
        }
        
        if (isset($data['memory_used'])) {
            $message .= " | Memory: {$data['memory_used']}";
        }
        
        if (isset($data['query_count'])) {
            $message .= " | Queries: {$data['query_count']}";
        }
        
        $message .= " | URL: {$url}";
        
        return $message;
    }
    
    /**
     * Pulizia automatica log
     */
    public static function cleanup_old_logs() {
        $retention_days = get_option('wsd_log_retention_days', 30);
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Pulisci performance log
        $performance_log = get_option('wsd_performance_log', array());
        $performance_log = array_filter($performance_log, function($issue) use ($cutoff_time) {
            return isset($issue['timestamp']) && $issue['timestamp'] > $cutoff_time;
        });
        update_option('wsd_performance_log', array_values($performance_log));
        
        // Pulisci slow queries
        $slow_queries = get_option('wsd_slow_queries', array());
        $slow_queries = array_filter($slow_queries, function($query) use ($cutoff_time) {
            return isset($query['timestamp']) && $query['timestamp'] > $cutoff_time;
        });
        update_option('wsd_slow_queries', array_values($slow_queries));
        
        // Pulisci file di log se troppo grande (>10MB)
        if (file_exists(self::$log_file) && filesize(self::$log_file) > 10 * 1024 * 1024) {
            // Mantieni solo le ultime 1000 righe
            $lines = file(self::$log_file);
            $lines = array_slice($lines, -1000);
            file_put_contents(self::$log_file, implode('', $lines));
        }
    }
}