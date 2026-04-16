# Moodle URL Removal — Production Deployment Guide

## Goal
Remove all `/moodle/` references from URLs on production so the platform
appears at `https://www.airpay.academy/` (root domain), not `https://www.airpay.academy/moodle/`.

## Apache Configuration

### Option 1: Install Moodle at document root (Recommended)
```apache
# In Apache vhost config (httpd.conf or sites-available/airpay.conf):
<VirtualHost *:443>
    ServerName www.airpay.academy
    DocumentRoot /var/www/html/moodle    # Point directly to Moodle root
    
    <Directory /var/www/html/moodle>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Then update `config.php`:
```php
$CFG->wwwroot = 'https://www.airpay.academy';  // No /moodle/ suffix
```

### Option 2: Apache Rewrite (If Moodle must stay in /moodle/ subdirectory)
```apache
# .htaccess at document root:
RewriteEngine On

# Rewrite all requests to /moodle/ subdirectory transparently
RewriteCond %{REQUEST_URI} !^/moodle/
RewriteRule ^(.*)$ /moodle/$1 [L,PT]
```

Then update `config.php`:
```php
$CFG->wwwroot = 'https://www.airpay.academy';  // Users see root URLs
```

## Moodle Config Changes
```php
// config.php on production:
$CFG->wwwroot = 'https://www.airpay.academy';  // NOT /moodle/
$CFG->dirroot = '/var/www/html/moodle';        // Physical path stays
```

## Code Already Handled
- Static page links: `index.php` dynamically replaces `/moodle/` with `$CFG->wwwroot`
- All custom plugins use `new moodle_url()` which auto-uses `$CFG->wwwroot`
- All templates use `{{{config.wwwroot}}}` for link generation

## Remaining Manual Checks
After changing `$CFG->wwwroot`:
1. Purge all caches: `php admin/cli/purge_caches.php`
2. Test login flow
3. Test all navbar links
4. Test static pages (privacy, terms, help, contact, dpdp)
5. Test course detail URLs
6. Test certificate download URLs
7. Verify email template links render without /moodle/
