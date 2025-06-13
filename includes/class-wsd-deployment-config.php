<?php
/**
 * WSD Deployment Configuration
 * 
 * Configurazioni per diversi ambienti di deployment
 * e esempi di utilizzo avanzato
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configurazioni Environment-Specific
 */
class WSD_Deployment_Config {
    
    /**
     * Configurazione per ambiente di sviluppo
     */
    public static function development_config() {
        return array(
            'general' => array(
                'enabled' => true,
                'debug_mode' => true,
                'performance_monitoring' => true,
                'frontend_monitoring' => true
            ),
            'monitoring' => array(
                'log_slow_queries' => true,
                'slow_query_threshold' => 100, // Più sensibile in dev
                'log_memory_usage' => true,
                'memory_threshold' => 64,
                'log_retention_days' => 7,
                'real_time_monitoring' => true,
                'monitoring_interval' => 30 // Controlli più frequenti
            ),
            'optimization' => array(
                'auto_cleanup_enabled' => false, // Non interferire con debug
                'auto_optimize_db' => false,
                'cache_optimization' => true,
                'image_optimization_hints' => true
            ),
            'email' => array(
                'enabled' => false, // Non inviare email in dev
                'critical_alerts' => false,
                'daily_reports' => false
            ),
            'scheduler' => array(
                'auto_maintenance' => false // Controllo manuale in dev
            ),
            'advanced' => array(
                'custom_performance_thresholds' => true,
                'critical_threshold' => 1000, // Più sensibile
                'warning_threshold' => 500,
                'enable_api_monitoring' => true,
                'custom_hooks' => true
            )
        );
    }
    
    /**
     * Configurazione per ambiente di staging
     */
    public static function staging_config() {
        return array(
            'general' => array(
                'enabled' => true,
                'debug_mode' => false,
                'performance_monitoring' => true,
                'frontend_monitoring' => false
            ),
            'monitoring' => array(
                'log_slow_queries' => true,
                'slow_query_threshold' => 300,
                'log_memory_usage' => true,
                'memory_threshold' => 128,
                'log_retention_days' => 14,
                'real_time_monitoring' => true,
                'monitoring_interval' => 60
            ),
            'optimization' => array(
                'auto_cleanup_enabled' => true,
                'cleanup_frequency' => 'daily',
                'auto_optimize_db' => false,
                'cache_optimization' => true
            ),
            'email' => array(
                'enabled' => true,
                'critical_alerts' => true,
                'daily_reports' => false,
                'alert_threshold' => 'critical'
            ),
            'scheduler' => array(
                'auto_maintenance' => true
            ),
            'advanced' => array(
                'custom_performance_thresholds' => true,
                'critical_threshold' => 2000,
                'warning_threshold' => 1000,
                'enable_api_monitoring' => false
            )
        );
    }
    
    /**
     * Configurazione per ambiente di produzione
     */
    public static function production_config() {
        return array(
            'general' => array(
                'enabled' => true,
                'debug_mode' => false,
                'performance_monitoring' => true,
                'frontend_monitoring' => false
            ),
            'monitoring' => array(
                'log_slow_queries' => true,
                'slow_query_threshold' => 500,
                'log_memory_usage' => true,
                'memory_threshold' => 256,
                'log_retention_days' => 30,
                'real_time_monitoring' => false, // Meno intensivo
                'monitoring_interval' => 300 // 5 minuti
            ),
            'optimization' => array(
                'auto_cleanup_enabled' => true,
                'cleanup_frequency' => 'daily',
                'auto_optimize_db' => true,
                'auto_plugin_optimization' => false, // Controllo manuale
                'cache_optimization' => true
            ),
            'email' => array(
                'enabled' => true,
                'critical_alerts' => true,
                'daily_reports' => true,
                'alert_threshold' => 'critical',
                'cooldown_hours' => 2
            ),
            'scheduler' => array(
                'auto_maintenance' => true,
                'weekly_optimization' => true,
                'monthly_deep_clean' => true
            ),
            'advanced' => array(
                'custom_performance_thresholds' => true,
                'critical_threshold' => 3000,
                'warning_threshold' => 1500,
                'memory_critical_threshold' => 512,
                'query_critical_threshold' => 100,
                'enable_api_monitoring' => false,
                'custom_hooks' => false
            )
        );
    }
    
    /**
     * Applica configurazione per ambiente specifico
     */
    public static function apply_environment_config($environment = 'production') {
        $config_method = $environment . '_config';
        
        if (!method_exists(__CLASS__, $config_method)) {
            return false;
        }
        
        $config = self::$config_method();
        
        foreach ($config as $group => $settings) {
            $option_map = array(
                'general' => 'wsd_general_options',
                'monitoring' => 'wsd_monitoring_options',
                'optimization' => 'wsd_optimization_options',
                'email' => 'wsd_email_notifications',
                'scheduler' => 'wsd_scheduler_settings',
                'advanced' => 'wsd_advanced_options'
            );
            
            if (isset($option_map[$group])) {
                update_option($option_map[$group], $settings);
            }
        }
        
        // Salva ambiente applicato
        update_option('wsd_current_environment', $environment);
        update_option('wsd_config_applied_date', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Configurazione High-Traffic per siti ad alto traffico
     */
    public static function high_traffic_config() {
        return array(
            'general' => array(
                'enabled' => true,
                'debug_mode' => false,
                'performance_monitoring' => true,
                'frontend_monitoring' => false
            ),
            'monitoring' => array(
                'log_slow_queries' => true,
                'slow_query_threshold' => 1000, // Meno sensibile
                'log_memory_usage' => false, // Riduce overhead
                'memory_threshold' => 512,
                'log_retention_days' => 7, // Retention breve
                'max_log_entries' => 500,
                'real_time_monitoring' => false,
                'monitoring_interval' => 600 // 10 minuti
            ),
            'optimization' => array(
                'auto_cleanup_enabled' => true,
                'cleanup_frequency' => 'daily',
                'auto_optimize_db' => true,
                'auto_plugin_optimization' => true
            ),
            'email' => array(
                'enabled' => true,
                'critical_alerts' => true,
                'daily_reports' => false,
                'alert_threshold' => 'critical',
                'cooldown_hours' => 6 // Meno email
            ),
            'scheduler' => array(
                'auto_maintenance' => true,
                'weekly_optimization' => true,
                'monthly_deep_clean' => true
            ),
            'advanced' => array(
                'custom_performance_thresholds' => true,
                'critical_threshold' => 5000, // Soglie più alte
                'warning_threshold' => 3000,
                'memory_critical_threshold' => 1024,
                'query_critical_threshold' => 200
            )
        );
    }
}

/**
 * Esempi di Utilizzo Avanzato
 */
class WSD_Usage_Examples {
    
    /**
     * Esempio 1: Monitoraggio Custom per WooCommerce
     */
    public static function woocommerce_monitoring_example() {
        // Hook specifico per WooCommerce
        add_action('woocommerce_checkout_order_processed', function($order_id) {
            $start_time = microtime(true);
            
            // Il processo di checkout continua...
            
            $checkout_time = (microtime(true) - $start_time) * 1000;
            
            // Log se il checkout è lento
            if ($checkout_time > 2000) {
                WSD_Developer_API::log_performance_data(array(
                    'type' => 'slow_checkout',
                    'order_id' => $order_id,
                    'checkout_time' => $checkout_time,
                    'severity' => 'warning'
                ), 'woocommerce');
            }
        });
        
        // Monitoraggio prodotti pesanti
        add_action('woocommerce_single_product_summary', function() {
            global $product;
            
            if ($product && $product->get_gallery_image_ids()) {
                $gallery_count = count($product->get_gallery_image_ids());
                
                if ($gallery_count > 20) {
                    WSD_Developer_API::log_performance_data(array(
                        'type' => 'heavy_product_gallery',
                        'product_id' => $product->get_id(),
                        'gallery_count' => $gallery_count,
                        'severity' => 'info'
                    ), 'woocommerce');
                }
            }
        });
    }
    
    /**
     * Esempio 2: Integrazione con CDN
     */
    public static function cdn_integration_example() {
        // Monitora caricamento asset da CDN
        add_action('wp_enqueue_scripts', function() {
            $start_time = microtime(true);
            
            // Simula controllo CDN
            $cdn_response_time = self::check_cdn_performance();
            
            if ($cdn_response_time > 1000) {
                WSD_Developer_API::log_performance_data(array(
                    'type' => 'slow_cdn',
                    'response_time' => $cdn_response_time,
                    'severity' => 'warning'
                ), 'cdn');
            }
        });
    }
    
    /**
     * Esempio 3: Monitoraggio API di terze parti
     */
    public static function third_party_api_monitoring() {
        // Wrapper per chiamate API
        function wsd_api_call($url, $args = array()) {
            $start_time = microtime(true);
            
            $response = wp_remote_get($url, $args);
            
            $api_time = (microtime(true) - $start_time) * 1000;
            
            // Log chiamate API lente
            if ($api_time > 3000) {
                WSD_Developer_API::log_performance_data(array(
                    'type' => 'slow_api_call',
                    'url' => $url,
                    'response_time' => $api_time,
                    'response_code' => wp_remote_retrieve_response_code($response),
                    'severity' => 'warning'
                ), 'api');
            }
            
            return $response;
        }
    }
    
    /**
     * Esempio 4: Ottimizzazione Database Custom
     */
    public static function custom_database_optimization() {
        // Aggiungi ottimizzazione personalizzata
        add_filter('wsd_custom_optimization', function($results, $type, $params) {
            if ($type === 'cleanup_custom_logs') {
                global $wpdb;
                
                // Pulisci log personalizzati più vecchi di 30 giorni
                $deleted = $wpdb->query("
                    DELETE FROM {$wpdb->prefix}my_custom_logs 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                
                $results['success'] = true;
                $results['message'] = "Puliti {$deleted} log personalizzati";
                $results['details'] = array('deleted_rows' => $deleted);
            }
            
            return $results;
        }, 10, 3);
        
        // Triggera l'ottimizzazione
        // WSD_Developer_API::trigger_optimization('cleanup_custom_logs');
    }
    
    /**
     * Esempio 5: Alert Personalizzati
     */
    public static function custom_alerts_example() {
        // Alert per uso memoria eccessivo
        add_action('wsd_after_performance_test', function($results) {
            $memory_mb = $results['memory_used'] / (1024 * 1024);
            
            if ($memory_mb > 100) {
                // Invia notifica Slack, Discord, etc.
                self::send_slack_alert("🚨 Alto uso memoria: {$memory_mb}MB");
            }
        });
        
        // Alert per query database eccessive
        add_filter('wsd_performance_metrics', function($metrics) {
            if ($metrics['query_count'] > 50) {
                self::send_teams_alert("⚠️ Troppe query DB: {$metrics['query_count']}");
            }
            
            return $metrics;
        });
    }
    
    /**
     * Esempio 6: Integrazione con servizi esterni
     */
    public static function external_services_integration() {
        // Invia metriche a servizi di monitoring esterni
        add_action('wsd_after_performance_test', function($results) {
            // New Relic
            if (function_exists('newrelic_custom_metric')) {
                newrelic_custom_metric('Custom/WSD/LoadTime', $results['execution_time']);
                newrelic_custom_metric('Custom/WSD/MemoryUsage', $results['memory_used']);
            }
            
            // DataDog
            if (class_exists('DataDogStatsD')) {
                $statsd = new DataDogStatsD();
                $statsd->timing('wsd.load_time', $results['execution_time']);
                $statsd->gauge('wsd.memory_usage', $results['memory_used']);
            }
            
            // Google Analytics Custom Events
            if (function_exists('gtag')) {
                if ($results['execution_time'] > 2000) {
                    echo "<script>gtag('event', 'slow_page_load', {'custom_parameter': '{$results['execution_time']}'});</script>";
                }
            }
        });
    }
    
    // Utility functions per esempi
    private static function check_cdn_performance() {
        // Simula controllo CDN
        return rand(200, 1500);
    }
    
    private static function send_slack_alert($message) {
        // Implementazione invio Slack
        // wp_remote_post('https://hooks.slack.com/...', array('body' => json_encode(array('text' => $message))));
    }
    
    private static function send_teams_alert($message) {
        // Implementazione invio Microsoft Teams
    }
}

/**
 * Script di Deploy Automatico
 */
class WSD_Auto_Deploy {
    
    /**
     * Deploy automatico basato su ambiente
     */
    public static function auto_configure() {
        // Rileva ambiente
        $environment = self::detect_environment();
        
        // Applica configurazione appropriata
        WSD_Deployment_Config::apply_environment_config($environment);
        
        // Log deploy
        error_log("[WSD] Auto-configured for environment: {$environment}");
        
        return $environment;
    }
    
    /**
     * Rileva ambiente di deployment
     */
    private static function detect_environment() {
        // Controlla domain
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        
        if (strpos($domain, 'localhost') !== false || strpos($domain, '.dev') !== false) {
            return 'development';
        }
        
        if (strpos($domain, 'staging') !== false || strpos($domain, '.test') !== false) {
            return 'staging';
        }
        
        // Controlla constants
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }
        
        if (defined('WSD_ENVIRONMENT')) {
            return WSD_ENVIRONMENT;
        }
        
        // Default production
        return 'production';
    }
    
    /**
     * Verifica prerequisiti
     */
    public static function check_prerequisites() {
        $checks = array(
            'php_version' => version_compare(PHP_VERSION, '7.4', '>='),
            'wordpress_version' => version_compare(get_bloginfo('version'), '5.0', '>='),
            'memory_limit' => intval(ini_get('memory_limit')) >= 128,
            'max_execution_time' => intval(ini_get('max_execution_time')) >= 30
        );
        
        $passed = array_filter($checks);
        
        if (count($passed) !== count($checks)) {
            $failed = array_diff_key($checks, $passed);
            error_log('[WSD] Prerequisites check failed: ' . implode(', ', array_keys($failed)));
            return false;
        }
        
        return true;
    }
}

// Auto-configure on activation se richiesto
if (defined('WSD_AUTO_CONFIGURE') && WSD_AUTO_CONFIGURE) {
    add_action('plugins_loaded', array('WSD_Auto_Deploy', 'auto_configure'), 5);
}