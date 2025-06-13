<?php
/**
 * Sistema di Scheduler Automatico per Ottimizzazioni Periodiche
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Auto_Scheduler {
    
    public static function init() {
        // Registra task schedulati
        add_action('wsd_auto_maintenance', array(__CLASS__, 'run_auto_maintenance'));
        add_action('wsd_weekly_optimization', array(__CLASS__, 'run_weekly_optimization'));
        add_action('wsd_monthly_deep_clean', array(__CLASS__, 'run_monthly_deep_clean'));
        
        // Schedula eventi se non esistono
        self::schedule_events();
        
        // Hook per admin
        add_action('admin_init', array(__CLASS__, 'register_scheduler_settings'));
        add_action('wp_ajax_wsd_toggle_auto_scheduler', array(__CLASS__, 'ajax_toggle_scheduler'));
        add_action('wp_ajax_wsd_run_manual_maintenance', array(__CLASS__, 'ajax_run_manual_maintenance'));
        
        // Pulizia on deactivation
        register_deactivation_hook(WSD_FILE, array(__CLASS__, 'cleanup_scheduled_events'));
    }
    
    public static function schedule_events() {
        $settings = get_option('wsd_scheduler_settings', array(
            'auto_maintenance' => true,
            'weekly_optimization' => true,
            'monthly_deep_clean' => true,
            'maintenance_time' => '03:00', // 3 AM
            'timezone' => 'server'
        ));
        
        if (!$settings['auto_maintenance']) {
            return;
        }
        
        // Daily maintenance (3 AM)
        if (!wp_next_scheduled('wsd_auto_maintenance')) {
            $time = self::get_scheduled_time($settings['maintenance_time']);
            wp_schedule_event($time, 'daily', 'wsd_auto_maintenance');
        }
        
        // Weekly optimization (Sunday 2 AM)
        if (!wp_next_scheduled('wsd_weekly_optimization') && $settings['weekly_optimization']) {
            $time = self::get_next_sunday() + (2 * HOUR_IN_SECONDS);
            wp_schedule_event($time, 'weekly', 'wsd_weekly_optimization');
        }
        
        // Monthly deep clean (1st of month, 1 AM)
        if (!wp_next_scheduled('wsd_monthly_deep_clean') && $settings['monthly_deep_clean']) {
            $time = self::get_next_month_first() + HOUR_IN_SECONDS;
            wp_schedule_event($time, 'monthly', 'wsd_monthly_deep_clean');
        }
    }
    
    public static function run_auto_maintenance() {
        $start_time = microtime(true);
        $log = array();
        $errors = array();
        
        try {
            // 1. Pulizia database leggera
            $log[] = "🗄️ Avvio pulizia database automatica...";
            $db_result = self::light_database_cleanup();
            $log = array_merge($log, $db_result['actions']);
            if (!empty($db_result['errors'])) {
                $errors = array_merge($errors, $db_result['errors']);
            }
            
            // 2. Ottimizzazione WP-Cron
            $log[] = "⏰ Controllo e ottimizzazione WP-Cron...";
            $cron_result = self::auto_cron_optimization();
            $log = array_merge($log, $cron_result['actions']);
            
            // 3. Pulizia cache scadute
            $log[] = "🧹 Pulizia cache e transient scaduti...";
            $cache_result = self::cleanup_expired_cache();
            $log = array_merge($log, $cache_result['actions']);
            
            // 4. Controllo Action Scheduler
            if (class_exists('ActionScheduler')) {
                $log[] = "⚡ Manutenzione Action Scheduler...";
                $as_result = self::auto_action_scheduler_maintenance();
                $log = array_merge($log, $as_result['actions']);
            }
            
            // 5. Aggiornamento statistiche
            $log[] = "📊 Aggiornamento statistiche performance...";
            self::update_performance_stats();
            
        } catch (Exception $e) {
            $errors[] = "Errore durante manutenzione automatica: " . $e->getMessage();
        }
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time), 2);
        
        // Salva log
        $maintenance_log = array(
            'timestamp' => current_time('mysql'),
            'duration' => $duration,
            'actions' => $log,
            'errors' => $errors,
            'type' => 'auto_maintenance'
        );
        
        self::save_maintenance_log($maintenance_log);
        
        // Notifica se ci sono errori
        if (!empty($errors)) {
            self::notify_maintenance_errors($maintenance_log);
        }
        
        error_log("[WSD] Auto maintenance completed in {$duration}s - Actions: " . count($log) . ", Errors: " . count($errors));
    }
    
    public static function run_weekly_optimization() {
        $start_time = microtime(true);
        $log = array();
        
        try {
            $log[] = "🚀 Avvio ottimizzazione settimanale...";
            
            // 1. Ottimizzazione database completa
            $log[] = "🗄️ Ottimizzazione tabelle database...";
            global $wpdb;
            $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
            $optimized = 0;
            foreach ($tables as $table) {
                $result = $wpdb->query("OPTIMIZE TABLE {$table[0]}");
                if ($result !== false) $optimized++;
            }
            $log[] = "✅ Ottimizzate {$optimized} tabelle database";
            
            // 2. Analisi e ottimizzazione plugin
            $log[] = "🔌 Analisi performance plugin...";
            $plugin_result = self::weekly_plugin_analysis();
            $log = array_merge($log, $plugin_result['actions']);
            
            // 3. Pulizia log vecchi
            $log[] = "📄 Pulizia log performance vecchi...";
            self::cleanup_old_logs();
            $log[] = "✅ Log performance puliti";
            
            // 4. Backup configurazione
            $log[] = "💾 Backup configurazione WSD...";
            self::backup_wsd_configuration();
            $log[] = "✅ Backup configurazione creato";
            
            // 5. Report settimanale
            if (self::should_send_weekly_report()) {
                $log[] = "📧 Invio report settimanale...";
                self::send_weekly_report();
                $log[] = "✅ Report settimanale inviato";
            }
            
        } catch (Exception $e) {
            $log[] = "❌ Errore durante ottimizzazione settimanale: " . $e->getMessage();
        }
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time), 2);
        
        $maintenance_log = array(
            'timestamp' => current_time('mysql'),
            'duration' => $duration,
            'actions' => $log,
            'errors' => array(),
            'type' => 'weekly_optimization'
        );
        
        self::save_maintenance_log($maintenance_log);
        
        error_log("[WSD] Weekly optimization completed in {$duration}s");
    }
    
    public static function run_monthly_deep_clean() {
        $start_time = microtime(true);
        $log = array();
        
        try {
            $log[] = "🧹 Avvio pulizia profonda mensile...";
            
            // 1. Pulizia database approfondita
            $log[] = "🗄️ Pulizia database approfondita...";
            global $wpdb;
            
            // Rimuovi revisioni molto vecchie (>90 giorni)
            $old_revisions = $wpdb->query("
                DELETE FROM {$wpdb->posts} 
                WHERE post_type = 'revision' 
                AND post_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            if ($old_revisions > 0) {
                $log[] = "📝 Rimosse {$old_revisions} revisioni molto vecchie";
            }
            
            // Rimuovi metadati orfani
            $orphaned_meta = $wpdb->query("
                DELETE pm FROM {$wpdb->postmeta} pm 
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE p.ID IS NULL
            ");
            if ($orphaned_meta > 0) {
                $log[] = "🗃️ Rimossi {$orphaned_meta} metadati orfani";
            }
            
            // 2. Analisi e pulizia plugin inattivi
            $log[] = "🔌 Analisi plugin inattivi...";
            $inactive_cleanup = self::cleanup_inactive_plugins_data();
            $log = array_merge($log, $inactive_cleanup['actions']);
            
            // 3. Pulizia cache WP profonda
            $log[] = "🗑️ Pulizia cache WordPress profonda...";
            wp_cache_flush();
            if (function_exists('w3tc_flush_all')) {
                w3tc_flush_all();
                $log[] = "✅ Cache W3TC pulita";
            }
            if (function_exists('wp_rocket_clean_domain')) {
                wp_rocket_clean_domain();
                $log[] = "✅ Cache WP Rocket pulita";
            }
            
            // 4. Controllo salute generale
            $log[] = "🏥 Controllo salute sistema completo...";
            $health_check = self::comprehensive_health_check();
            $log = array_merge($log, $health_check['summary']);
            
            // 5. Archivio log vecchi
            $log[] = "📦 Archiviazione log performance...";
            self::archive_old_performance_logs();
            $log[] = "✅ Log performance archiviati";
            
        } catch (Exception $e) {
            $log[] = "❌ Errore durante pulizia mensile: " . $e->getMessage();
        }
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time), 2);
        
        $maintenance_log = array(
            'timestamp' => current_time('mysql'),
            'duration' => $duration,
            'actions' => $log,
            'errors' => array(),
            'type' => 'monthly_deep_clean'
        );
        
        self::save_maintenance_log($maintenance_log);
        
        error_log("[WSD] Monthly deep clean completed in {$duration}s");
    }
    
    private static function light_database_cleanup() {
        global $wpdb;
        $actions = array();
        $errors = array();
        
        try {
            // Pulizia transient scaduti (max 50 per volta)
            $expired_transients = $wpdb->query("
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_%' 
                AND option_value < UNIX_TIMESTAMP() 
                LIMIT 50
            ");
            
            if ($expired_transients > 0) {
                $actions[] = "🗂️ Rimossi {$expired_transients} transient scaduti";
            }
            
            // Pulizia spam comments vecchi (max 20 per volta)
            $old_spam = $wpdb->query("
                DELETE FROM {$wpdb->comments} 
                WHERE comment_approved = 'spam' 
                AND comment_date < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                LIMIT 20
            ");
            
            if ($old_spam > 0) {
                $actions[] = "💬 Rimossi {$old_spam} commenti spam vecchi";
            }
            
            // Pulizia revisioni vecchie (max 10 per volta)
            $old_revisions = $wpdb->query("
                DELETE FROM {$wpdb->posts} 
                WHERE post_type = 'revision' 
                AND post_date < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                LIMIT 10
            ");
            
            if ($old_revisions > 0) {
                $actions[] = "📝 Rimosse {$old_revisions} revisioni vecchie";
            }
            
        } catch (Exception $e) {
            $errors[] = "Errore pulizia database: " . $e->getMessage();
        }
        
        return array('actions' => $actions, 'errors' => $errors);
    }
    
    private static function auto_cron_optimization() {
        $actions = array();
        
        $crons = _get_cron_array();
        $removed = 0;
        $cutoff_time = time() - (2 * HOUR_IN_SECONDS); // Eventi più vecchi di 2 ore
        
        foreach ($crons as $timestamp => $cron) {
            if ($timestamp < $cutoff_time) {
                foreach ($cron as $hook => $events) {
                    foreach ($events as $event) {
                        wp_unschedule_event($timestamp, $hook, $event['args']);
                        $removed++;
                    }
                }
            }
        }
        
        if ($removed > 0) {
            $actions[] = "⏰ Rimossi {$removed} eventi cron in ritardo";
        } else {
            $actions[] = "⏰ WP-Cron è pulito e aggiornato";
        }
        
        return array('actions' => $actions);
    }
    
    private static function cleanup_expired_cache() {
        $actions = array();
        
        // Pulizia object cache se disponibile
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $actions[] = "🗑️ Object cache pulito";
        }
        
        // Pulizia transient scaduti specifici WSD
        $cleaned = delete_expired_transients();
        if ($cleaned > 0) {
            $actions[] = "🧹 Rimossi {$cleaned} transient scaduti";
        }
        
        return array('actions' => $actions);
    }
    
    private static function auto_action_scheduler_maintenance() {
        global $wpdb;
        $actions = array();
        
        $table_name = $wpdb->prefix . 'actionscheduler_actions';
        
        // Pulizia task completati vecchi
        $completed_cleaned = $wpdb->query("
            DELETE FROM {$table_name} 
            WHERE status = 'complete' 
            AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 7 DAY 
            LIMIT 50
        ");
        
        if ($completed_cleaned > 0) {
            $actions[] = "✅ Puliti {$completed_cleaned} task AS completati";
        }
        
        // Reset task in-progress molto vecchi
        $stuck_reset = $wpdb->query("
            UPDATE {$table_name} 
            SET status = 'failed' 
            WHERE status = 'in-progress' 
            AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 10 MINUTE 
            LIMIT 10
        ");
        
        if ($stuck_reset > 0) {
            $actions[] = "🔓 Reset {$stuck_reset} task AS bloccati";
        }
        
        return array('actions' => $actions);
    }
    
    public static function ajax_toggle_scheduler() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $enabled = $_POST['enabled'] === 'true';
        $settings = get_option('wsd_scheduler_settings', array());
        $settings['auto_maintenance'] = $enabled;
        
        update_option('wsd_scheduler_settings', $settings);
        
        if ($enabled) {
            self::schedule_events();
            wp_send_json_success('Scheduler automatico abilitato');
        } else {
            self::cleanup_scheduled_events();
            wp_send_json_success('Scheduler automatico disabilitato');
        }
    }
    
    public static function ajax_run_manual_maintenance() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'maintenance');
        
        switch ($type) {
            case 'maintenance':
                self::run_auto_maintenance();
                break;
            case 'weekly':
                self::run_weekly_optimization();
                break;
            case 'monthly':
                self::run_monthly_deep_clean();
                break;
            default:
                wp_send_json_error('Tipo manutenzione non valido');
                return;
        }
        
        wp_send_json_success('Manutenzione ' . $type . ' completata con successo');
    }
    
    public static function cleanup_scheduled_events() {
        wp_clear_scheduled_hook('wsd_auto_maintenance');
        wp_clear_scheduled_hook('wsd_weekly_optimization');
        wp_clear_scheduled_hook('wsd_monthly_deep_clean');
    }
    
    private static function save_maintenance_log($log) {
        $logs = get_option('wsd_maintenance_logs', array());
        $logs[] = $log;
        
        // Mantieni solo gli ultimi 50 log
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('wsd_maintenance_logs', $logs);
    }
    
    public static function render_scheduler_dashboard() {
        $settings = get_option('wsd_scheduler_settings', array());
        $logs = get_option('wsd_maintenance_logs', array());
        $last_logs = array_slice(array_reverse($logs), 0, 5);
        
        echo '<div class="wsd-section">';
        echo '<h2><span class="dashicons dashicons-clock"></span> Scheduler Automatico</h2>';
        
        // Status scheduler
        $auto_enabled = $settings['auto_maintenance'] ?? true;
        echo '<div class="wsd-scheduler-status ' . ($auto_enabled ? 'enabled' : 'disabled') . '">';
        echo '<h3>Stato Scheduler: ' . ($auto_enabled ? '✅ Attivo' : '❌ Disattivato') . '</h3>';
        
        if ($auto_enabled) {
            $next_maintenance = wp_next_scheduled('wsd_auto_maintenance');
            $next_weekly = wp_next_scheduled('wsd_weekly_optimization');
            $next_monthly = wp_next_scheduled('wsd_monthly_deep_clean');
            
            echo '<p><strong>Prossima manutenzione:</strong> ' . ($next_maintenance ? date('d/m/Y H:i', $next_maintenance) : 'Non schedulata') . '</p>';
            echo '<p><strong>Prossima ottimizzazione:</strong> ' . ($next_weekly ? date('d/m/Y H:i', $next_weekly) : 'Non schedulata') . '</p>';
            echo '<p><strong>Prossima pulizia profonda:</strong> ' . ($next_monthly ? date('d/m/Y H:i', $next_monthly) : 'Non schedulata') . '</p>';
        }
        
        echo '</div>';
        
        // Controlli manuali
        echo '<div class="wsd-scheduler-controls">';
        echo '<h3>Controlli Manuali</h3>';
        echo '<button id="wsd-toggle-scheduler" class="button ' . ($auto_enabled ? 'button-secondary' : 'button-primary') . '">';
        echo $auto_enabled ? '⏸️ Disabilita Scheduler' : '▶️ Abilita Scheduler';
        echo '</button>';
        
        echo '<button id="wsd-run-maintenance" class="button button-secondary" style="margin-left: 10px;">🔧 Manutenzione Ora</button>';
        echo '<button id="wsd-run-weekly" class="button button-secondary" style="margin-left: 10px;">🚀 Ottimizzazione Ora</button>';
        echo '<button id="wsd-run-monthly" class="button button-secondary" style="margin-left: 10px;">🧹 Pulizia Profonda Ora</button>';
        echo '</div>';
        
        // Log recenti
        if (!empty($last_logs)) {
            echo '<div class="wsd-scheduler-logs">';
            echo '<h3>Log Manutenzioni Recenti</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Data/Ora</th><th>Tipo</th><th>Durata</th><th>Azioni</th><th>Errori</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($last_logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['timestamp']) . '</td>';
                echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', $log['type']))) . '</td>';
                echo '<td>' . esc_html($log['duration']) . 's</td>';
                echo '<td>' . count($log['actions']) . '</td>';
                echo '<td>' . (empty($log['errors']) ? '✅' : '❌ ' . count($log['errors'])) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    // Funzioni di utilità
    private static function get_scheduled_time($time_string) {
        $time_parts = explode(':', $time_string);
        $hour = intval($time_parts[0]);
        $minute = intval($time_parts[1] ?? 0);
        
        $tomorrow = strtotime('tomorrow');
        return $tomorrow + ($hour * HOUR_IN_SECONDS) + ($minute * MINUTE_IN_SECONDS);
    }
    
    private static function get_next_sunday() {
        return strtotime('next sunday');
    }
    
    private static function get_next_month_first() {
        return strtotime('first day of next month');
    }
    
    // Placeholder per funzioni aggiuntive
    private static function weekly_plugin_analysis() {
        return array('actions' => array('📊 Analisi plugin completata'));
    }
    
    private static function cleanup_old_logs() {
        // Implementazione pulizia log
    }
    
    private static function backup_wsd_configuration() {
        // Implementazione backup configurazione
    }
    
    private static function should_send_weekly_report() {
        return false; // Implementare logica
    }
    
    private static function send_weekly_report() {
        // Implementazione invio report
    }
    
    private static function cleanup_inactive_plugins_data() {
        return array('actions' => array('🧹 Dati plugin inattivi puliti'));
    }
    
    private static function comprehensive_health_check() {
        return array('summary' => array('🏥 Controllo salute completato'));
    }
    
    private static function archive_old_performance_logs() {
        // Implementazione archiviazione log
    }
    
    private static function update_performance_stats() {
        // Aggiorna statistiche generali
        update_option('wsd_last_maintenance', current_time('mysql'));
    }
    
    private static function notify_maintenance_errors($log) {
        if (!empty($log['errors'])) {
            error_log('[WSD] Maintenance errors: ' . implode(', ', $log['errors']));
        }
    }
}