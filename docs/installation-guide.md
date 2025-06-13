# WSD Pro - Guida Installazione Completa

## 📁 Struttura Plugin Completa

```
woocommerce-speed-doctor/
├── woocommerce-speed-doctor.php          # Plugin principale
├── uninstall.php                         # Script disinstallazione
├── README.md                             # Documentazione completa
├── 
├── assets/                               # Asset frontend/admin
│   ├── admin.css                         # Stili admin dashboard
│   ├── admin.js                          # JavaScript admin
│   ├── widget.css                        # Stili dashboard widget
│   ├── widget.js                         # JavaScript widget
│   └── icon-alert.png                    # Icone notifiche
│
├── includes/                             # Classi core
│   ├── class-wsd-diagnostics.php         # Diagnostica sistema
│   ├── class-wsd-logger.php              # Sistema logging
│   ├── class-wsd-auto-repair.php         # Auto-riparazione
│   ├── class-wsd-dashboard-widget.php    # Widget dashboard
│   ├── class-wsd-email-notifications.php # Notifiche email
│   ├── class-wsd-auto-scheduler.php      # Scheduler automatico
│   ├── class-wsd-settings-manager.php    # Gestione impostazioni
│   ├── class-wsd-developer-api.php       # API sviluppatori
│   └── class-wsd-deployment-config.php   # Configurazioni deploy
│
├── languages/                            # File traduzioni
│   ├── wc-speed-doctor.pot              # Template traduzioni
│   ├── wc-speed-doctor-it_IT.po         # Italiano
│   └── wc-speed-doctor-it_IT.mo         # Italiano compilato
│
├── templates/                            # Template email/output
│   ├── email-alert.php                  # Template email alert
│   ├── email-report.php                 # Template email report
│   └── dashboard-widget.php             # Template widget
│
├── docs/                                 # Documentazione
│   ├── developer-guide.md               # Guida sviluppatori
│   ├── api-reference.md                 # Riferimento API
│   ├── hooks-filters.md                 # Lista hook/filtri
│   └── troubleshooting.md               # Risoluzione problemi
│
└── tests/                                # Test automatici
    ├── test-performance.php             # Test performance
    ├── test-diagnostics.php             # Test diagnostica
    └── test-auto-repair.php             # Test riparazione
```

## 🚀 Processo di Installazione

### Metodo 1: Installazione Standard WordPress

1. **Download del plugin**
   ```bash
   # Scarica o clona repository
   git clone https://github.com/your-repo/woocommerce-speed-doctor.git
   cd woocommerce-speed-doctor
   ```

2. **Upload su WordPress**
   - Comprimi cartella plugin in `woocommerce-speed-doctor.zip`
   - Vai a `Plugin > Aggiungi nuovo > Carica plugin`
   - Seleziona file ZIP e clicca "Installa ora"
   - Clicca "Attiva plugin"

3. **Configurazione automatica**
   - Il plugin rileva automaticamente l'ambiente
   - Applica configurazione ottimale per il tuo setup
   - Dashboard widget appare automaticamente

### Metodo 2: Installazione via FTP

1. **Upload file**
   ```bash
   # Via FTP/SFTP
   scp -r woocommerce-speed-doctor/ user@yoursite.com:/wp-content/plugins/
   ```

2. **Imposta permessi**
   ```bash
   # SSH su server
   chmod -R 755 /wp-content/plugins/woocommerce-speed-doctor/
   chown -R www-data:www-data /wp-content/plugins/woocommerce-speed-doctor/
   ```

3. **Attiva da WordPress Admin**
   - Vai a `Plugin > Plugin installati`
   - Trova "WooCommerce Speed Doctor Pro"
   - Clicca "Attiva"

### Metodo 3: Installazione via WP-CLI

```bash
# Via WP-CLI (metodo preferito per developers)
wp plugin install woocommerce-speed-doctor.zip --activate

# Configura automaticamente per ambiente production
wp option update wsd_environment 'production'

# Esegui primo test performance
wp wsd performance-test

# Abilita notifiche email
wp wsd setup-notifications --email=admin@yourdomain.com
```

## ⚙️ Configurazione Post-Installazione

### 1. Verifica Prerequisiti

```php
// Aggiungi a wp-config.php per ottimizzazione
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Per siti ad alto traffico
define('WSD_HIGH_TRAFFIC_MODE', true);
define('DISABLE_WP_CRON', true); // Usa cron server

// Per sviluppatori
define('WSD_DEBUG', true);
define('WSD_LOG_QUERIES', true);
```

### 2. Configurazione Server (Opzionale ma Raccomandato)

```bash
# Configura cron server (sostituisce WP-Cron)
# Aggiungi a crontab: crontab -e
*/15 * * * * curl -s https://yoursite.com/wp-cron.php > /dev/null

# Per NGINX - ottimizzazione cache
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Per Apache - file .htaccess
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
</IfModule>
```

### 3. Configurazioni Database

```sql
-- Ottimizzazioni MySQL per WooCommerce + WSD
SET GLOBAL innodb_buffer_pool_size = 256M;
SET GLOBAL query_cache_size = 64M;
SET GLOBAL query_cache_type = 1;

-- Indici personalizzati per performance
ALTER TABLE wp_posts ADD INDEX idx_post_type_status (post_type, post_status);
ALTER TABLE wp_postmeta ADD INDEX idx_meta_key_value (meta_key, meta_value(10));
```

## 🔧 Configurazione Ambienti Specifici

### Development Environment
```php
// wp-config.php per sviluppo
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WSD_ENVIRONMENT', 'development');
define('WSD_DEBUG_LEVEL', 'VERBOSE');
define('WSD_AUTO_CONFIGURE', true);

// Configurazione WSD development
$wsd_dev_config = array(
    'monitoring_interval' => 30,
    'log_retention_days' => 7,
    'auto_cleanup' => false,
    'email_notifications' => false
);
```

### Staging Environment
```php
// wp-config.php per staging
define('WSD_ENVIRONMENT', 'staging');
define('WSD_AUTO_CONFIGURE', true);
define('WSD_STAGING_MODE', true);

// Mirror configurazione production ma con alert ridotti
$wsd_staging_config = array(
    'monitoring_interval' => 60,
    'email_notifications' => true,
    'alert_threshold' => 'critical'
);
```

### Production Environment
```php
// wp-config.php per produzione
define('WSD_ENVIRONMENT', 'production');
define('WSD_AUTO_CONFIGURE', true);
define('WSD_PERFORMANCE_MODE', true);

// Configurazione production ottimizzata
$wsd_prod_config = array(
    'monitoring_interval' => 300,
    'log_retention_days' => 30,
    'auto_cleanup' => true,
    'auto_optimization' => true,
    'email_notifications' => true,
    'daily_reports' => true
);
```

## 📊 Verifica Installazione

### 1. Health Check Automatico
```bash
# Via WP-CLI
wp wsd health-check

# Output atteso:
# ✅ Plugin Status: Active
# ✅ Database Tables: Created
# ✅ Cron Events: Scheduled
# ✅ Email Configuration: Valid
# ✅ Performance Baseline: Established
```

### 2. Test Performance Iniziale
```bash
# Esegui primo test completo
wp wsd performance-test --full

# Verifica risultati
wp wsd show-stats --last=24h
```

### 3. Verifica Dashboard
1. Vai a **WordPress Admin > Speed Doctor**
2. Controlla che tutti i componenti siano "✅ OK"
3. Verifica che il widget appaia nella Dashboard
4. Testa "Performance Test" per baseline

## 🔍 Troubleshooting Installazione

### Problemi Comuni

**Error: "Plugin non si attiva"**
```bash
# Verifica permessi file
find /wp-content/plugins/woocommerce-speed-doctor/ -type f -exec chmod 644 {} \;
find /wp-content/plugins/woocommerce-speed-doctor/ -type d -exec chmod 755 {} \;

# Verifica log errori
tail -f /var/log/nginx/error.log
# o
tail -f /var/log/apache2/error.log
```

**Error: "Classi non trovate"**
```php
// Aggiungi a wp-config.php temporaneamente
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verifica autoloader
if (!class_exists('WSD_Auto_Repair')) {
    error_log('WSD: Classe WSD_Auto_Repair non caricata');
}
```

**Error: "Database non accessibile"**
```php
// Test connessione database
global $wpdb;
$result = $wpdb->get_var("SELECT 1");
if ($result !== '1') {
    error_log('WSD: Problema connessione database');
}
```

**Error: "Email non inviate"**
```bash
# Test SMTP
wp wsd test-email --to=test@yourdomain.com

# Verifica configurazione
wp option get wsd_email_notifications
```

### Debug Avanzato

```php
// Abilita debug completo
define('WSD_DEBUG', true);
define('WSD_LOG_ALL_QUERIES', true);
define('WSD_TRACE_MEMORY', true);
define('WSD_PROFILE_HOOKS', true);

// Verifica log debug
tail -f /wp-content/debug.log | grep WSD

// Test specifici
wp wsd debug --component=auto-repair
wp wsd debug --component=email-notifications
wp wsd debug --component=scheduler
```

## 📈 Monitoraggio Post-Installazione

### Prime 24 ore
- [ ] Verifica baseline performance
- [ ] Controlla alert critici
- [ ] Testa notifiche email
- [ ] Monitora uso memoria
- [ ] Verifica cron scheduler

### Prima settimana
- [ ] Analisi trend performance
- [ ] Ottimizzazione plugin rilevati
- [ ] Pulizia database iniziale
- [ ] Configurazione soglie personalizzate
- [ ] Setup backup automatici

### Primo mese
- [ ] Report performance completo
- [ ] Ottimizzazione avanzata database
- [ ] Fine-tuning configurazioni
- [ ] Analisi ROI performance
- [ ] Setup monitoring esterno

## 🚀 Next Steps

1. **Esplora Dashboard**: Familiarizza con tutte le sezioni
2. **Configura Notifiche**: Setup email e alert personalizzati
3. **Prima Ottimizzazione**: Esegui riparazioni automatiche
4. **Monitoring Setup**: Configura dashboard widget
5. **Advanced Config**: Personalizza soglie e parametri

## 📞 Supporto Tecnico

### Auto-diagnosi
```bash
# Genera report completo per supporto
wp wsd generate-support-report

# Include:
# - Configurazione completa
# - Log errori recenti
# - Performance metrics
# - System information
# - Plugin conflicts analysis
```

### Contatti
- **Documentazione**: `/docs/` nella cartella plugin
- **GitHub Issues**: Per bug reports e feature requests
- **Community Forum**: Per discussioni e best practices
- **Enterprise Support**: Per clienti enterprise

---

**🎉 Installazione Completata!**

Il tuo WSD Pro è ora attivo e pronto a ottimizzare le performance del tuo store WooCommerce. Buon lavoro! 🚀