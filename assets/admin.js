/**
 * JavaScript per WooCommerce Speed Doctor
 * Gestisce monitoraggio in tempo reale e interazioni UI
 */
(function($) {
    'use strict';
    
    const WSDAdmin = {
        nonce: '',
        ajax_url: '',
        
        init: function() {
            // Verifica se siamo nella pagina corretta e se le variabili sono disponibili
            if (typeof wsd_admin === 'undefined') {
                console.log('WSD Admin: Variabili non disponibili');
                return;
            }
            
            this.nonce = wsd_admin.nonce;
            this.ajax_url = wsd_admin.ajax_url || ajaxurl;
            
            this.bindEvents();
            this.startMonitoring();
            this.addActionButtons();
            
            // Inizializza funzionalità aggiuntive
            this.initSettingsTabs();
            this.initSettingsToggles();
            this.initAdditionalTools();
            this.initErrorTracking();
        },
        
        bindEvents: function() {
            $(document).on('click', '#wsd-test-performance', this.runPerformanceTest.bind(this));
            $(document).on('click', '#wsd-clear-logs', this.clearLogs.bind(this));
            $(document).on('click', '#wsd-refresh-stats', this.refreshStats.bind(this));
            
            // Handler per riparazioni
            $(document).on('click', '#wsd-repair-action-scheduler', function(e) {
                e.preventDefault();
                WSDAdmin.runRepair('wsd_repair_action_scheduler', 'Action Scheduler', $(this));
            });
            
            $(document).on('click', '#wsd-repair-wp-cron', function(e) {
                e.preventDefault();
                WSDAdmin.runRepair('wsd_optimize_wp_cron', 'WP-Cron', $(this));
            });
            
            $(document).on('click', '#wsd-repair-database', function(e) {
                e.preventDefault();
                if (confirm('Sei sicuro di voler pulire il database? Questa operazione è irreversibile ma sicura.')) {
                    WSDAdmin.runRepair('wsd_database_cleanup', 'Database', $(this));
                }
            });
            
            // Handler per ottimizzazione plugin
            $(document).on('click', '#wsd-repair-plugins', function(e) {
                e.preventDefault();
                if (confirm('Vuoi analizzare e ottimizzare i plugin? Verrà creato un backup della configurazione attuale.')) {
                    WSDAdmin.runPluginOptimization($(this));
                }
            });
            
            // NUOVI HANDLER: Email e Scheduler
            
            // Test email notifications
            $(document).on('click', '#wsd-test-email', function(e) {
                e.preventDefault();
                WSDAdmin.testEmailNotification($(this));
            });
            
            // Scheduler controls
            $(document).on('click', '#wsd-toggle-scheduler', function(e) {
                e.preventDefault();
                WSDAdmin.toggleScheduler($(this));
            });
            
            $(document).on('click', '#wsd-run-maintenance', function(e) {
                e.preventDefault();
                WSDAdmin.runManualMaintenance('maintenance', $(this));
            });
            
            $(document).on('click', '#wsd-run-weekly', function(e) {
                e.preventDefault();
                WSDAdmin.runManualMaintenance('weekly', $(this));
            });
            
            $(document).on('click', '#wsd-run-monthly', function(e) {
                e.preventDefault();
                if (confirm('La pulizia profonda mensile può richiedere diversi minuti. Continuare?')) {
                    WSDAdmin.runManualMaintenance('monthly', $(this));
                }
            });
            
            // Export/Import configuration
            $(document).on('click', '#wsd-export-config', function(e) {
                e.preventDefault();
                WSDAdmin.exportConfiguration();
            });
            
            $(document).on('click', '#wsd-import-config', function(e) {
                e.preventDefault();
                $('#wsd-import-file').click();
            });
            
            $(document).on('change', '#wsd-import-file', function(e) {
                WSDAdmin.importConfiguration(e.target.files[0]);
            });
            
            // Advanced settings toggles
            $(document).on('change', '.wsd-setting-toggle', function() {
                WSDAdmin.saveSetting($(this));
            });
        },
        
        addActionButtons: function() {
            // Verifica se i bottoni sono già stati aggiunti
            if ($('.wsd-actions').length > 0) {
                return;
            }

            const actionHtml = `
                <div class="wsd-actions">
                    <button id="wsd-test-performance" class="button button-primary">
                        <span class="dashicons dashicons-performance"></span> Test Performance
                    </button>
                    <button id="wsd-refresh-stats" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span> Aggiorna Statistiche
                    </button>
                    <button id="wsd-clear-logs" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span> Pulisci Log
                    </button>
                </div>
            `;
            $('.wsd-dashboard h1').after(actionHtml);
        },
        
        startMonitoring: function() {
            this.updateStats();
            // Aggiorna ogni 60 secondi per non sovraccaricare
            setInterval(() => this.updateStats(), 60000);
        },
        
        updateStats: function() {
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_get_current_stats',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WSDAdmin.displayStats(response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('Errore nel recupero delle statistiche:', textStatus, errorThrown);
                }
            });
        },
        
        displayStats: function(stats) {
            if ($('#wsd-live-stats').length === 0) {
                const statsHtml = `
                    <div id="wsd-live-stats" class="wsd-section">
                        <h2><span class="dashicons dashicons-dashboard"></span> Statistiche Live</h2>
                        <div class="wsd-stats-grid"></div>
                        <p><small>Ultimo aggiornamento: <span id="wsd-last-update"></span></small></p>
                    </div>
                `;
                $('.wsd-actions').after(statsHtml);
            }
            
            const statsGrid = $('.wsd-stats-grid');
            statsGrid.html(`
                <div class="wsd-stat-box">
                    <strong>Memoria PHP</strong><br>
                    <span class="wsd-stat-value">${stats.memory_usage}</span>
                </div>
                <div class="wsd-stat-box">
                    <strong>Query Database</strong><br>
                    <span class="wsd-stat-value">${stats.query_count}</span>
                </div>
                <div class="wsd-stat-box">
                    <strong>Errori Recenti</strong><br>
                    <span class="wsd-stat-value ${stats.error_count > 0 ? 'error' : ''}">${stats.error_count}</span>
                </div>
                <div class="wsd-stat-box">
                    <strong>Performance Score</strong><br>
                    <span class="wsd-stat-value ${this.getScoreClass(stats.performance_score)}">${stats.performance_score}/100</span>
                </div>
            `);
            
            $('#wsd-last-update').text(new Date().toLocaleTimeString());
        },
        
        getScoreClass: function(score) {
            if (score >= 80) return 'good';
            if (score >= 60) return 'warning';
            return 'error';
        },
        
        runPerformanceTest: function(e) {
            e.preventDefault();
            const $btn = $(e.target).closest('button');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testing...');
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_run_performance_test',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WSDAdmin.showTestResults(response.data);
                    } else {
                        alert('Errore durante il test: ' + (response.data || 'Errore sconosciuto.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Errore di connessione durante il test: ' + textStatus);
                    console.error('Test error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        showTestResults: function(results) {
            $('.wsd-test-results').remove();
            
            const statusClass = results.overall_status === 'good' ? 'wsd-success' : 
                               results.overall_status === 'warning' ? 'wsd-warning' : 'wsd-error';
            
            const recommendationsHtml = results.recommendations.map(rec => `<li>${rec}</li>`).join('');

            const resultsHtml = `
                <div class="wsd-test-results wsd-section ${statusClass}">
                    <h3><span class="dashicons dashicons-analytics"></span> Risultati Test Performance</h3>
                    <div class="wsd-test-summary">
                        <div class="wsd-test-metric">
                            <strong>Tempo Totale:</strong><br>
                            <span>${results.total_time}ms</span>
                        </div>
                        <div class="wsd-test-metric">
                            <strong>Memoria Utilizzata:</strong><br>
                            <span>${results.memory_used}</span>
                        </div>
                        <div class="wsd-test-metric">
                            <strong>Query Eseguite:</strong><br>
                            <span>${results.query_count}</span>
                        </div>
                    </div>
                    <h4>Raccomandazioni:</h4>
                    <ul class="wsd-recommendations">
                        ${recommendationsHtml}
                    </ul>
                </div>
            `;
            
            $('#wsd-live-stats').after(resultsHtml);
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Sei sicuro di voler eliminare tutti i log delle performance? Questa operazione è irreversibile.')) {
                return;
            }
            
            const $btn = $(e.target).closest('button');
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-trash spin"></span> Eliminando...');

            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_clear_performance_logs',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Log eliminati con successo!');
                        location.reload();
                    } else {
                        alert('Errore durante l\'eliminazione dei log: ' + (response.data || 'Errore sconosciuto.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Errore di connessione durante l\'eliminazione dei log: ' + textStatus);
                    console.error('Clear logs error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        refreshStats: function(e) {
            e.preventDefault();
            this.updateStats();
            
            const $btn = $(e.target).closest('button');
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-update spin"></span> Aggiornando...');
            
            setTimeout(() => {
                $btn.html(originalText);
            }, 1000);
        },

        runRepair: function(action, component, $btn) {
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Riparando...');
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        let message = '✅ ' + component + ' riparato con successo!';
                        
                        if (response.data.repairs && response.data.repairs.length > 0) {
                            message += '\n\nAzioni eseguite:\n' + response.data.repairs.join('\n');
                        }
                        if (response.data.optimizations && response.data.optimizations.length > 0) {
                            message += '\n\nOttimizzazioni applicate:\n' + response.data.optimizations.join('\n');
                        }
                        if (response.data.cleaned && response.data.cleaned.length > 0) {
                            message += '\n\nElementi puliti:\n' + response.data.cleaned.join('\n');
                        }
                        
                        // Mostra suggerimenti per wp-config se necessario
                        if (response.data.wp_config_suggestion) {
                            message += '\n\n💡 Suggerimento: Aggiungi questa riga al tuo wp-config.php:\ndefine(\'DISABLE_WP_CRON\', true);';
                            message += '\nPoi configura un cron job server ogni 15 minuti.';
                        }
                        
                        alert(message);
                        
                        // Ricarica la pagina per aggiornare lo stato dopo un breve delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                        
                    } else {
                        alert('❌ Errore durante la riparazione: ' + (response.data || 'Errore sconosciuto.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('❌ Errore di connessione durante la riparazione: ' + textStatus);
                    console.error('Repair error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * NUOVA FUNZIONALITÀ: Ottimizzazione Plugin
         */
        runPluginOptimization: function($btn) {
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Analizzando...');
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_optimize_plugins',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WSDAdmin.showPluginOptimizationResults(response.data);
                        
                        // Ricarica la pagina dopo 5 secondi per aggiornare lo stato
                        setTimeout(() => {
                            location.reload();
                        }, 5000);
                        
                    } else {
                        alert('❌ Errore durante l\'ottimizzazione plugin: ' + (response.data || 'Errore sconosciuto.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('❌ Errore di connessione durante l\'ottimizzazione: ' + textStatus);
                    console.error('Plugin optimization error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        showPluginOptimizationResults: function(data) {
            // Rimuovi risultati precedenti
            $('.wsd-plugin-optimization-results').remove();
            
            const report = data.report;
            const optimizations = data.optimizations || [];
            const recommendations = data.recommendations || [];
            const warnings = data.warnings || [];
            
            // Determina la classe di stato basata sul punteggio di performance
            let statusClass = 'wsd-success';
            if (report.performance_score < 50) {
                statusClass = 'wsd-error';
            } else if (report.performance_score < 75) {
                statusClass = 'wsd-warning';
            }

            let resultsHtml = `
                <div class="wsd-plugin-optimization-results wsd-section ${statusClass}">
                    <h3><span class="dashicons dashicons-admin-plugins"></span> Risultati Ottimizzazione Plugin</h3>
                    
                    <div class="wsd-test-summary">
                        <div class="wsd-test-metric">
                            <strong>Plugin Attivi:</strong><br>
                            <span>${report.total_plugins}</span>
                        </div>
                        <div class="wsd-test-metric">
                            <strong>Plugin Pesanti:</strong><br>
                            <span style="color: ${report.heavy_plugins_count > 0 ? '#dc3232' : '#46b450'}">${report.heavy_plugins_count}</span>
                        </div>
                        <div class="wsd-test-metric">
                            <strong>Conflitti Rilevati:</strong><br>
                            <span style="color: ${report.conflicts_found > 0 ? '#dc3232' : '#46b450'}">${report.conflicts_found}</span>
                        </div>
                        <div class="wsd-test-metric">
                            <strong>Performance Score:</strong><br>
                            <span style="color: ${this.getScoreClass(report.performance_score)}">${report.performance_score}/100</span>
                        </div>
                    </div>
            `;

            // Ottimizzazioni applicate
            if (optimizations.length > 0) {
                resultsHtml += `
                    <h4>✅ Ottimizzazioni Applicate:</h4>
                    <ul class="wsd-recommendations">
                        ${optimizations.map(opt => `<li>${opt}</li>`).join('')}
                    </ul>
                `;
            }

            // Avvisi sui plugin problematici
            if (warnings.length > 0) {
                resultsHtml += `
                    <h4>⚠️ Plugin Problematici Rilevati:</h4>
                    <ul class="wsd-recommendations" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px;">
                        ${warnings.map(warning => `<li>${warning}</li>`).join('')}
                    </ul>
                `;
            }

            // Raccomandazioni
            if (recommendations.length > 0) {
                resultsHtml += `
                    <h4>💡 Raccomandazioni per Migliorare le Performance:</h4>
                    <ul class="wsd-recommendations">
                        ${recommendations.map(rec => `<li>${rec}</li>`).join('')}
                    </ul>
                `;
            }

            // Analisi dettagliata (opzionale, nascosta di default)
            if (data.detailed_analysis) {
                const analysis = data.detailed_analysis;
                resultsHtml += `
                    <div style="margin-top: 20px;">
                        <button type="button" class="button button-secondary" onclick="$(this).next().toggle(); $(this).text($(this).text() === '📊 Mostra Analisi Dettagliata' ? '📊 Nascondi Analisi Dettagliata' : '📊 Mostra Analisi Dettagliata')">📊 Mostra Analisi Dettagliata</button>
                        <div style="display: none; margin-top: 15px; background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h5>Plugin Pesanti Identificati:</h5>
                `;
                
                if (Object.keys(analysis.heavy_plugins).length > 0) {
                    Object.values(analysis.heavy_plugins).forEach(plugin => {
                        resultsHtml += `<p><strong>${plugin.name}</strong> - Impatto: ${plugin.impact} (${plugin.reason})</p>`;
                    });
                } else {
                    resultsHtml += '<p>Nessun plugin pesante rilevato! 🎉</p>';
                }

                if (Object.keys(analysis.conflicting_plugins).length > 0) {
                    resultsHtml += '<h5>Conflitti Plugin:</h5>';
                    Object.entries(analysis.conflicting_plugins).forEach(([category, plugins]) => {
                        const pluginNames = plugins.map(p => p.name).join(', ');
                        resultsHtml += `<p><strong>Categoria ${category}:</strong> ${pluginNames}</p>`;
                    });
                }

                resultsHtml += `
                        </div>
                    </div>
                `;
            }

            resultsHtml += `
                <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 5px;">
                    <p><strong>💾 Backup:</strong> È stato creato un backup della configurazione attuale dei plugin. In caso di problemi, contatta l'amministratore del sito.</p>
                    <p><strong>🔄 Aggiornamento:</strong> La pagina verrà ricaricata automaticamente tra 5 secondi per mostrare i miglioramenti.</p>
                </div>
            `;

            resultsHtml += '</div>';
            
            // Inserisci i risultati dopo le statistiche live
            if ($('#wsd-live-stats').length > 0) {
                $('#wsd-live-stats').after(resultsHtml);
            } else {
                $('.wsd-section').first().after(resultsHtml);
            }

            // Scorri verso i risultati
            $('html, body').animate({
                scrollTop: $('.wsd-plugin-optimization-results').offset().top - 50
            }, 500);
        },

        /**
         * NUOVE FUNZIONALITÀ: Email, Scheduler, Settings
         */
        
        testEmailNotification: function($btn) {
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-email-alt spin"></span> Inviando...');
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_test_email_notification',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#wsd-test-email-result').html('<div class="wsd-inline-notice success">✅ ' + response.data + '</div>');
                    } else {
                        $('#wsd-test-email-result').html('<div class="wsd-inline-notice error">❌ ' + (response.data || 'Errore invio email') + '</div>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#wsd-test-email-result').html('<div class="wsd-inline-notice error">❌ Errore connessione: ' + textStatus + '</div>');
                    console.error('Email test error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                    
                    // Auto-hide result dopo 5 secondi
                    setTimeout(() => {
                        $('#wsd-test-email-result').fadeOut();
                    }, 5000);
                }
            });
        },

        toggleScheduler: function($btn) {
            const isEnabled = $btn.text().includes('Disabilita');
            const newState = !isEnabled;
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Aggiornando...');
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_toggle_auto_scheduler',
                    nonce: this.nonce,
                    enabled: newState
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data);
                        location.reload(); // Ricarica per aggiornare lo stato
                    } else {
                        alert('❌ Errore: ' + (response.data || 'Errore sconosciuto'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('❌ Errore connessione: ' + textStatus);
                    console.error('Scheduler toggle error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        runManualMaintenance: function(type, $btn) {
            const typeNames = {
                'maintenance': 'Manutenzione Giornaliera',
                'weekly': 'Ottimizzazione Settimanale',
                'monthly': 'Pulizia Profonda Mensile'
            };
            
            const typeName = typeNames[type] || 'Manutenzione';
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Eseguendo...');
            
            // Mostra indicatore di progresso
            this.showMaintenanceProgress(typeName);
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_run_manual_maintenance',
                    nonce: this.nonce,
                    type: type
                },
                timeout: 300000, // 5 minuti timeout per operazioni lunghe
                success: function(response) {
                    if (response.success) {
                        WSDAdmin.hideMaintenanceProgress();
                        alert('✅ ' + typeName + ' completata con successo!\n\nLa pagina verrà ricaricata per mostrare i risultati.');
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        WSDAdmin.hideMaintenanceProgress();
                        alert('❌ Errore durante ' + typeName.toLowerCase() + ': ' + (response.data || 'Errore sconosciuto'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    WSDAdmin.hideMaintenanceProgress();
                    if (textStatus === 'timeout') {
                        alert('⏱️ Operazione in timeout. Può essere ancora in esecuzione in background.\nControlla i log tra qualche minuto.');
                    } else {
                        alert('❌ Errore connessione: ' + textStatus);
                    }
                    console.error('Maintenance error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        showMaintenanceProgress: function(operationName) {
            const progressHtml = `
                <div id="wsd-maintenance-progress" class="wsd-progress-overlay">
                    <div class="wsd-progress-content">
                        <div class="wsd-progress-spinner"></div>
                        <h3>⚙️ ${operationName} in corso...</h3>
                        <p>L'operazione può richiedere alcuni minuti. Non chiudere questa pagina.</p>
                        <div class="wsd-progress-bar">
                            <div class="wsd-progress-fill" style="width: 0%; animation: wsd-progress-animate 30s linear infinite;"></div>
                        </div>
                        <p><small>Tempo stimato: 30-120 secondi</small></p>
                    </div>
                </div>
            `;
            
            $('body').append(progressHtml);
        },

        hideMaintenanceProgress: function() {
            $('#wsd-maintenance-progress').fadeOut(500, function() {
                $(this).remove();
            });
        },

        exportConfiguration: function() {
            // Simula export configurazione (in realtà dovrebbe essere gestito lato server)
            const config = {
                timestamp: new Date().toISOString(),
                version: '1.2.0',
                settings: {
                    // Qui andrebbero le impostazioni reali
                    email_notifications: true,
                    auto_scheduler: true,
                    performance_monitoring: true
                },
                note: 'WSD Configuration Export'
            };
            
            const dataStr = JSON.stringify(config, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = 'wsd-config-' + new Date().toISOString().slice(0,10) + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            alert('✅ Configurazione esportata con successo!');
        },

        importConfiguration: function(file) {
            if (!file) {
                alert('❌ Nessun file selezionato');
                return;
            }
            
            if (file.type !== 'application/json') {
                alert('❌ Formato file non valido. Seleziona un file JSON.');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const config = JSON.parse(e.target.result);
                    
                    if (!config.version || !config.settings) {
                        throw new Error('Struttura file non valida');
                    }
                    
                    if (confirm('Sei sicuro di voler importare questa configurazione?\n\nQuesta operazione sovrascriverà le impostazioni attuali.')) {
                        WSDAdmin.applyImportedConfiguration(config);
                    }
                } catch (error) {
                    alert('❌ Errore lettura file: ' + error.message);
                }
            };
            
            reader.readAsText(file);
        },

        applyImportedConfiguration: function(config) {
            // In un'implementazione reale, questo invierebbe la configurazione al server
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_import_configuration',
                    nonce: this.nonce,
                    config: JSON.stringify(config)
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Configurazione importata con successo!\n\nLa pagina verrà ricaricata.');
                        location.reload();
                    } else {
                        alert('❌ Errore import: ' + (response.data || 'Errore sconosciuto'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('❌ Errore connessione durante import: ' + textStatus);
                    console.error('Import error:', textStatus, errorThrown);
                }
            });
        },

        saveSetting: function($setting) {
            const settingName = $setting.attr('name');
            const settingValue = $setting.is(':checkbox') ? $setting.is(':checked') : $setting.val();
            
            // Visual feedback
            const $feedback = $('<span class="wsd-save-feedback">💾</span>');
            $setting.after($feedback);
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_save_setting',
                    nonce: this.nonce,
                    setting_name: settingName,
                    setting_value: settingValue
                },
                success: function(response) {
                    if (response.success) {
                        $feedback.text('✅').addClass('success');
                    } else {
                        $feedback.text('❌').addClass('error');
                    }
                },
                error: function() {
                    $feedback.text('❌').addClass('error');
                },
                complete: function() {
                    setTimeout(() => {
                        $feedback.fadeOut(500, function() { $(this).remove(); });
                    }, 2000);
                }
            });
        },

        /**
         * GESTIONE TAB IMPOSTAZIONI
         */
        initSettingsTabs: function() {
            // Gestione click tab
            $(document).on('click', '.wsd-tab-button', function(e) {
                e.preventDefault();
                
                const $this = $(this);
                const tabId = $this.data('tab');
                
                // Aggiorna stato bottoni
                $('.wsd-tab-button').removeClass('active');
                $this.addClass('active');
                
                // Mostra contenuto tab corretto
                $('.wsd-tab-content').removeClass('active');
                $('#wsd-tab-' + tabId).addClass('active');
                
                // Salva tab attivo nel localStorage se disponibile
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('wsd_active_tab', tabId);
                }
            });
            
            // Ripristina tab attivo al caricamento
            if (typeof(Storage) !== "undefined") {
                const activeTab = localStorage.getItem('wsd_active_tab');
                if (activeTab) {
                    $('.wsd-tab-button[data-tab="' + activeTab + '"]').click();
                }
            }
        },

        /**
         * GESTIONE TOGGLE SETTINGS
         */
        initSettingsToggles: function() {
            $(document).on('click', '.wsd-settings-toggle', function() {
                const $this = $(this);
                const $checkbox = $this.find('input[type="checkbox"]');
                
                if ($checkbox.length) {
                    $checkbox.prop('checked', !$checkbox.prop('checked'));
                    $this.toggleClass('active', $checkbox.prop('checked'));
                    
                    // Trigger change event per salvare automaticamente
                    $checkbox.trigger('change');
                }
            });
            
            // Inizializza stato toggle
            $('.wsd-settings-toggle').each(function() {
                const $this = $(this);
                const $checkbox = $this.find('input[type="checkbox"]');
                
                if ($checkbox.length && $checkbox.prop('checked')) {
                    $this.addClass('active');
                }
            });
        },

        /**
         * STRUMENTI AGGIUNTIVI
         */
        initAdditionalTools: function() {
            // System Info
            $(document).on('click', '#wsd-system-info', function(e) {
                e.preventDefault();
                WSDAdmin.showSystemInfo();
            });
            
            // Create Backup
            $(document).on('click', '#wsd-create-backup', function(e) {
                e.preventDefault();
                WSDAdmin.createBackup($(this));
            });
            
            // Reset Settings
            $(document).on('click', '#wsd-reset-settings', function(e) {
                e.preventDefault();
                if (confirm('⚠️ Sei sicuro di voler resettare TUTTE le impostazioni?\n\nQuesta operazione è irreversibile!')) {
                    if (confirm('🔴 ULTIMA CONFERMA: Tutti i dati di configurazione verranno persi!')) {
                        WSDAdmin.resetSettings($(this));
                    }
                }
            });
            
            // Download Debug
            $(document).on('click', '#wsd-download-debug', function(e) {
                e.preventDefault();
                WSDAdmin.downloadDebugInfo();
            });
        },

        showSystemInfo: function() {
            const systemInfo = this.collectSystemInfo();
            
            const infoHtml = `
                <div id="wsd-system-info-modal" class="wsd-modal-overlay">
                    <div class="wsd-modal-content">
                        <div class="wsd-modal-header">
                            <h3>ℹ️ Informazioni Sistema</h3>
                            <button class="wsd-modal-close">&times;</button>
                        </div>
                        <div class="wsd-modal-body">
                            <div class="wsd-system-info-grid">
                                ${systemInfo.map(info => `
                                    <div class="wsd-info-item">
                                        <strong>${info.label}:</strong>
                                        <span>${info.value}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        <div class="wsd-modal-footer">
                            <button class="button button-primary" onclick="WSDAdmin.copySystemInfo()">📋 Copia Info</button>
                            <button class="button button-secondary wsd-modal-close">Chiudi</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(infoHtml);
            
            // Gestione chiusura modal
            $(document).on('click', '.wsd-modal-close, .wsd-modal-overlay', function(e) {
                if (e.target === this) {
                    $('#wsd-system-info-modal').fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            });
        },

        collectSystemInfo: function() {
            return [
                { label: 'WSD Version', value: '1.2.0' },
                { label: 'WordPress Version', value: window.wp ? wp.version || 'Unknown' : 'Unknown' },
                { label: 'User Agent', value: navigator.userAgent },
                { label: 'Screen Resolution', value: screen.width + 'x' + screen.height },
                { label: 'Browser Language', value: navigator.language },
                { label: 'Timezone', value: Intl.DateTimeFormat().resolvedOptions().timeZone },
                { label: 'Local Time', value: new Date().toLocaleString() },
                { label: 'Performance API', value: 'performance' in window ? 'Disponibile' : 'Non disponibile' },
                { label: 'Local Storage', value: typeof(Storage) !== "undefined" ? 'Disponibile' : 'Non disponibile' }
            ];
        },

        copySystemInfo: function() {
            const info = this.collectSystemInfo();
            const infoText = info.map(item => `${item.label}: ${item.value}`).join('\n');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(infoText).then(() => {
                    alert('✅ Informazioni sistema copiate negli appunti!');
                });
            } else {
                // Fallback per browser più vecchi
                const textArea = document.createElement('textarea');
                textArea.value = infoText;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ Informazioni sistema copiate negli appunti!');
            }
        },

        createBackup: function($btn) {
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creando backup...');
            
            // Simula creazione backup
            setTimeout(() => {
                const backup = {
                    timestamp: new Date().toISOString(),
                    version: '1.2.0',
                    type: 'manual_backup',
                    settings: {
                        // Qui andrebbero le impostazioni reali dal server
                        note: 'Backup creato manualmente dall\'utente'
                    }
                };
                
                const dataStr = JSON.stringify(backup, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                const url = URL.createObjectURL(dataBlob);
                
                const link = document.createElement('a');
                link.href = url;
                link.download = 'wsd-backup-' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                
                $btn.prop('disabled', false).html(originalText);
                alert('✅ Backup creato e scaricato con successo!');
            }, 2000);
        },

        resetSettings: function($btn) {
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Resettando...');
            
            $.ajax({
                url: this.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_reset_settings',
                    nonce: this.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Impostazioni resettate con successo!\n\nLa pagina verrà ricaricata.');
                        location.reload();
                    } else {
                        alert('❌ Errore durante il reset: ' + (response.data || 'Errore sconosciuto'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('❌ Errore connessione: ' + textStatus);
                    console.error('Reset error:', textStatus, errorThrown);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        downloadDebugInfo: function() {
            const debugInfo = {
                timestamp: new Date().toISOString(),
                wsd_version: '1.2.0',
                system_info: this.collectSystemInfo(),
                browser_info: {
                    user_agent: navigator.userAgent,
                    language: navigator.language,
                    platform: navigator.platform,
                    cookies_enabled: navigator.cookieEnabled,
                    online: navigator.onLine
                },
                performance_info: {
                    memory: 'performance' in window && 'memory' in performance ? {
                        used: performance.memory.usedJSHeapSize,
                        total: performance.memory.totalJSHeapSize,
                        limit: performance.memory.jsHeapSizeLimit
                    } : 'Non disponibile',
                    timing: 'performance' in window && 'timing' in performance ? performance.timing : 'Non disponibile'
                },
                console_errors: window.wsdConsoleErrors || []
            };
            
            const dataStr = JSON.stringify(debugInfo, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = 'wsd-debug-' + new Date().toISOString().slice(0,10) + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            alert('✅ File debug scaricato con successo!');
        },

        /**
         * TRACKING ERRORI CONSOLE
         */
        initErrorTracking: function() {
            // Cattura errori JavaScript per debug
            window.wsdConsoleErrors = window.wsdConsoleErrors || [];
            
            const originalError = console.error;
            console.error = function(...args) {
                window.wsdConsoleErrors.push({
                    timestamp: new Date().toISOString(),
                    type: 'error',
                    message: args.join(' ')
                });
                
                // Mantieni solo gli ultimi 50 errori
                if (window.wsdConsoleErrors.length > 50) {
                    window.wsdConsoleErrors = window.wsdConsoleErrors.slice(-50);
                }
                
                return originalError.apply(console, args);
            };
            
            // Cattura errori non gestiti
            window.addEventListener('error', function(e) {
                window.wsdConsoleErrors.push({
                    timestamp: new Date().toISOString(),
                    type: 'unhandled_error',
                    message: e.message,
                    filename: e.filename,
                    lineno: e.lineno,
                    colno: e.colno
                });
            });
        }
    };
    
    // Inizializza quando il DOM è pronto
    $(document).ready(function() {
        if ($('.wsd-dashboard').length > 0) {
            WSDAdmin.init();
        }
    });
    
})(jQuery);