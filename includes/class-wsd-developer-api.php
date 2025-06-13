<?php
/**
 * WSD Hooks e API per Sviluppatori
 * 
 * Questo file fornisce una panoramica completa di tutti gli hook, 
 * filtri e API disponibili per estendere WSD.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WSD Developer API
 * 
 * Classe che gestisce gli hook personalizzati e l'API per sviluppatori
 */
class WSD_Developer_API {
    
    public static function init() {
        // Registra hook personalizzati se abilitati
        $advanced_settings = get_option('wsd_advanced_options', array());
        if (isset($advanced_settings['custom_hooks']) && $advanced_settings['custom_hooks']) {
            self::register_developer_hooks();
        }
    }
    
    /**
     * Registra tutti gli hook personalizzati
     */
    public static function register_developer_hooks() {
        // Hook per il monitoraggio performance
        add_action('wsd_before_performance_test', array(__CLASS__, 'trigger_before_performance_test'));
        add_action('wsd_after_performance_test', array(__CLASS__, 'trigger_after_performance_test'));
        
        // Hook per le riparazioni
        add_action('wsd_before_auto_repair', array(__CLASS__, 'trigger_before_repair'));
        add_action('wsd_after_auto_repair', array(__CLASS__, 'trigger_after_repair'));
        
        // Hook per le ottimizzazioni
        add_action('wsd_before_optimization', array(__CLASS__, 'trigger_before_optimization'));
        add_action('wsd_after_optimization', array(__CLASS__, 'trigger_after_optimization'));
        
        // Filtri per personalizzare soglie
        add_filter('wsd_performance_thresholds', array(__CLASS__, 'filter_performance_thresholds'));
        add_filter('wsd_critical_alerts', array(__CLASS__, 'filter_critical_alerts'));
        
        // Hook per dati personalizzati
        add_filter('wsd_system_health_data', array(__CLASS__, 'filter_system_health_data'));
        add_filter('wsd_performance_metrics', array(__CLASS__, 'filter_performance_metrics'));
    }
    
    /**
     * HOOK EXAMPLES E DOCUMENTAZIONE
     */
    
    public static function trigger_before_performance_test($test_params = array()) {
        /**
         * Triggered before a performance test runs
         * 
         * @param array $test_params Test parameters
         */
        do_action('wsd_before_performance_test', $test_params);
    }
    
    public static function trigger_after_performance_test($results = array()) {
        /**
         * Triggered after a performance test completes
         * 
         * @param array $results Test results
         */
        do_action('wsd_after_performance_test', $results);
    }
    
    public static function trigger_before_repair($component = '', $issues = array()) {
        /**
         * Triggered before auto-repair runs
         * 
         * @param string $component Component being repaired
         * @param array $issues Issues detected
         */
        do_action('wsd_before_auto_repair', $component, $issues);
    }
    
    public static function trigger_after_repair($component = '', $results = array()) {
        /**
         * Triggered after auto-repair completes
         * 
         * @param string $component Component repaired
         * @param array $results Repair results
         */
        do_action('wsd_after_auto_repair', $component, $results);
    }
    
    public static function filter_performance_thresholds($thresholds) {
        /**
         * Filter performance thresholds
         * 
         * @param array $thresholds Default thresholds
         * @return array Modified thresholds
         */
        return apply_filters('wsd_performance_thresholds', $thresholds);
    }
    
    public static function filter_system_health_data($health_data) {
        /**
         * Filter system health data
         * 
         * @param array $health_data Health check results
         * @return array Modified health data
         */
        return apply_filters('wsd_system_health_data', $health_data);
    }
    
    /**
     * API METHODS FOR DEVELOPERS
     */
    
    /**
     * Get current performance metrics
     * 
     * @return array Performance metrics
     */
    public static function get_performance_metrics() {
        $metrics = array(
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'query_count' => get_num_queries(),
            'load_time' => self::get_current_load_time(),
            'timestamp' => current_time('mysql')
        );
        
        return apply_filters('wsd_performance_metrics', $metrics);
    }
    
    /**
     * Get system health status
     * 
     * @return array System health
     */
    public static function get_system_health() {
        if (class_exists('WSD_Auto_Repair')) {
            $health = WSD_Auto_Repair::get_system_health();
            return apply_filters('wsd_system_health_data', $health);
        }
        
        return array();
    }
    
    /**
     * Run custom performance test
     * 
     * @param array $test_params Test parameters
     * @return array Test results
     */
    public static function run_custom_performance_test($test_params = array()) {
        do_action('wsd_before_performance_test', $test_params);
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        $start_queries = get_num_queries();
        
        // Esegui test personalizzato
        $custom_results = apply_filters('wsd_custom_performance_test', array(), $test_params);
        
        $end_time = microtime(true);
        $results = array(
            'execution_time' => ($end_time - $start_time) * 1000,
            'memory_used' => memory_get_usage() - $start_memory,
            'queries_executed' => get_num_queries() - $start_queries,
            'custom_results' => $custom_results,
            'timestamp' => current_time('mysql')
        );
        
        do_action('wsd_after_performance_test', $results);
        
        return $results;
    }
    
    /**
     * Add custom health check
     * 
     * @param string $component Component name
     * @param callable $callback Health check callback
     */
    public static function add_custom_health_check($component, $callback) {
        add_filter('wsd_system_health_data', function($health_data) use ($component, $callback) {
            if (is_callable($callback)) {
                $health_data[$component] = call_user_func($callback);
            }
            return $health_data;
        });
    }
    
    /**
     * Log custom performance data
     * 
     * @param array $data Performance data to log
     * @param string $category Data category
     */
    public static function log_performance_data($data, $category = 'custom') {
        if (class_exists('WSD_Logger')) {
            $log_entry = array_merge($data, array(
                'category' => $category,
                'timestamp' => current_time('mysql'),
                'source' => 'custom_api'
            ));
            
            WSD_Logger::log_performance_issue($log_entry);
        }
    }
    
    /**
     * Get performance history
     * 
     * @param int $hours Hours to look back
     * @param string $category Data category filter
     * @return array Performance history
     */
    public static function get_performance_history($hours = 24, $category = null) {
        if (class_exists('WSD_Logger')) {
            $issues = WSD_Logger::get_recent_issues($hours);
            
            if ($category) {
                $issues = array_filter($issues, function($issue) use ($category) {
                    return isset($issue['category']) && $issue['category'] === $category;
                });
            }
            
            return $issues;
        }
        
        return array();
    }
    
    /**
     * Trigger manual optimization
     * 
     * @param string $type Optimization type
     * @param array $params Optimization parameters
     * @return array Optimization results
     */
    public static function trigger_optimization($type, $params = array()) {
        do_action('wsd_before_optimization', $type, $params);
        
        $results = array(
            'type' => $type,
            'timestamp' => current_time('mysql'),
            'success' => false,
            'message' => 'Optimization not implemented',
            'details' => array()
        );
        
        // Consenti ottimizzazioni personalizzate
        $results = apply_filters('wsd_custom_optimization', $results, $type, $params);
        
        do_action('wsd_after_optimization', $type, $results);
        
        return $results;
    }
    
    /**
     * Utility functions
     */
    
    private static function get_current_load_time() {
        if (defined('WSD_START_TIME')) {
            return (microtime(true) - WSD_START_TIME) * 1000;
        }
        return 0;
    }
    
    /**
     * Get WSD version
     * 
     * @return string Version number
     */
    public static function get_version() {
        return defined('WSD_VERSION') ? WSD_VERSION : '1.2.0';
    }
    
    /**
     * Check if WSD is enabled
     * 
     * @return bool
     */
    public static function is_enabled() {
        $settings = get_option('wsd_general_options', array());
        return isset($settings['enabled']) ? $settings['enabled'] : true;
    }
    
    /**
     * Get WSD settings
     * 
     * @param string $group Settings group
     * @return array Settings
     */
    public static function get_settings($group = 'general') {
        $option_map = array(
            'general' => 'wsd_general_options',
            'monitoring' => 'wsd_monitoring_options',
            'optimization' => 'wsd_optimization_options',
            'advanced' => 'wsd_advanced_options',
            'email' => 'wsd_email_notifications',
            'scheduler' => 'wsd_scheduler_settings'
        );
        
        $option_name = isset($option_map[$group]) ? $option_map[$group] : $option_map['general'];
        return get_option($option_name, array());
    }
    
    /**
     * Update WSD settings
     * 
     * @param string $group Settings group
     * @param array $settings New settings
     * @return bool Success
     */
    public static function update_settings($group, $settings) {
        $option_map = array(
            'general' => 'wsd_general_options',
            'monitoring' => 'wsd_monitoring_options',
            'optimization' => 'wsd_optimization_options',
            'advanced' => 'wsd_advanced_options',
            'email' => 'wsd_email_notifications',
            'scheduler' => 'wsd_scheduler_settings'
        );
        
        $option_name = isset($option_map[$group]) ? $option_map[$group] : $option_map['general'];
        return update_option($option_name, $settings);
    }
}

/**
 * HOOK DOCUMENTATION
 * 
 * Lista completa di tutti gli hook disponibili in WSD
 */

/*
 * ACTION HOOKS:
 * 
 * wsd_before_performance_test - Triggered before performance test
 * wsd_after_performance_test - Triggered after performance test
 * wsd_before_auto_repair - Triggered before auto-repair
 * wsd_after_auto_repair - Triggered after auto-repair
 * wsd_before_optimization - Triggered before optimization
 * wsd_after_optimization - Triggered after optimization
 * wsd_daily_maintenance - Daily maintenance hook
 * wsd_weekly_optimization - Weekly optimization hook
 * wsd_monthly_deep_clean - Monthly deep clean hook
 * wsd_critical_alert - Critical alert hook
 * wsd_performance_threshold_exceeded - Performance threshold exceeded
 * 
 * FILTER HOOKS:
 * 
 * wsd_performance_thresholds - Modify performance thresholds
 * wsd_system_health_data - Modify system health data
 * wsd_performance_metrics - Modify performance metrics
 * wsd_critical_alerts - Modify critical alerts
 * wsd_optimization_results - Modify optimization results
 * wsd_email_notification_content - Modify email content
 * wsd_dashboard_widget_data - Modify widget data
 * wsd_custom_performance_test - Add custom performance tests
 * wsd_custom_optimization - Add custom optimizations
 * wsd_repair_components - Add custom repair components
 */

/**
 * USAGE EXAMPLES:
 */

/*
// Example 1: Add custom health check
WSD_Developer_API::add_custom_health_check('my_custom_check', function() {
    return array(
        'status' => 'good',
        'message' => 'Custom check passed',
        'issues' => array()
    );
});

// Example 2: Hook into performance test
add_action('wsd_after_performance_test', function($results) {
    if ($results['execution_time'] > 2000) {
        // Send custom alert
        wp_mail('admin@example.com', 'Slow Performance Alert', 'Performance test took ' . $results['execution_time'] . 'ms');
    }
});

// Example 3: Modify performance thresholds
add_filter('wsd_performance_thresholds', function($thresholds) {
    $thresholds['critical_time'] = 5000; // 5 seconds instead of default
    $thresholds['warning_time'] = 2500; // 2.5 seconds instead of default
    return $thresholds;
});

// Example 4: Add custom optimization
add_filter('wsd_custom_optimization', function($results, $type, $params) {
    if ($type === 'my_optimization') {
        // Run custom optimization
        $results['success'] = true;
        $results['message'] = 'Custom optimization completed';
        $results['details'] = array('custom_data' => 'value');
    }
    return $results;
}, 10, 3);

// Example 5: Log custom performance data
WSD_Developer_API::log_performance_data(array(
    'custom_metric' => 'value',
    'load_time' => 1500,
    'memory_used' => '64MB'
), 'my_plugin');
*/