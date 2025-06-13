<?php
/**
 * Plugin Uninstall Script
 * Eseguito quando il plugin viene disinstallato completamente
 */

// Se l'uninstall non è chiamato da WordPress, esce
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Pulizia completa del plugin WSD
 */
class WSD_Uninstaller {
    
    public static function uninstall() {
        global $wpdb;
        
        // Log dell'inizio disinstallazione
        error_log('[WSD] Avvio disinstallazione completa plugin - ' . date('Y-m-d H:i:s'));
        
        // 1. Rimuovi opzioni WordPress
        self::remove_options();
        
        // 2. Rimuovi transient e cache
        self::remove_transients();
        
        // 3. Rimuovi scheduled events
        self::remove_scheduled_events();
        
        // 4. Rimuovi tabelle personalizzate (se esistono)
        self::remove_custom_tables();
        
        // 5. Rimuovi file di log
        self::remove_log_files();
        
        // 6. Rimuovi user meta personalizzate
        self::remove_user_meta();
        
        // 7. Pulizia finale cache
        self::flush_cache();
        
        error_log('[WSD] Disinstallazione completata con successo');
    }
    
    /**
     * Rimuove tutte le opzioni del plugin
     */
    private static function remove_options() {
        $options_to_remove = array(
            // Opzioni principali
            'wsd_general_options',
            'wsd_monitoring_options', 
            'wsd_optimization_options',
            'wsd_advanced_options',
            
            // Dati operativi
            'wsd_performance_log',
            'wsd_slow_queries',
            'wsd_maintenance_logs',
            'wsd_plugin_backup',
            
            // Impostazioni email e scheduler
            'wsd_email_notifications',
            'wsd_scheduler_settings',
            'wsd_widget_settings',
            
            // Dati di stato
            'wsd_last_maintenance',
            'wsd_last_email_alert',
            'wsd_last_alert_hash',
            'wsd_system_health_cache',
            
            // Statistiche
            'wsd_stats_cache',
            'wsd_performance_history'
        );
        
        foreach ($options_to_remove as $option) {
            delete_option($option);
        }
        
        // Rimuovi opzioni con pattern
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wsd_%'");
        
        error_log('[WSD] Opzioni rimosse: ' . count($options_to_remove));
    }
    
    /**
     * Rimuove tutti i transient del plugin
     */
    private static function remove_transients() {
        global $wpdb;
        
        // Rimuovi transient specifici
        $transients = array(
            'wsd_health_check',
            'wsd_performance_cache',
            'wsd_stats_cache',
            'wsd_plugin_analysis',
            'wsd_system_info'
        );
        
        foreach ($transients as $transient) {
            delete_transient($transient);
            delete_site_transient($transient);
        }
        
        // Rimuovi tutti i transient WSD
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wsd_%' OR option_name LIKE '_transient_timeout_wsd_%'");
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_wsd_%' OR meta_key LIKE '_site_transient_timeout_wsd_%'");
        
        error_log('[WSD] Transient rimossi');
    }
    
    /**
     * Rimuove eventi schedulati
     */
    private static function remove_scheduled_events() {
        $scheduled_hooks = array(
            'wsd_auto_maintenance',
            'wsd_weekly_optimization', 
            'wsd_monthly_deep_clean',
            'wsd_daily_health_check',
            'wsd_critical_alert_check'
        );
        
        foreach ($scheduled_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        
        error_log('[WSD] Eventi schedulati rimossi: ' . count($scheduled_hooks));
    }
    
    /**
     * Rimuove tabelle personalizzate (se esistono)
     */
    private static function remove_custom_tables() {
        global $wpdb;
        
        // Per ora WSD non crea tabelle personalizzate
        // Ma prepariamo per future versioni
        $custom_tables = array(
            $wpdb->prefix . 'wsd_performance_log',
            $wpdb->prefix . 'wsd_error_log',
            $wpdb->prefix . 'wsd_optimization_history'
        );
        
        foreach ($custom_tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        error_log('[WSD] Controllo tabelle personalizzate completato');
    }
    
    /**
     * Rimuove file di log
     */
    private static function remove_log_files() {
        $log_files = array(
            WP_CONTENT_DIR . '/wsd-performance.log',
            WP_CONTENT_DIR . '/wsd-errors.log',
            WP_CONTENT_DIR . '/wsd-debug.log',
            WP_CONTENT_DIR . '/wsd-maintenance.log'
        );
        
        $removed_files = 0;
        foreach ($log_files as $file) {
            if (file_exists($file)) {
                if (@unlink($file)) {
                    $removed_files++;
                }
            }
        }
        
        // Rimuovi directory log se vuota
        $log_dir = WP_CONTENT_DIR . '/wsd-logs/';
        if (is_dir($log_dir)) {
            $files = scandir($log_dir);
            if (count($files) <= 2) { // Solo . e ..
                @rmdir($log_dir);
            }
        }
        
        error_log('[WSD] File di log rimossi: ' . $removed_files);
    }
    
    /**
     * Rimuove user meta personalizzate
     */
    private static function remove_user_meta() {
        global $wpdb;
        
        $user_meta_keys = array(
            'wsd_dashboard_preferences',
            'wsd_notification_settings',
            'wsd_last_seen_alerts'
        );
        
        foreach ($user_meta_keys as $meta_key) {
            $wpdb->delete($wpdb->usermeta, array('meta_key' => $meta_key));
        }
        
        // Rimuovi tutte le user meta con pattern WSD
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wsd_%'");
        
        error_log('[WSD] User meta rimosse');
    }
    
    /**
     * Flush finale della cache
     */
    private static function flush_cache() {
        // WordPress object cache
        wp_cache_flush();
        
        // Se disponibile, pulisci cache di terze parti
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
        
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        
        // Rewrite rules
        flush_rewrite_rules();
        
        error_log('[WSD] Cache completamente pulita');
    }
    
    /**
     * Backup dei dati prima della disinstallazione (opzionale)
     */
    public static function create_uninstall_backup() {
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'version' => '1.2.0',
            'options' => array(),
            'settings' => array()
        );
        
        // Salva backup in uploads
        $upload_dir = wp_upload_dir();
        $backup_file = $upload_dir['basedir'] . '/wsd-uninstall-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        // Raccogli dati principali
        $main_options = array(
            'wsd_general_options',
            'wsd_monitoring_options',
            'wsd_optimization_options',
            'wsd_email_notifications',
            'wsd_scheduler_settings'
        );
        
        foreach ($main_options as $option) {
            $backup_data['options'][$option] = get_option($option);
        }
        
        // Scrivi file backup
        $json_data = json_encode($backup_data, JSON_PRETTY_PRINT);
        if (file_put_contents($backup_file, $json_data)) {
            error_log('[WSD] Backup pre-disinstallazione creato: ' . $backup_file);
            return $backup_file;
        }
        
        return false;
    }
    
    /**
     * Funzione principale di disinstallazione con conferma
     */
    public static function confirm_and_uninstall() {
        // Verifica che sia un admin
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti per disinstallare il plugin.');
        }
        
        // Crea backup se richiesto
        if (get_option('wsd_create_backup_on_uninstall', false)) {
            self::create_uninstall_backup();
        }
        
        // Esegui disinstallazione
        self::uninstall();
        
        // Messaggio finale
        error_log('[WSD] Plugin WooCommerce Speed Doctor completamente disinstallato.');
    }
}

// Esegui la disinstallazione
WSD_Uninstaller::uninstall();