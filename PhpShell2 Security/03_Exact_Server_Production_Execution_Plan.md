# Production Server Exact Execution Plan: Laravel 9.52.21 on Ubuntu Focal + Apache

**Date Recorded:** October 30, 2026  
**Server:** `rrn2-ubuntu-s-1vcpu-1gb-intel-blr1-01` (4GB RAM active, 22GB Disk free)  
**Application:** Laravel Framework `9.52.21` (`/var/www/html/prod/`)  
**Web Server:** Apache 2 with `mpm_prefork` and `libapache2-mod-php8.1`  
**Databases in use:** MySQL / PDO (`pdo_mysql`) + MongoDB (`mongodb` extension)  
**Current State:** 🚨 `session.upload_progress.enabled = On` (HIGH VULNERABILITY EXPOSURE)

---

## 1. Key Insights from Your Server Diagnostics

1. **Active Configuration:**
   - Apache is currently using `mpm_prefork_module` and `php8.1` via `libapache2-mod-php8.1`.
   - `session.upload_progress.enabled` is currently **On** with a `200M` post limit. This leaves CVE-2026-115560 and CVE-2026-109618 exposed.
2. **Framework Compatibility (Critical!):**
   - Your application is **Laravel 9.52.21**.
   - **Laravel 9 natively supports PHP 8.0, 8.1, and 8.2.**
   - Do **NOT** jump to PHP 8.3 or 8.4 right now, as Laravel 9 components may throw deprecation or compatibility errors.
   - **PHP 8.2 (Patched >= 8.2.35)** is the **exact optimal target version** for your application.
3. **Repository Reality:**
   - Your system repositories are configured for **Ubuntu 20.04 LTS (focal)**, and you already have the repository `http://ppa.launchpad.net/ondrej/php/ubuntu focal main` registered!
   - You already have several `php8.2` packages installed at version `8.2.0-1`.

---

## 2. Phase 1: Immediate Lockdown (Execute Right Now in 2 Minutes)

Do this immediately to protect the server while preparing the package update.

### Step 1: Disable `session.upload_progress.enabled`
Disable this in both the Apache PHP configuration and the CLI configuration:

```bash
# 1. Disable in Apache php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.1/apache2/php.ini

# 2. Disable in CLI php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.1/cli/php.ini

# 3. Verify it is set to Off
grep "session.upload_progress.enabled" /etc/php/8.1/apache2/php.ini
grep "session.upload_progress.enabled" /etc/php/8.1/cli/php.ini
```

### Step 2: Enforce Apache Process Recycling
This neutralizes the **Use-After-Free (CVE-2026-109619)** exploit window by forcing Apache child workers to terminate after 100 requests rather than persisting with corrupted memory.

Edit `/etc/apache2/mods-available/mpm_prefork.conf`:
```bash
nano /etc/apache2/mods-available/mpm_prefork.conf
```
Ensure `MaxConnectionsPerChild` is set to `100`:
```apache
<IfModule mpm_prefork_module>
    StartServers             5
    MinSpareServers          5
    MaxSpareServers         10
    MaxRequestWorkers       150
    MaxConnectionsPerChild  100
</IfModule>
```

### Step 3: Gracefully Reload Apache
```bash
apache2ctl configtest
systemctl restart apache2
```

---

## 3. Phase 2: Upgrading to Patched PHP 8.2

Because your app is on Laravel 9, PHP 8.2 is 100% compatible and resolves the PHP2Shell vulnerability once patched.

### Step 1: Update APT Repositories
Run an apt update and inspect if the Sury PPA is providing packages:
```bash
apt update
```

If you encounter any GPG key warnings for `ppa.launchpad.net/ondrej/php`, refresh the key:
```bash
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C
apt update
```

Check the available candidate version for PHP 8.2:
```bash
apt-cache policy php8.2-cli php8.2-common
```
You should now see candidate packages from `ppa.launchpad.net/ondrej/php/ubuntu focal`.

---

### Step 2: Install All Required PHP 8.2 Extensions
Based on your active `php -m` modules (including **MySQL** and **MongoDB**), install the corresponding PHP 8.2 packages:

```bash
apt-get install -y \
  php8.2 \
  php8.2-cli \
  php8.2-common \
  php8.2-bcmath \
  php8.2-curl \
  php8.2-gd \
  php8.2-intl \
  php8.2-mbstring \
  php8.2-mysql \
  php8.2-mongodb \
  php8.2-opcache \
  php8.2-readline \
  php8.2-soap \
  php8.2-xml \
  php8.2-zip \
  libapache2-mod-php8.2
```

---

### Step 3: Match Your Custom PHP Settings
Your current PHP 8.1 has:
- `memory_limit = 1024M`
- `post_max_size = 200M`
- `upload_max_filesize = 200M`

Apply these to the newly installed PHP 8.2 configurations:
```bash
# Update Apache PHP 8.2 settings
sed -i 's/^memory_limit = .*/memory_limit = 1024M/' /etc/php/8.2/apache2/php.ini
sed -i 's/^post_max_size = .*/post_max_size = 200M/' /etc/php/8.2/apache2/php.ini
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 200M/' /etc/php/8.2/apache2/php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.2/apache2/php.ini

# Update CLI PHP 8.2 settings
sed -i 's/^memory_limit = .*/memory_limit = 1024M/' /etc/php/8.2/cli/php.ini
sed -i 's/^post_max_size = .*/post_max_size = 200M/' /etc/php/8.2/cli/php.ini
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 200M/' /etc/php/8.2/cli/php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.2/cli/php.ini
```

---

### Step 4: Switch Apache to PHP 8.2
Switch the active Apache module from PHP 8.1 to PHP 8.2, update the CLI alternative, and restart Apache:

```bash
# 1. Disable PHP 8.1 in Apache
a2dismod php8.1

# 2. Enable PHP 8.2 in Apache
a2enmod php8.2

# 3. Update the CLI default binary
update-alternatives --set php /usr/bin/php8.2

# 4. Test Apache configuration
apache2ctl configtest

# 5. Restart Apache
systemctl restart apache2
```

---

## 4. Phase 3: Post-Upgrade Verification on Laravel

Run the following checks directly inside `/var/www/html/prod/`:

### 1. Verify Active PHP Version
```bash
php -v
```
Ensure CLI reports `PHP 8.2.x` (>= 8.2.35).

### 2. Verify Apache is Serving PHP 8.2
```bash
ls -la /etc/apache2/mods-enabled/ | grep php
```
You should see:
`php8.2.conf -> ../mods-available/php8.2.conf`  
`php8.2.load -> ../mods-available/php8.2.load`

### 3. Verify Laravel 9 Runs Smoothly
```bash
cd /var/www/html/prod
php artisan --version
php artisan route:list --compact
php artisan config:clear
php artisan cache:clear
```
All commands should succeed without syntax or compatibility errors.

### 4. Verify MongoDB and MySQL Connection
```bash
php -r "var_dump(extension_loaded('mongodb'), extension_loaded('pdo_mysql'));"
```
Both should return `bool(true)`.

---

## 5. Rollback Plan (If Any Unexpected Issue Arises)

If your application requires immediate fallback to PHP 8.1 for any reason:
```bash
a2dismod php8.2
a2enmod php8.1
update-alternatives --set php /usr/bin/php8.1
systemctl restart apache2
```
*(Your Phase 1 mitigations on PHP 8.1 will remain active to keep the server safeguarded).*
