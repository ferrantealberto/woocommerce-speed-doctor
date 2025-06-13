<?php
/**
 * Gestore Impostazioni Avanzate WSD
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSD_Settings_Manager {
    
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_ajax_wsd_save_setting', array(__CLASS__, 'ajax_save_setting'));
        add_action('wp_ajax_wsd_import_configuration', array(__CLASS__, 'ajax_import_configuration'));
        add_action('wp_ajax_wsd_export_configuration', array(__CLASS__, 'ajax_export_configuration'));
        add_action('wp_ajax_wsd_reset_settings', array(__CLASS__, 'ajax_reset_settings'));
    }
    
    public static function register_settings() {
        // Registra gruppi di impostazioni
        register_setting('wsd_general_settings', 'wsd_general_options', array(
            'type' => 'array',
            'default' => self::get_default_general_settings()
        ));
        
        register_setting('wsd_monitoring_settings', 'wsd_monitoring_options', array(
            'type' => 'array', 
            'default' => self::get_default_monitoring_settings()
        ));
        
        register_setting('wsd_optimization_settings', 'wsd_optimization_options', array(
            'type' => 'array',
            'default' => self::get_default_optimization_settings()
        ));
        
        register_setting('wsd_advanced_settings', 'wsd_advanced_options', array(
            'type' => 'array',
            'default' => self::get_default_advanced_settings()
        ));
    }
    
    public static function get_default_general_settings() {
        return array(
            'enabled' => true,
            'performance_monitoring' => true,
            'dashboard_widget' => true,
            'admin_bar_indicator' => true,
            'frontend_monitoring' => false,
            'debug_mode' => false
        );
    }
    
    public static function get_default_monitoring_settings() {
        return array(
            'log_slow_queries' => true,
            'slow_query_threshold' => 500, // milliseconds
            'log_memory_usage' => true,
            'memory_threshold' => 128, // MB
            'log_retention_days' => 30,
            'max_log_entries' => 1000,
            'real_time_monitoring' => true,
            'monitoring_interval' => 60 // seconds
        );
    }
    
    public static function get_default_optimization_settings() {
        return array(
            'auto_cleanup_enabled' => true,
            'cleanup_frequency' => 'daily',
            'auto_optimize_db' => false,
            'auto_plugin_optimization' => false,
            'cache_optimization' => true,
            'image_optimization_hints' => true,
            'css_js_optimization_hints' => true
        );
    }
    
    public static function get_default_advanced_settings() {
        return array(
            'custom_performance_thresholds' => false,
            'critical_threshold' => 3000, // ms
            'warning_threshold' => 1500, // ms
            'memory_critical_threshold' => 256, // MB
            'memory_warning_threshold' => 128, // MB
            'query_critical_threshold' => 100,
            'query_warning_threshold' => 50,
            'enable_api_monitoring' => false,
            'api_rate_limiting' => false,
            'custom_hooks' => false
        );
    }
    
    public static function render_settings_page() {
        // Recupera le impostazioni attuali
        $general = get_option('wsd_general_options', self::get_default_general_settings());
        $monitoring = get_option('wsd_monitoring_options', self::get_default_monitoring_settings());
        $optimization = get_option('wsd_optimization_options', self::get_default_optimization_settings());
        $advanced = get_option('wsd_advanced_options', self::get_default_advanced_settings());
        
        echo '<div class="wrap wsd-dashboard">';
        echo '<h1><span class="dashicons dashicons-admin-settings"></span> WSD - Impostazioni Avanzate</h1>';
        
        // Tab navigation
        echo '<div class="wsd-settings-tabs">';
        echo '<button class="wsd-tab-button active" data-tab="general">🔧 Generale</button>';
        echo '<button class="wsd-tab-button" data-tab="monitoring">📊 Monitoraggio</button>';
        echo '<button class="wsd-tab-button" data-tab="optimization">⚡ Ottimizzazione</button>';
        echo '<button class="wsd-tab-button" data-tab="advanced">🚀 Avanzate</button>';
        echo '<button class="wsd-tab-button" data-tab="tools">🛠️ Strumenti</button>';
        echo '</div>';
        
        echo '<form method="post" action="options.php" class="wsd-settings-form">';
        
        // Tab: Generale
        echo '<div class="wsd-tab-content active" id="wsd-tab-general">';
        self::render_general_settings($general);
        echo '</div>';
        
        // Tab: Monitoraggio
        echo '<div class="wsd-tab-content" id="wsd-tab-monitoring">';
        self::render_monitoring_settings($monitoring);
        echo '</div>';
        
        // Tab: Ottimizzazione
        echo '<div class="wsd-tab-content" id="wsd-tab-optimization">';
        self::render_optimization_settings($optimization);
        echo '</div>';
        
        // Tab: Avanzate
        echo '<div class="wsd-tab-content" id="wsd-tab-advanced">';
        self::render_advanced_settings($advanced);
        echo '</div>';
        
        // Tab: Strumenti
        echo '<div class="wsd-tab-content" id="wsd-tab-tools">';
        self::render_tools_section();
        echo '</div>';
        
        echo '</form>';
        
        // Statistiche e Info
        self::render_statistics_overview();
        
        echo '</div>';
    }
    
    private static function render_general_settings($settings) {
        echo '<div class="wsd-settings-section">';
        echo '<div class="wsd-settings-header">';
        echo '<h3>⚙️ Impostazioni Generali</h3>';
        echo '</div>';
        echo '<div class="wsd-settings-body">';
        
        settings_fields('wsd_general_settings');
        
        $setting_items = array(
            'enabled' => array(
                'label' => 'Plugin Abilitato',
                'description' => 'Abilita/disabilita completamente WSD',
                'type' => 'toggle'
            ),
            'performance_monitoring' => array(
                'label' => 'Monitoraggio Performance',
                'description' => 'Monitora automaticamente le performance del sito',
                'type' => 'toggle'
            ),
            'dashboard_widget' => array(
                'label' => 'Widget Dashboard',
                'description' => 'Mostra widget performance nella dashboard WordPress',
                'type' => 'toggle'
            ),
            'admin_bar_indicator' => array(
                'label' => 'Indicatore Admin Bar',
                'description' => 'Mostra indicatore performance nella barra admin',
                'type' => 'toggle'
            ),
            'frontend_monitoring' => array(
                'label' => 'Monitoraggio Frontend',
                'description' => 'Monitora performance anche sul frontend (può influire sulle performance)',
                'type' => 'toggle'
            ),
            'debug_mode' => array(
                'label' => 'Modalità Debug',
                'description' => 'Abilita logging dettagliato per debugging',
                'type' => 'toggle'
            )
        );
        
        foreach ($setting_items as $key => $item) {
            self::render_setting_item($key, $item, $settings[$key] ?? false, 'wsd_general_options');
        }
        
        echo '</div>';
        echo '</div>';
        
        submit_button('Salva Impostazioni Generali', 'primary', 'submit', false);
    }
    
    private static function render_monitoring_settings($settings) {
        echo '<div class="wsd-settings-section">';
        echo '<div class="wsd-settings-header">';
        echo '<h3>📊 Configurazione Monitoraggio</h3>';
        echo '</div>';
        echo '<div class="wsd-settings-body">';
        
        settings_fields('wsd_monitoring_settings');
        
        $setting_items = array(
            'log_slow_queries' => array(
                'label' => 'Log Query Lente',
                'description' => 'Registra automaticamente le query database lente',
                'type' => 'toggle'
            ),
            'slow_query_threshold' => array(
                'label' => 'Soglia Query Lente (ms)',
                'description' => 'Query più lente di questo valore verranno loggate',
                'type' => 'number',
                'min' => 100,
                'max' => 5000,
                'step' => 50
            ),
            'log_memory_usage' => array(
                'label' => 'Log Uso Memoria',
                'description' => 'Monitora e logga l\'uso memoria PHP',
                'type' => 'toggle'
            ),
            'memory_threshold' => array(
                'label' => 'Soglia Memoria (MB)',
                'description' => 'Uso memoria superiore genererà un alert',
                'type' => 'number',
                'min' => 64,
                'max' => 1024,
                'step' => 16
            ),
            'log_retention_days' => array(
                'label' => 'Giorni Retention Log',
                'description' => 'Per quanti giorni mantenere i log performance',
                'type' => 'number',
                'min' => 1,
                'max' => 365,
                'step' => 1
            ),
            'real_time_monitoring' => array(
                'label' => 'Monitoraggio Real-time',
                'description' => 'Aggiorna statistiche in tempo reale (richiede più risorse)',
                'type' => 'toggle'
            )
        );
        
        foreach ($setting_items as $key => $item) {
            self::render_setting_item($key, $item, $settings[$key] ?? false, 'wsd_monitoring_options');
        }
        
        echo '</div>';
        echo '</div>';
        
        submit_button('Salva Impostazioni Monitoraggio', 'primary', 'submit', false);
    }
    
    private static function render_optimization_settings($settings) {
        echo '<div class="wsd-settings-section">';
        echo '<div class="wsd-settings-header">';
        echo '<h3>⚡ Configurazione Ottimizzazioni</h3>';
        echo '</div>';
        echo '<div class="wsd-settings-body">';
        
        settings_fields('wsd_optimization_settings');
        
        $setting_items = array(
            'auto_cleanup_enabled' => array(
                'label' => 'Pulizia Automatica',
                'description' => 'Esegui automaticamente pulizia database e cache',
                'type' => 'toggle'
            ),
            'cleanup_frequency' => array(
                'label' => 'Frequenza Pulizia',
                'description' => 'Quanto spesso eseguire la pulizia automatica',
                'type' => 'select',
                'options' => array(
                    'hourly' => 'Ogni ora',
                    'daily' => 'Giornaliera',
                    'weekly' => 'Settimanale'
                )
            ),
            'auto_optimize_db' => array(
                'label' => 'Ottimizzazione DB Auto',
                'description' => 'Ottimizza automaticamente tabelle database',
                'type' => 'toggle'
            ),
            'auto_plugin_optimization' => array(
                'label' => 'Ottimizzazione Plugin Auto',
                'description' => 'Analizza e ottimizza automaticamente i plugin',
                'type' => 'toggle'
            ),
            'cache_optimization' => array(
                'label' => 'Suggerimenti Cache',
                'description' => 'Mostra suggerimenti per ottimizzazione cache',
                'type' => 'toggle'
            ),
            'image_optimization_hints' => array(
                'label' => 'Suggerimenti Immagini',
                'description' => 'Analizza e suggerisce ottimizzazioni immagini',
                'type' => 'toggle'
            )
        );
        
        foreach ($setting_items as $key => $item) {
            self::render_setting_item($key, $item, $settings[$key] ?? false, 'wsd_optimization_options');
        }
        
        echo '</div>';
        echo '</div>';
        
        submit_button('Salva Impostazioni Ottimizzazione', 'primary', 'submit', false);
    }
    
    private static function render_advanced_settings($settings) {
        echo '<div class="wsd-settings-section">';
        echo '<div class="wsd-settings-header">';
        echo '<h3>🚀 Impostazioni Avanzate</h3>';
        echo '</div>';
        echo '<div class="wsd-settings-body">';
        
        echo '<div class="notice notice-warning"><p><strong>⚠️ Attenzione:</strong> Modifica queste impostazioni solo se sai cosa stai facendo. Valori incorretti possono influire sulle performance del sito.</p></div>';
        
        settings_fields('wsd_advanced_settings');
        
        $setting_items = array(
            'custom_performance_thresholds' => array(
                'label' => 'Soglie Performance Personalizzate',
                'description' => 'Usa soglie personalizzate invece di quelle automatiche',
                'type' => 'toggle'
            ),
            'critical_threshold' => array(
                'label' => 'Soglia Critica (ms)',
                'description' => 'Tempo di caricamento considerato critico',
                'type' => 'number',
                'min' => 1000,
                'max' => 10000,
                'step' => 100
            ),
            'warning_threshold' => array(
                'label' => 'Soglia Warning (ms)',
                'description' => 'Tempo di caricamento che genera warning',
                'type' => 'number',
                'min' => 500,
                'max' => 5000,
                'step' => 50
            ),
            'memory_critical_threshold' => array(
                'label' => 'Memoria Critica (MB)',
                'description' => 'Uso memoria considerato critico',
                'type' => 'number',
                'min' => 128,
                'max' => 1024,
                'step' => 16
            ),
            'query_critical_threshold' => array(
                'label' => 'Query Critiche (#)',
                'description' => 'Numero query database considerate critiche',
                'type' => 'number',
                'min' => 50,
                'max' => 500,
                'step' => 10
            ),
            'enable_api_monitoring' => array(
                'label' => 'Monitoraggio API',
                'description' => 'Monitora chiamate API REST e AJAX',
                'type' => 'toggle'
            ),
            'custom_hooks' => array(
                'label' => 'Hook Personalizzati',
                'description' => 'Abilita hook personalizzati per sviluppatori',
                'type' => 'toggle'
            )
        );
        
        foreach ($setting_items as $key => $item) {
            self::render_setting_item($key, $item, $settings[$key] ?? false, 'wsd_advanced_options');
        }
        
        echo '</div>';
        echo '</div>';
        
        submit_button('Salva Impostazioni Avanzate', 'primary', 'submit', false);
    }
    
    private static function render_tools_section() {
        echo '<div class="wsd-settings-section">';
        echo '<div class="wsd-settings-header">';
        echo '<h3>🛠️ Strumenti e Utilità</h3>';
        echo '</div>';
        echo '<div class="wsd-settings-body">';
        
        // Import/Export
        echo '<div class="wsd-import-export">';
        echo '<h4>📁 Import/Export Configurazione</h4>';
        echo '<p>Esporta la configurazione attuale o importa una configurazione salvata:</p>';
        echo '<button id="wsd-export-config" class="button button-secondary">📤 Esporta Configurazione</button>';
        echo '<button id="wsd-import-config" class="button button-secondary">📥 Importa Configurazione</button>';
        echo '<input type="file" id="wsd-import-file" accept=".json" style="display: none;">';
        echo '</div>';
        
        // Reset Settings
        echo '<div class="wsd-import-export" style="background: #fff3cd; border-color: #ffc107;">';
        echo '<h4>🔄 Reset Impostazioni</h4>';
        echo '<p>Ripristina tutte le impostazioni ai valori di default:</p>';
        echo '<button id="wsd-reset-settings" class="button button-secondary" style="color: #856404;">⚠️ Reset Completo</button>';
        echo '</div>';
        
        // Backup & Restore
        echo '<div class="wsd-import-export">';
        echo '<h4>💾 Backup & Restore</h4>';
        echo '<p>Gestisci backup delle configurazioni e log:</p>';
        echo '<button id="wsd-create-backup" class="button button-secondary">💾 Crea Backup</button>';
        echo '<button id="wsd-view-backups" class="button button-secondary">📋 Visualizza Backup</button>';
        echo '</div>';
        
        // System Info
        echo '<div class="wsd-import-export">';
        echo '<h4>ℹ️ Informazioni Sistema</h4>';
        echo '<p>Informazioni dettagliate sul sistema per supporto tecnico:</p>';
        echo '<button id="wsd-system-info" class="button button-secondary">📋 Mostra Info Sistema</button>';
        echo '<button id="wsd-download-debug" class="button button-secondary">📥 Download Debug Info</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    private static function render_setting_item($key, $item, $value, $option_group) {
        echo '<div class="wsd-setting-item">';
        echo '<div class="wsd-setting-label">';
        echo '<strong>' . esc_html($item['label']) . '</strong>';
        echo '<small>' . esc_html($item['description']) . '</small>';
        echo '</div>';
        echo '<div class="wsd-setting-control">';
        
        $field_name = $option_group . '[' . $key . ']';
        
        switch ($item['type']) {
            case 'toggle':
                echo '<label class="wsd-settings-toggle ' . ($value ? 'active' : '') . '">';
                echo '<input type="checkbox" name="' . $field_name . '" value="1" ' . checked($value, true, false) . ' class="wsd-setting-toggle">';
                echo '</label>';
                break;
                
            case 'number':
                echo '<input type="number" name="' . $field_name . '" value="' . esc_attr($value) . '" ';
                echo 'min="' . ($item['min'] ?? 0) . '" ';
                echo 'max="' . ($item['max'] ?? 999999) . '" ';
                echo 'step="' . ($item['step'] ?? 1) . '" ';
                echo 'class="small-text wsd-setting-toggle">';
                break;
                
            case 'select':
                echo '<select name="' . $field_name . '" class="wsd-setting-toggle">';
                foreach ($item['options'] as $opt_value => $opt_label) {
                    echo '<option value="' . esc_attr($opt_value) . '" ' . selected($value, $opt_value, false) . '>';
                    echo esc_html($opt_label);
                    echo '</option>';
                }
                echo '</select>';
                break;
                
            case 'text':
            default:
                echo '<input type="text" name="' . $field_name . '" value="' . esc_attr($value) . '" class="regular-text wsd-setting-toggle">';
                break;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    private static function render_statistics_overview() {
        $stats = self::get_system_statistics();
        
        echo '<div class="wsd-settings-section">';
        echo '<div class="wsd-settings-header">';
        echo '<h3>📊 Panoramica Sistema</h3>';
        echo '</div>';
        echo '<div class="wsd-settings-body">';
        
        echo '<div class="wsd-advanced-stats">';
        
        foreach ($stats as $stat) {
            echo '<div class="wsd-stat-card">';
            echo '<h4>' . esc_html($stat['label']) . '</h4>';
            echo '<div class="wsd-stat-number ' . esc_attr($stat['class']) . '">' . esc_html($stat['value']) . '</div>';
            if (isset($stat['trend'])) {
                echo '<div class="wsd-stat-trend ' . esc_attr($stat['trend']['class']) . '">' . esc_html($stat['trend']['text']) . '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    private static function get_system_statistics() {
        return array(
            array(
                'label' => 'Performance Score',
                'value' => '85/100',
                'class' => 'wsd-score-good',
                'trend' => array('class' => 'wsd-trend-up', 'text' => '↗ +5 questa settimana')
            ),
            array(
                'label' => 'Log Entries (30d)',
                'value' => count(get_option('wsd_performance_log', array())),
                'class' => 'wsd-score-neutral'
            ),
            array(
                'label' => 'Plugin Attivi',
                'value' => count(get_option('active_plugins', array())),
                'class' => 'wsd-score-neutral'
            ),
            array(
                'label' => 'Ultima Manutenzione',
                'value' => self::format_last_maintenance(),
                'class' => 'wsd-score-neutral'
            ),
            array(
                'label' => 'Database Size',
                'value' => self::get_database_size(),
                'class' => 'wsd-score-neutral'
            ),
            array(
                'label' => 'Cache Status',
                'value' => self::get_cache_status(),
                'class' => 'wsd-score-good'
            )
        );
    }
    
    public static function ajax_save_setting() {
        check_ajax_referer('wsd_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        $setting_name = sanitize_text_field($_POST['setting_name']);
        $setting_value = sanitize_text_field($_POST['setting_value']);
        
        // Parse setting name to extract option group and key
        if (strpos($setting_name, '[') !== false) {
            preg_match('/([^[]+)\[([^]]+)\]/', $setting_name, $matches);
            $option_group = $matches[1];
            $setting_key = $matches[2];
            
            $options = get_option($option_group, array());
            $options[$setting_key] = $setting_value;
            
            $updated = update_option($option_group, $options);
            
            if ($updated) {
                wp_send_json_success('Impostazione salvata');
            } else {
                wp_send_json_error('Errore salvataggio');
            }
        } else {
            wp_send_json_error('Nome impostazione non valido');
        }
    }
    
    // Utility functions
    private static function format_last_maintenance() {
        $last = get_option('wsd_last_maintenance', null);
        if (!$last) return 'Mai';
        
        $diff = time() - strtotime($last);
        if ($diff < 3600) return floor($diff / 60) . 'm fa';
        if ($diff < 86400) return floor($diff / 3600) . 'h fa';
        return floor($diff / 86400) . 'd fa';
    }
    
    private static function get_database_size() {
        global $wpdb;
        $size = $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='{$wpdb->dbname}'");
        return $size ? $size . ' MB' : 'N/A';
    }
    
    private static function get_cache_status() {
        if (function_exists('wp_cache_get')) {
            return 'Attivo';
        }
        return 'Non attivo';
    }
}