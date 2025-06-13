<?php
/**
 * Modulo di riparazione automatica per problemi di performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Auto_Repair {
    
    public static function init() {
        // Già inizializzato nel file principale
    }
    
    public static function get_system_health() {
        $health = array(
            'action_scheduler' => self::check_action_scheduler(),
            'wp_cron' => self::check_wp_cron(),
            'plugins' => self::check_plugins_load(),
            'database' => self::check_database_health()
        );
        
        return $health;
    }
    
    private static function check_action_scheduler() {
        global $wpdb;
        
        if (!class_exists('ActionScheduler')) {
            return array(
                'status' => 'not_available',
                'message' => 'Action Scheduler non installato',
                'issues' => array()
            );
        }
        
        $table_name = $wpdb->prefix . 'actionscheduler_actions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return array(
                'status' => 'warning',
                'message' => 'Tabelle Action Scheduler non trovate',
                'issues' => array('Tabelle database mancanti')
            );
        }
        
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'");
        $stuck = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'in-progress' AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 5 MINUTE");
        
        $status = 'good';
        $issues = array();
        
        if ($pending > 100) {
            $status = 'critical';
            $issues[] = "⚠️ {$pending} task in coda (troppi!)";
        }
        
        if ($failed > 10) {
            $status = 'critical';
            $issues[] = "❌ {$failed} task falliti";
        }
        
        if ($stuck > 0) {
            $status = 'warning';
            $issues[] = "🔄 {$stuck} task bloccati";
        }
        
        return array(
            'status' => $status,
            'pending' => $pending,
            'failed' => $failed,
            'stuck' => $stuck,
            'issues' => $issues,
            'message' => empty($issues) ? '✅ Action Scheduler OK' : implode(', ', $issues)
        );
    }
    
    private static function check_wp_cron() {
        $crons = _get_cron_array();
        $total_events = 0;
        $overdue_events = 0;
        $problem_hooks = array();
        
        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                $total_events += count($events);
                
                if ($timestamp < time()) {
                    $overdue_events += count($events);
                    $problem_hooks[$hook] = ($problem_hooks[$hook] ?? 0) + count($events);
                }
            }
        }
        
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        $status = 'good';
        $issues = array();
        
        if (!$wp_cron_disabled && $overdue_events > 10) {
            $status = 'critical';
            $issues[] = "⏰ {$overdue_events} eventi cron in ritardo";
        }
        
        if ($total_events > 50) {
            $status = 'warning';
            $issues[] = "📈 {$total_events} eventi cron totali (molti)";
        }
        
        if (!$wp_cron_disabled) {
            $issues[] = "🔧 WP-Cron interno attivo (raccomandato: cron server)";
        }
        
        return array(
            'status' => $status,
            'total_events' => $total_events,
            'overdue_events' => $overdue_events,
            'wp_cron_disabled' => $wp_cron_disabled,
            'problem_hooks' => array_slice($problem_hooks, 0, 5, true),
            'issues' => $issues,
            'message' => empty($issues) ? '✅ WP-Cron OK' : implode(', ', $issues)
        );
    }
    
    private static function check_plugins_load() {
        $active_plugins = get_option('active_plugins', array());
        $plugin_count = count($active_plugins);
        
        $plugin_analysis = self::analyze_plugins_performance();
        
        $status = 'good';
        $issues = array();
        
        if ($plugin_count > 30) {
            $status = 'critical';
            $issues[] = "🔌 {$plugin_count} plugin attivi (troppi!)";
        } elseif ($plugin_count > 20) {
            $status = 'warning';
            $issues[] = "🔌 {$plugin_count} plugin attivi (molti)";
        }
        
        if (!empty($plugin_analysis['heavy_plugins'])) {
            $status = 'warning';
            $heavy_count = count($plugin_analysis['heavy_plugins']);
            $issues[] = "⚡ {$heavy_count} plugin pesanti rilevati";
        }
        
        if (!empty($plugin_analysis['outdated_plugins'])) {
            $outdated_count = count($plugin_analysis['outdated_plugins']);
            $issues[] = "📅 {$outdated_count} plugin non aggiornati";
        }
        
        if (!empty($plugin_analysis['conflicting_plugins'])) {
            $conflicts = count($plugin_analysis['conflicting_plugins']);
            $status = 'warning';
            $issues[] = "⚠️ {$conflicts} possibili conflitti plugin";
        }
        
        return array(
            'status' => $status,
            'total_plugins' => $plugin_count,
            'plugin_analysis' => $plugin_analysis,
            'issues' => $issues,
            'message' => empty($issues) ? '✅ Plugin ottimizzati' : implode(', ', $issues)
        );
    }
    
    private static function analyze_plugins_performance() {
        $active_plugins = get_option('active_plugins', array());
        $all_plugins = get_plugins();
        
        $heavy_plugins = array();
        $outdated_plugins = array();
        $conflicting_plugins = array();
        $unused_plugins = array();
        
        // Database dei plugin noti per essere pesanti
        $known_heavy = array(
            'updraftplus' => array('name' => 'UpdraftPlus', 'impact' => 'alto', 'reason' => 'Backup intensivo'),
            'woocommerce-subscriptions' => array('name' => 'WC Subscriptions', 'impact' => 'alto', 'reason' => 'Molte query DB'),
            'mailpoet' => array('name' => 'MailPoet', 'impact' => 'medio', 'reason' => 'Caricamento frontend'),
            'elementor' => array('name' => 'Elementor', 'impact' => 'alto', 'reason' => 'CSS/JS pesanti'),
            'jetpack' => array('name' => 'Jetpack', 'impact' => 'alto', 'reason' => 'Molti moduli attivi'),
            'wordfence' => array('name' => 'Wordfence', 'impact' => 'medio', 'reason' => 'Scansioni sicurezza'),
            'yoast' => array('name' => 'Yoast SEO', 'impact' => 'medio', 'reason' => 'Analisi contenuti'),
            'contact-form-7' => array('name' => 'Contact Form 7', 'impact' => 'basso', 'reason' => 'Caricamento script'),
            'woocommerce-pdf-invoices' => array('name' => 'WC PDF Invoices', 'impact' => 'medio', 'reason' => 'Generazione PDF'),
            'wp-rocket' => array('name' => 'WP Rocket', 'impact' => 'benefico', 'reason' => 'Plugin di cache'),
            'w3-total-cache' => array('name' => 'W3 Total Cache', 'impact' => 'benefico', 'reason' => 'Plugin di cache')
        );
        
        // Plugin conflittuali (stesso scopo)
        $conflicting_categories = array(
            'cache' => array('wp-rocket', 'w3-total-cache', 'wp-super-cache', 'litespeed-cache'),
            'seo' => array('yoast', 'rankmath', 'all-in-one-seo-pack'),
            'security' => array('wordfence', 'ithemes-security', 'sucuri-scanner'),
            'backup' => array('updraftplus', 'backwpup', 'duplicator')
        );
        
        foreach ($active_plugins as $plugin_file) {
            $plugin_slug = dirname($plugin_file);
            $plugin_data = $all_plugins[$plugin_file] ?? array();
            
            // Controlla plugin pesanti
            foreach ($known_heavy as $heavy_slug => $heavy_info) {
                if (strpos($plugin_slug, $heavy_slug) !== false) {
                    $heavy_plugins[$plugin_file] = array_merge($heavy_info, array(
                        'file' => $plugin_file,
                        'version' => $plugin_data['Version'] ?? 'N/A'
                    ));
                }
            }
            
            // Controlla plugin non aggiornati (simulazione - dovresti usare wp_remote_get per controllare)
            if (!empty($plugin_data['Version'])) {
                $version = $plugin_data['Version'];
                if (version_compare($version, '1.0', '<') || strpos($version, 'beta') !== false) {
                    $outdated_plugins[$plugin_file] = array(
                        'name' => $plugin_data['Name'] ?? $plugin_slug,
                        'version' => $version,
                        'file' => $plugin_file
                    );
                }
            }
        }
        
        // Trova conflitti
        foreach ($conflicting_categories as $category => $plugins_in_category) {
            $found_in_category = array();
            foreach ($active_plugins as $plugin_file) {
                $plugin_slug = dirname($plugin_file);
                foreach ($plugins_in_category as $conflicting_slug) {
                    if (strpos($plugin_slug, $conflicting_slug) !== false) {
                        $found_in_category[] = array(
                            'file' => $plugin_file,
                            'name' => $all_plugins[$plugin_file]['Name'] ?? $plugin_slug,
                            'category' => $category
                        );
                    }
                }
            }
            if (count($found_in_category) > 1) {
                $conflicting_plugins[$category] = $found_in_category;
            }
        }
        
        return array(
            'heavy_plugins' => $heavy_plugins,
            'outdated_plugins' => $outdated_plugins,
            'conflicting_plugins' => $conflicting_plugins,
            'unused_plugins' => $unused_plugins,
            'total_active' => count($active_plugins)
        );
    }
    
    private static function check_database_health() {
        global $wpdb;
        
        $revisions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        $spam_comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        $transients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $orphaned_meta = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
            WHERE p.ID IS NULL
        ");
        
        $status = 'good';
        $issues = array();
        
        if ($revisions > 200) {
            $status = 'warning';
            $issues[] = "📝 {$revisions} revisioni (molte)";
        }
        
        if ($spam_comments > 50) {
            $status = 'warning';
            $issues[] = "💬 {$spam_comments} commenti spam";
        }
        
        if ($transients > 1000) {
            $status = 'warning';
            $issues[] = "🗂️ {$transients} transient (molti)";
        }
        
        if ($orphaned_meta > 100) {
            $status = 'warning';
            $issues[] = "🗃️ {$orphaned_meta} metadati orfani";
        }
        
        return array(
            'status' => $status,
            'revisions' => $revisions,
            'spam_comments' => $spam_comments,
            'transients' => $transients,
            'orphaned_meta' => $orphaned_meta,
            'issues' => $issues,
            'message' => empty($issues) ? '✅ Database ottimizzato' : implode(', ', $issues)
        );
    }
    
    public static function repair_action_scheduler() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        global $wpdb;
        $repairs = array();
        
        try {
            $table_name = $wpdb->prefix . 'actionscheduler_actions';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if (!$table_exists) {
                wp_send_json_error('Tabelle Action Scheduler non trovate');
                return;
            }
            
            $stuck_fixed = $wpdb->query("
                UPDATE {$table_name} 
                SET status = 'failed' 
                WHERE status = 'in-progress' 
                AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 5 MINUTE
            ");
            
            if ($stuck_fixed > 0) {
                $repairs[] = "🔓 Sbloccati {$stuck_fixed} task in stallo";
            }
            
            $failed_cleaned = $wpdb->query("
                DELETE FROM {$table_name} 
                WHERE status = 'failed' 
                AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 7 DAY
            ");
            
            if ($failed_cleaned > 0) {
                $repairs[] = "🗑️ Eliminati {$failed_cleaned} task falliti obsoleti";
            }
            
            $completed_cleaned = $wpdb->query("
                DELETE FROM {$table_name} 
                WHERE status = 'complete' 
                AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 30 DAY
            ");
            
            if ($completed_cleaned > 0) {
                $repairs[] = "✅ Eliminati {$completed_cleaned} task completati obsoleti";
            }
            
            wp_send_json_success(array(
                'message' => 'Action Scheduler riparato!',
                'repairs' => $repairs
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Errore durante la riparazione: ' . $e->getMessage());
        }
    }
    
    public static function optimize_wp_cron() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $optimizations = array();
        
        try {
            $crons = _get_cron_array();
            $removed_count = 0;
            $cutoff_time = time() - HOUR_IN_SECONDS;
            
            foreach ($crons as $timestamp => $cron) {
                if ($timestamp < $cutoff_time) {
                    foreach ($cron as $hook => $events) {
                        foreach ($events as $event) {
                            wp_unschedule_event($timestamp, $hook, $event['args']);
                            $removed_count++;
                        }
                    }
                }
            }
            
            if ($removed_count > 0) {
                $optimizations[] = "⏰ Rimossi {$removed_count} eventi cron in ritardo";
            }
            
            if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
                $optimizations[] = "💡 Raccomandazione: Disabilita WP-Cron interno e usa cron server";
            }
            
            wp_send_json_success(array(
                'message' => 'WP-Cron ottimizzato!',
                'optimizations' => $optimizations,
                'wp_config_suggestion' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Errore durante l\'ottimizzazione: ' . $e->getMessage());
        }
    }
    
    public static function database_cleanup() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        global $wpdb;
        $cleaned = array();
        
        try {
            // Pulizia revisioni vecchie
            $revisions_deleted = $wpdb->query("
                DELETE FROM {$wpdb->posts} 
                WHERE post_type = 'revision' 
                AND post_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
                LIMIT 200
            ");
            
            if ($revisions_deleted > 0) {
                $cleaned[] = "📝 Eliminate {$revisions_deleted} revisioni vecchie";
            }
            
            // Pulizia commenti spam
            $spam_deleted = $wpdb->query("
                DELETE FROM {$wpdb->comments} 
                WHERE comment_approved = 'spam' 
                AND comment_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
                LIMIT 200
            ");
            
            if ($spam_deleted > 0) {
                $cleaned[] = "💬 Eliminati {$spam_deleted} commenti spam";
            }
            
            // Pulizia transient scaduti
            $transients_deleted = $wpdb->query("
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_%' 
                AND option_value < UNIX_TIMESTAMP()
                LIMIT 200
            ");
            
            if ($transients_deleted > 0) {
                $cleaned[] = "🗂️ Eliminati {$transients_deleted} transient scaduti";
            }
            
            // Pulizia metadati orfani
            $orphaned_deleted = $wpdb->query("
                DELETE pm FROM {$wpdb->postmeta} pm 
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE p.ID IS NULL
                LIMIT 100
            ");
            
            if ($orphaned_deleted > 0) {
                $cleaned[] = "🗃️ Eliminati {$orphaned_deleted} metadati orfani";
            }
            
            // Ottimizza tabelle
            $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
            $optimized_tables = 0;
            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE {$table[0]}");
                $optimized_tables++;
            }
            
            if ($optimized_tables > 0) {
                $cleaned[] = "⚡ Ottimizzate {$optimized_tables} tabelle database";
            }
            
            wp_send_json_success(array(
                'message' => 'Database ottimizzato completamente!',
                'cleaned' => $cleaned
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Errore durante la pulizia: ' . $e->getMessage());
        }
    }
    
    /**
     * NUOVA FUNZIONALITÀ: Ottimizzazione Plugin
     */
    public static function optimize_plugins() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $optimizations = array();
        $recommendations = array();
        $warnings = array();
        
        try {
            $plugin_analysis = self::analyze_plugins_performance();
            
            // Backup della configurazione attuale
            $current_config = array(
                'active_plugins' => get_option('active_plugins'),
                'timestamp' => current_time('mysql')
            );
            update_option('wsd_plugin_backup', $current_config);
            
            $optimizations[] = "💾 Backup configurazione plugin creato";
            
            // Analizza e suggerisci ottimizzazioni per plugin pesanti
            if (!empty($plugin_analysis['heavy_plugins'])) {
                foreach ($plugin_analysis['heavy_plugins'] as $plugin_file => $plugin_info) {
                    if ($plugin_info['impact'] === 'alto') {
                        $warnings[] = "⚡ Plugin pesante rilevato: {$plugin_info['name']} - {$plugin_info['reason']}";
                        
                        // Suggerimenti specifici
                        $suggestions = self::get_plugin_optimization_suggestions($plugin_file, $plugin_info);
                        $recommendations = array_merge($recommendations, $suggestions);
                    }
                }
            }
            
            // Gestisci conflitti tra plugin
            if (!empty($plugin_analysis['conflicting_plugins'])) {
                foreach ($plugin_analysis['conflicting_plugins'] as $category => $conflicting) {
                    $plugin_names = array_column($conflicting, 'name');
                    $warnings[] = "⚠️ Conflitto {$category}: " . implode(', ', $plugin_names);
                    $recommendations[] = "Mantieni solo un plugin per {$category} per evitare conflitti";
                }
            }
            
            // Ottimizzazioni automatiche sicure
            $auto_optimizations = self::perform_safe_plugin_optimizations();
            $optimizations = array_merge($optimizations, $auto_optimizations);
            
            // Genera report dettagliato
            $report = array(
                'total_plugins' => count(get_option('active_plugins')),
                'heavy_plugins_count' => count($plugin_analysis['heavy_plugins']),
                'conflicts_found' => count($plugin_analysis['conflicting_plugins']),
                'optimizations_applied' => count($auto_optimizations),
                'performance_score' => self::calculate_plugin_performance_score($plugin_analysis)
            );
            
            wp_send_json_success(array(
                'message' => 'Analisi plugin completata!',
                'optimizations' => $optimizations,
                'recommendations' => $recommendations,
                'warnings' => $warnings,
                'report' => $report,
                'detailed_analysis' => $plugin_analysis
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Errore durante l\'ottimizzazione plugin: ' . $e->getMessage());
        }
    }
    
    private static function get_plugin_optimization_suggestions($plugin_file, $plugin_info) {
        $suggestions = array();
        $plugin_slug = dirname($plugin_file);
        
        // Suggerimenti specifici per plugin comuni
        switch (true) {
            case strpos($plugin_slug, 'elementor') !== false:
                $suggestions[] = "🎨 Elementor: Disabilita widget non utilizzati, usa 'Elementor > Tools > Regenerate Files'";
                $suggestions[] = "📦 Considera di passare a Gutenberg per pagine semplici";
                break;
                
            case strpos($plugin_slug, 'jetpack') !== false:
                $suggestions[] = "🚀 Jetpack: Disabilita moduli non necessari in 'Jetpack > Settings'";
                $suggestions[] = "📊 Usa plugin specializzati invece dei moduli Jetpack generici";
                break;
                
            case strpos($plugin_slug, 'wordfence') !== false:
                $suggestions[] = "🛡️ Wordfence: Riduci frequenza scansioni, disabilita 'Real-time IP blocklist'";
                $suggestions[] = "⚡ Considera Cloudflare per protezione a livello DNS";
                break;
                
            case strpos($plugin_slug, 'yoast') !== false:
                $suggestions[] = "📈 Yoast SEO: Disabilita 'Cornerstone content', riduci analisi in tempo reale";
                $suggestions[] = "🔍 Considera RankMath per migliori performance";
                break;
                
            case strpos($plugin_slug, 'updraftplus') !== false:
                $suggestions[] = "💾 UpdraftPlus: Esegui backup durante ore di basso traffico";
                $suggestions[] = "☁️ Usa storage remoto per evitare rallentamenti del server";
                break;
                
            default:
                $suggestions[] = "🔧 Controlla le impostazioni del plugin per opzioni di ottimizzazione";
        }
        
        return $suggestions;
    }
    
    private static function perform_safe_plugin_optimizations() {
        $optimizations = array();
        
        // Pulizia opzioni di plugin disattivati
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $inactive_plugins = array_diff(array_keys($all_plugins), $active_plugins);
        
        $cleaned_options = 0;
        foreach ($inactive_plugins as $plugin_file) {
            $plugin_slug = dirname($plugin_file);
            
            // Lista di opzioni comuni da pulire per plugin disattivati
            $common_options = array(
                $plugin_slug . '_settings',
                $plugin_slug . '_options',
                $plugin_slug . '_cache',
                $plugin_slug . '_data'
            );
            
            foreach ($common_options as $option) {
                if (get_option($option) !== false) {
                    delete_option($option);
                    $cleaned_options++;
                }
            }
        }
        
        if ($cleaned_options > 0) {
            $optimizations[] = "🧹 Pulite {$cleaned_options} opzioni di plugin disattivati";
        }
        
        // Ottimizza autoload delle opzioni
        global $wpdb;
        $large_autoload = $wpdb->get_results("
            SELECT option_name, LENGTH(option_value) as size 
            FROM {$wpdb->options} 
            WHERE autoload = 'yes' 
            AND LENGTH(option_value) > 1024 
            ORDER BY size DESC 
            LIMIT 10
        ");
        
        $autoload_optimized = 0;
        foreach ($large_autoload as $option) {
            // Disabilita autoload per opzioni grandi non critiche
            $non_critical_patterns = array('_transient_', '_cache_', '_backup_');
            foreach ($non_critical_patterns as $pattern) {
                if (strpos($option->option_name, $pattern) !== false) {
                    $wpdb->update(
                        $wpdb->options,
                        array('autoload' => 'no'),
                        array('option_name' => $option->option_name)
                    );
                    $autoload_optimized++;
                    break;
                }
            }
        }
        
        if ($autoload_optimized > 0) {
            $optimizations[] = "⚡ Ottimizzato autoload per {$autoload_optimized} opzioni pesanti";
        }
        
        return $optimizations;
    }
    
    private static function calculate_plugin_performance_score($analysis) {
        $score = 100;
        
        // Penalità per numero eccessivo di plugin
        $total_plugins = $analysis['total_active'];
        if ($total_plugins > 30) {
            $score -= 30;
        } elseif ($total_plugins > 20) {
            $score -= 15;
        } elseif ($total_plugins > 15) {
            $score -= 5;
        }
        
        // Penalità per plugin pesanti
        foreach ($analysis['heavy_plugins'] as $plugin) {
            switch ($plugin['impact']) {
                case 'alto':
                    $score -= 20;
                    break;
                case 'medio':
                    $score -= 10;
                    break;
                case 'basso':
                    $score -= 5;
                    break;
            }
        }
        
        // Penalità per conflitti
        $score -= count($analysis['conflicting_plugins']) * 15;
        
        // Bonus per plugin benefici (cache, ottimizzazione)
        foreach ($analysis['heavy_plugins'] as $plugin) {
            if ($plugin['impact'] === 'benefico') {
                $score += 10;
            }
        }
        
        return max(0, min(100, $score));
    }
    
    public static function display_repair_dashboard() {
        $health = self::get_system_health();
        
        echo '<div class="wsd-section wsd-repair-section">';
        echo '<h2><span class="dashicons dashicons-admin-tools"></span> Sistema di Riparazione Automatica</h2>';
        
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
            
            // Mostra dettagli aggiuntivi per i plugin
            if ($component === 'plugins' && isset($status['plugin_analysis'])) {
                $analysis = $status['plugin_analysis'];
                echo '<div style="margin: 10px 0; font-size: 13px; opacity: 0.9;">';
                if (!empty($analysis['heavy_plugins'])) {
                    echo '<span style="color: #fbbf24;">⚡ Plugin pesanti: ' . count($analysis['heavy_plugins']) . '</span><br>';
                }
                if (!empty($analysis['conflicting_plugins'])) {
                    echo '<span style="color: #f87171;">⚠️ Conflitti: ' . count($analysis['conflicting_plugins']) . '</span><br>';
                }
                echo '</div>';
            }
            
            if ($status['status'] !== 'good' && $status['status'] !== 'not_available') {
                $button_id = 'wsd-repair-' . str_replace('_', '-', $component);
                echo '<button id="' . $button_id . '" class="button button-primary">🔧 ' . 
                     ($component === 'plugins' ? 'Ottimizza' : 'Ripara') . ' Ora</button>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
}