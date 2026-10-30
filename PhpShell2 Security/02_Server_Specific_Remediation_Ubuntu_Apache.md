# Server-Specific Security Remediation: Ubuntu 21.10 & Apache mod_php8.1

**Date Recorded:** October 30, 2026  
**Server Hostname:** `rrn2-ubuntu-s-1vcpu-1gb-intel-blr1-01`  
**Web Directory:** `/var/www/html/prod/`  
**Current OS:** Ubuntu 21.10 (Impish Indri)  
**Web Server:** Apache 2 (`systemctl is-active apache2 -> active`, Nginx inactive)  
**Current PHP SAPI:** `libapache2-mod-php8.1` (Version: `8.1.13`)  
**Risk Level:** 🚨 **CRITICAL (Extreme Priority)**

---

## 1. Analysis of Your Server's Current State

Your server diagnostic output reveals 4 critical findings:

### 1. `libapache2-mod-php8.1` is in use (Highest Risk SAPI)
- You are running `libapache2-mod-php8.1` (`8.1.13`), **not PHP-FPM**.
- In this setup, the PHP Zend engine runs directly inside Apache's worker processes.
- **Why this is critical:** The PHP2Shell vulnerabilities (CVE-2026-109618 and CVE-2026-109619) are most lethal on `mod_php` because memory state corruption happens at the web server layer before any application code executes, and Apache processes persist across requests.

### 2. PHP Version is EOL & Vulnerable (`8.1.13`)
- Your installed version is `PHP 8.1.13 (built: Nov 26 2022)`.
- All PHP versions `< 8.2.35` / `< 8.3.36` are vulnerable to PHP2Shell.
- PHP 8.1 reached official End-of-Life (EOL), meaning standard channels no longer deliver vanilla 8.1 patches.

### 3. Mixed PHP Packages are Present
- You have `php8.1` packages installed alongside stray `php8.2.0` packages (`php8.2-cli`, `php8.2-common`, `php8.2-mongodb`).
- This indicates an incomplete or aborted upgrade attempt in the past.

### 4. Apt Repository Issue (`Candidate: 8.1.13 100 /var/lib/dpkg/status`)
- When running `apt-cache policy php8.1-cli`, there are **no upstream package URLs listed** under the Version table—only the local dpkg status (`/var/lib/dpkg/status`).
- This means your server is currently **unable to fetch updates from the internet** because:
  - Ubuntu 21.10 reached End of Life (Ubuntu moved its repos to `old-releases.ubuntu.com`).
  - Or the Ondřej Surý PHP repository (`ppa:ondrej/php`) is disabled, expired, or missing in `/etc/apt/sources.list.d/`.

---

## 2. Immediate Emergency Mitigation (Apply in 2 Minutes)

Before resolving the package manager and upgrading PHP, apply these configuration mitigations immediately to neutralize the primary attack vectors.

### Step 1: Disable Session Upload Progress in Apache's PHP
Open the Apache PHP 8.1 configuration:
```bash
sudo nano /etc/php/8.1/apache2/php.ini
```
Find `session.upload_progress.enabled` and set it to `Off`:
```ini
session.upload_progress.enabled = Off
```
Also do the same for the CLI ini:
```bash
sudo nano /etc/php/8.1/cli/php.ini
```

### Step 2: Force Apache Child Processes to Recycle Frequently
Because CVE-2026-109619 relies on **stale state persisting across sequential requests in long-running worker processes**, force Apache to recycle child processes after a small number of requests.

Check which MPM Apache is using:
```bash
apache2ctl -M | grep mpm
```
*(In standard mod_php installations, this is `mpm_prefork`)*.

Edit the prefork configuration:
```bash
sudo nano /etc/apache2/mods-available/mpm_prefork.conf
```
Find or add `MaxConnectionsPerChild` (or `MaxRequestsPerChild` on older configs) and set it to `100` or `250`:
```apache
<IfModule mpm_prefork_module>
    StartServers             5
    MinSpareServers          5
    MaxSpareServers         10
    MaxRequestWorkers       150
    MaxConnectionsPerChild  100
</IfModule>
```

### Step 3: Restart Apache
```bash
sudo apache2ctl configtest
sudo systemctl restart apache2
```

---

## 3. Permanent Fix: Restoring APT & Upgrading to Patched PHP

To eliminate the vulnerability completely, you must upgrade PHP. Follow these steps on your server:

### Step 1: Diagnose APT Repository Status
Run:
```bash
sudo apt update
```
If you see `404 Not Found` errors referring to `archive.ubuntu.com/ubuntu impish`, fix your Ubuntu base repositories to point to `old-releases`:
```bash
sudo sed -i -r 's/([a-z]{2}\.)?archive.ubuntu.com/old-releases.ubuntu.com/g' /etc/apt/sources.list
sudo sed -i -r 's/security.ubuntu.com/old-releases.ubuntu.com/g' /etc/apt/sources.list
```

### Step 2: Ensure Ondřej Surý PHP PPA is Configured
Ensure the PPA repository list is present:
```bash
sudo apt-get install -y ca-certificates lsb-release apt-transport-https software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
```

Now check if new candidates are available:
```bash
apt-cache policy php8.2-cli php8.3-cli
```
You should now see candidate versions available from `ppa.launchpadcontent.net` or `deb.sury.org`.

---

### Step 3: Upgrade to Patched PHP (Recommended: PHP 8.2 or 8.3)

Since you already have partial `php8.2` packages installed, upgrading to modern **PHP 8.2** (or 8.3) is the cleanest route.

#### 1. Install the full set of PHP 8.2 modules matching your 8.1 installation:
```bash
sudo apt-get install -y \
  php8.2 \
  php8.2-cli \
  php8.2-common \
  php8.2-bcmath \
  php8.2-curl \
  php8.2-gd \
  php8.2-intl \
  php8.2-mbstring \
  php8.2-mysql \
  php8.2-opcache \
  php8.2-readline \
  php8.2-soap \
  php8.2-xml \
  php8.2-zip \
  libapache2-mod-php8.2
```

#### 2. Switch Apache from PHP 8.1 to PHP 8.2:
```bash
sudo a2dismod php8.1
sudo a2enmod php8.2
sudo update-alternatives --set php /usr/bin/php8.2
sudo systemctl restart apache2
```

#### 3. Verify the Active Versions:
```bash
php -v
```
Ensure the version shows a patched release (e.g. `PHP 8.2.35` or higher).

Confirm Apache is serving PHP 8.2:
```bash
php -r "echo 'Active CLI: ' . PHP_VERSION . PHP_EOL;"
apache2ctl -M | grep php
```

---

## 4. Modern Architecture Recommendation: Switch from `mod_php` to `php-fpm`

`mod_php` is legacy, memory-heavy, and binds the PHP engine directly to the web server. Switching to **`php-fpm` with `mpm_event`** will:
1. Significantly lower memory usage on your 1GB RAM droplet (`1vcpu-1gb`).
2. Provide process boundary isolation against memory corruption exploits.

To switch Apache to `php8.2-fpm`:
```bash
sudo apt-get install -y php8.2-fpm
sudo a2dismod php8.1 php8.2 mpm_prefork
sudo a2enmod mpm_event proxy_fcgi setenvif
sudo a2enconf php8.2-fpm
sudo systemctl restart php8.2-fpm
sudo systemctl restart apache2
```

In `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
pm.max_requests = 250
```

---

## 5. Summary Execution Checklist for Your Droplet

- [ ] **Immediate:** Edit `/etc/php/8.1/apache2/php.ini` -> Set `session.upload_progress.enabled = Off`.
- [ ] **Immediate:** Set `MaxConnectionsPerChild 100` in `/etc/apache2/mods-available/mpm_prefork.conf`.
- [ ] **Immediate:** Run `sudo systemctl restart apache2`.
- [ ] **Repo Fix:** Run `sudo apt update` and verify `ppa:ondrej/php` provides candidate packages.
- [ ] **Binary Upgrade:** Install `php8.2` / `libapache2-mod-php8.2` (or `php8.2-fpm`).
- [ ] **Verification:** Run `php -v` to confirm patched version is active.
