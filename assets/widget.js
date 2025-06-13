/**
 * JavaScript per WSD Dashboard Widget
 * Gestisce auto-refresh, aggiornamenti live e interazioni
 */
(function($) {
    'use strict';
    
    const WSDWidget = {
        refreshInterval: null,
        isRefreshing: false,
        settings: {
            auto_refresh: true,
            refresh_interval: 300000, // 5 minuti di default
            show_alerts: true
        },
        
        init: function() {
            // Verifica se siamo nella dashboard e se il widget esiste
            if (!$('#wsd_performance_widget').length) {
                return;
            }
            
            this.bindEvents();
            this.loadSettings();
            this.startAutoRefresh();
            this.initScoreCircle();
        },
        
        bindEvents: function() {
            // Refresh manuale
            $(document).on('click', '.wsd-widget-refresh', this.manualRefresh.bind(this));
            
            // Hover effects per componenti
            $(document).on('mouseenter', '.wsd-component-status', function() {
                const $this = $(this);
                const title = $this.attr('title');
                if (title) {
                    $this.append('<div class="wsd-tooltip">' + title + '</div>');
                }
            });
            
            $(document).on('mouseleave', '.wsd-component-status', function() {
                $(this).find('.wsd-tooltip').remove();
            });
            
            // Click su componenti per andare alla dashboard
            $(document).on('click', '.wsd-component-status.wsd-component-critical, .wsd-component-status.wsd-component-warning', function() {
                window.open(wsd_widget.dashboard_url || 'admin.php?page=wsd-speed-doctor-main', '_blank');
            });
        },
        
        loadSettings: function() {
            // In un'implementazione reale, questi dati verrebbero caricati dal server
            // Per ora usiamo i valori di default
            if (typeof wsd_widget_settings !== 'undefined') {
                this.settings = $.extend(this.settings, wsd_widget_settings);
            }
        },
        
        startAutoRefresh: function() {
            if (!this.settings.auto_refresh) {
                return;
            }
            
            // Pulisci interval esistente
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
            
            // Avvia nuovo interval
            this.refreshInterval = setInterval(() => {
                this.autoRefresh();
            }, this.settings.refresh_interval);
        },
        
        manualRefresh: function(e) {
            e.preventDefault();
            
            if (this.isRefreshing) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            this.startRefreshUI($btn);
            this.performRefresh().always(() => {
                this.endRefreshUI($btn);
            });
        },
        
        autoRefresh: function() {
            if (this.isRefreshing) {
                return;
            }
            
            this.performRefresh();
        },
        
        performRefresh: function() {
            this.isRefreshing = true;
            
            return $.ajax({
                url: wsd_widget.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsd_widget_refresh',
                    nonce: wsd_widget.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateWidget(response.data);
                    } else {
                        console.error('WSD Widget: Errore refresh', response.data);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('WSD Widget: Errore AJAX', textStatus, errorThrown);
                },
                complete: () => {
                    this.isRefreshing = false;
                }
            });
        },
        
        updateWidget: function(data) {
            const $container = $('.wsd-widget-container');
            
            // Aggiungi classe per animazione
            $container.addClass('wsd-widget-updated');
            
            // Aggiorna score circle
            this.updateScoreCircle(data.performance_score);
            
            // Aggiorna statistiche
            this.updateStats(data);
            
            // Aggiorna status componenti
            this.updateComponentStatus(data.health_status);
            
            // Aggiorna timestamp
            $('.wsd-last-update').text(data.last_update);
            
            // Rimuovi classe animazione dopo un breve delay
            setTimeout(() => {
                $container.removeClass('wsd-widget-updated');
            }, 500);
            
            // Mostra notifiche se necessario
            this.checkForAlerts(data);
        },
        
        updateScoreCircle: function(score) {
            const $circle = $('.wsd-score-circle');
            const $number = $('.wsd-score-number');
            
            // Aggiorna numero
            $number.text(score);
            
            // Aggiorna classe di stato
            $circle.removeClass('excellent good warning critical');
            
            let newClass = 'critical';
            if (score >= 85) newClass = 'excellent';
            else if (score >= 70) newClass = 'good';
            else if (score >= 50) newClass = 'warning';
            
            $circle.addClass(newClass);
            
            // Aggiorna variabile CSS per il gradiente circolare
            const percentage = Math.round((score / 100) * 360);
            $circle[0].style.setProperty('--score-percentage', percentage + 'deg');
        },
        
        updateStats: function(data) {
            // Aggiorna memoria
            $('.wsd-stat-item').eq(0).find('.wsd-stat-value').text(data.memory_usage);
            
            // Aggiorna plugin count
            $('.wsd-stat-item').eq(1).find('.wsd-stat-value').text(data.active_plugins);
            
            // Aggiorna recent issues con classe appropriata
            const $issuesValue = $('.wsd-stat-item').eq(2).find('.wsd-stat-value');
            $issuesValue.text(data.recent_issues)
                       .removeClass('good warning error')
                       .addClass(data.recent_issues === 0 ? 'good' : (data.recent_issues > 5 ? 'error' : 'warning'));
        },
        
        updateComponentStatus: function(healthStatus) {
            $('.wsd-component-status').each(function() {
                const $component = $(this);
                const componentName = $component.find('.wsd-component-name').text().toLowerCase();
                
                // Mappa i nomi visualizzati ai nomi dei componenti
                const componentMap = {
                    'scheduler': 'action_scheduler',
                    'cron': 'wp_cron',
                    'plugin': 'plugins',
                    'database': 'database'
                };
                
                const actualComponent = componentMap[componentName] || componentName;
                
                if (healthStatus[actualComponent]) {
                    const status = healthStatus[actualComponent].status;
                    
                    // Rimuovi classi di stato precedenti
                    $component.removeClass('wsd-component-good wsd-component-warning wsd-component-critical wsd-component-not_available');
                    
                    // Aggiungi nuova classe
                    $component.addClass('wsd-component-' + status);
                    
                    // Aggiorna tooltip
                    $component.attr('title', healthStatus[actualComponent].message);
                }
            });
        },
        
        checkForAlerts: function(data) {
            if (!this.settings.show_alerts) {
                return;
            }
            
            // Conta problemi critici
            let criticalCount = 0;
            let warningCount = 0;
            
            Object.values(data.health_status).forEach(status => {
                if (status.status === 'critical') criticalCount++;
                else if (status.status === 'warning') warningCount++;
            });
            
            // Rimuovi alert esistenti
            $('.wsd-widget-alert').remove();
            
            // Aggiungi nuovi alert se necessario
            if (criticalCount > 0) {
                this.showAlert('critical', `🚨 ${criticalCount} problema/i critico/i rilevato/i!`, 'Risolvi ora →');
            } else if (data.recent_issues > 0) {
                this.showAlert('warning', `⚠️ ${data.recent_issues} issue/s performance nelle ultime 24h`, 'Analizza →');
            }
            
            // Mostra notifica browser se critico e supportato
            if (criticalCount > 0 && 'Notification' in window && Notification.permission === 'granted') {
                new Notification('WSD Speed Doctor Alert', {
                    body: `${criticalCount} problema/i critico/i rilevato/i sul tuo sito`,
                    icon: wsd_widget.plugin_url + '/assets/icon-alert.png'
                });
            }
        },
        
        showAlert: function(type, message, linkText) {
            const alertHtml = `
                <div class="wsd-widget-alert ${type}">
                    <strong>${message}</strong>
                    <a href="${wsd_widget.dashboard_url || 'admin.php?page=wsd-speed-doctor-main'}" class="wsd-alert-link">${linkText}</a>
                </div>
            `;
            
            $('.wsd-widget-components').after(alertHtml);
        },
        
        initScoreCircle: function() {
            // Inizializza il gradiente circolare basato sul score attuale
            const $circle = $('.wsd-score-circle');
            const score = parseInt($('.wsd-score-number').text()) || 0;
            const percentage = Math.round((score / 100) * 360);
            
            if ($circle.length && $circle[0].style) {
                $circle[0].style.setProperty('--score-percentage', percentage + 'deg');
            }
        },
        
        startRefreshUI: function($btn) {
            $btn.addClass('loading')
                .html('🔄 <span class="wsd-loading-indicator"></span>')
                .prop('disabled', true);
        },
        
        endRefreshUI: function($btn) {
            $btn.removeClass('loading')
                .html('🔄')
                .prop('disabled', false);
        },
        
        requestNotificationPermission: function() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }
    };
    
    // Inizializza quando il DOM è pronto
    $(document).ready(function() {
        WSDWidget.init();
        
        // Richiedi permesso notifiche dopo un breve delay
        setTimeout(() => {
            WSDWidget.requestNotificationPermission();
        }, 2000);
    });
    
    // Cleanup quando si lascia la pagina
    $(window).on('beforeunload', function() {
        if (WSDWidget.refreshInterval) {
            clearInterval(WSDWidget.refreshInterval);
        }
    });
    
    // Gestisci visibilità pagina per ottimizzare refresh
    if (typeof document.addEventListener !== 'undefined') {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Pausa auto-refresh quando la pagina non è visibile
                if (WSDWidget.refreshInterval) {
                    clearInterval(WSDWidget.refreshInterval);
                    WSDWidget.refreshInterval = null;
                }
            } else {
                // Riprendi auto-refresh quando la pagina torna visibile
                WSDWidget.startAutoRefresh();
                // Esegui refresh immediato
                setTimeout(() => {
                    WSDWidget.autoRefresh();
                }, 1000);
            }
        });
    }
    
    // Esponi globalmente per debug (solo in development)
    if (typeof window.console !== 'undefined') {
        window.WSDWidget = WSDWidget;
    }
    
})(jQuery);