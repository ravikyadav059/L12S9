# Deep Analysis of Server Diagnostics: INI Defaults, OS Mismatch, and APT Candidates

**Date Recorded:** October 30, 2026  
**Server Hostname:** `rrn2-ubuntu-s-1vcpu-1gb-intel-blr1-01`  
**OS Confirmed:** Ubuntu 21.10 (Impish Indri) — `base-files 11.1ubuntu5`  
**APT Sources Configured:** Ubuntu 20.04 LTS (`focal`) + MongoDB 6.0 (`ubuntu2004`) + Ondřej Sury (`focal`)  
**Apache PHP 8.1 Configuration:** `/etc/php/8.1/apache2/php.ini`  
**Risk Level:** 🚨 **ACTIVE (Vulnerable In-Memory Defaults Confirmed)**

---

## 1. Critical Finding 1: The Semicolon Trap in `php.ini`

In your output from `/etc/php/8.1/apache2/php.ini`:
```ini
#memory_limit = -1
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M
;session.upload_progress.enabled = On
```

### Why This Is Dangerous
- In PHP INI syntax, a semicolon (`;`) at the start of a line means the directive is **COMMENTED OUT**.
- When `session.upload_progress.enabled` is commented out, PHP falls back to its **internal C-level compiled default**.
- In all standard PHP binaries, the compiled internal default is **`1` (`On`)**!
- That is why `php -i | grep session.upload_progress.enabled` confirmed:
  ```text
  session.upload_progress.enabled => On => On
  ```
- **Result:** Your production server is actively running with upload progress tracking enabled, leaving it vulnerable to **CVE-2026-115560**.

### The Immediate Fix:
You must explicitly uncomment it and set it to `Off`:
```bash
# In Apache's php.ini
sed -i 's/^;session.upload_progress.enabled = .*/session.upload_progress.enabled = Off/' /etc/php/8.1/apache2/php.ini

# In CLI's php.ini
sed -i 's/^;session.upload_progress.enabled = .*/session.upload_progress.enabled = Off/' /etc/php/8.1/cli/php.ini
```

Verify with:
```bash
grep -E "session.upload_progress.enabled" /etc/php/8.1/apache2/php.ini
```
It must now output:
```text
session.upload_progress.enabled = Off
```
*(No semicolon `;` in front!)*

---

## 2. Critical Finding 2: OS Version vs APT Sources Mismatch

Your server has an interesting configuration:
1. `cat /etc/os-release` and `lsb_release` report: **Ubuntu 21.10 (impish)**.
2. BUT all your APT sources (`/etc/apt/sources.list`) and your MongoDB binaries are configured for: **Ubuntu 20.04 (`focal`)**!
3. Your installed PHP packages show:
   `8.1.13-1+ubuntu20.04.1+deb.sury.org+1`

### Why `apt-cache policy` Only Showed `100 /var/lib/dpkg/status`
In your previous command:
```text
php8.1-cli:
  Installed: 8.1.13-1+ubuntu20.04.1+deb.sury.org+1
  Candidate: 8.1.13-1+ubuntu20.04.1+deb.sury.org+1
  Version table:
 *** 8.1.13-1+ubuntu20.04.1+deb.sury.org+1 100
        100 /var/lib/dpkg/status
```
`apt` only shows `/var/lib/dpkg/status` when:
1. `apt update` has not yet downloaded the package indexes for the configured repositories into `/var/lib/apt/lists/`.
2. OR `apt update` is hitting an error (such as a missing GPG key, HTTP redirect, or repository release lock).

---

## 3. Step-by-Step Command Playbook to Execute on Server

Follow these exact steps in your SSH terminal:

### Step 1: Fix the INI File Right Now (Immediate Safety)
```bash
# 1. Ensure upload progress is explicitly Off in Apache
sed -i 's/^;session.upload_progress.enabled = .*/session.upload_progress.enabled = Off/' /etc/php/8.1/apache2/php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.1/apache2/php.ini

# 2. Ensure upload progress is explicitly Off in CLI
sed -i 's/^;session.upload_progress.enabled = .*/session.upload_progress.enabled = Off/' /etc/php/8.1/cli/php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.1/cli/php.ini

# 3. Recycle Apache child workers to prevent lingering heap states
if ! grep -q "MaxConnectionsPerChild" /etc/apache2/mods-available/mpm_prefork.conf; then
    sed -i '/<IfModule mpm_prefork_module>/a \    MaxConnectionsPerChild  100' /etc/apache2/mods-available/mpm_prefork.conf
fi

# 4. Reload Apache
apache2ctl configtest
systemctl restart apache2
```

Verify that Apache is now reporting it as `Off`:
```bash
php -r "var_dump(ini_get('session.upload_progress.enabled'));"
```
It should return:
```text
string(0) ""   (or "0" / "Off")
```

---

### Step 2: Run `apt update` and Inspect Output
Run:
```bash
apt update
```
Inspect the output lines. You want to see:
- `Hit: http://ppa.launchpad.net/ondrej/php/ubuntu focal InRelease` OR `Get: ...`

If you see `NO_PUBKEY 4F4EA0AAE5267A6C` or `GPG error`, run:
```bash
apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C
apt update
```

Now test `apt-cache policy` again for PHP 8.2:
```bash
apt-cache policy php8.2-cli php8.2-common libapache2-mod-php8.2
```
You will now see the version candidate showing the repository URL (e.g., `500 http://ppa.launchpad.net/ondrej/php/ubuntu focal/main amd64 Packages`).

---

### Step 3: Upgrade to Patched PHP 8.2 (Full Stack)

Once candidates appear:
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

### Step 4: Apply Your Exact Limits to PHP 8.2
Your Apache `php.ini` has:
- `memory_limit = 512M`
- `post_max_size = 100M`
- `upload_max_filesize = 100M`

Apply these exact production numbers to PHP 8.2:
```bash
sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/apache2/php.ini
sed -i 's/^post_max_size = .*/post_max_size = 100M/' /etc/php/8.2/apache2/php.ini
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 100M/' /etc/php/8.2/apache2/php.ini
sed -i 's/^;session.upload_progress.enabled = .*/session.upload_progress.enabled = Off/' /etc/php/8.2/apache2/php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.2/apache2/php.ini

# Apply to CLI
sed -i 's/^memory_limit = .*/memory_limit = 1024M/' /etc/php/8.2/cli/php.ini
sed -i 's/^post_max_size = .*/post_max_size = 100M/' /etc/php/8.2/cli/php.ini
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 100M/' /etc/php/8.2/cli/php.ini
sed -i 's/^;session.upload_progress.enabled = .*/session.upload_progress.enabled = Off/' /etc/php/8.2/cli/php.ini
sed -i 's/^session.upload_progress.enabled = On/session.upload_progress.enabled = Off/' /etc/php/8.2/cli/php.ini
```

---

### Step 5: Switch Apache to PHP 8.2
```bash
a2dismod php8.1
a2enmod php8.2
update-alternatives --set php /usr/bin/php8.2
apache2ctl configtest
systemctl restart apache2
```

---

### Step 6: Verify Laravel 9 and Extensions
```bash
# 1. Confirm PHP version is >= 8.2.35
php -v

# 2. Confirm both MongoDB and MySQL extensions are active
php -r "var_dump(extension_loaded('mongodb'), extension_loaded('pdo_mysql'));"

# 3. Confirm Laravel 9 status
cd /var/www/html/prod
php artisan --version
php artisan route:list --compact
php artisan cache:clear
```
