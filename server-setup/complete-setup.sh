#!/bin/bash

# ============================================================================
#  COMPLETE VPS SERVER SETUP - ALL-IN-ONE SCRIPT
#  Ubuntu 22.04 + Apache + MariaDB 11 + PHP 8.2 + FTP + phpMyAdmin + Webmin
# ============================================================================

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
DOMAIN="onenetly.com"
FTP_USER="TheAceMotiur"
FTP_PASS="AmiMotiur27@"
WEB_ROOT="/var/www/${DOMAIN}"
ADMIN_EMAIL="admin@${DOMAIN}"

# ============================================================================
# HEADER
# ============================================================================
clear
echo -e "${CYAN}"
cat << "EOF"
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║        🚀 COMPLETE VPS SERVER SETUP - ALL-IN-ONE SCRIPT 🚀          ║
║                                                                      ║
║     Ubuntu 22.04 + Apache + MariaDB 11 + PHP 8.2 + FTP + More      ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"
echo ""

# ============================================================================
# ROOT CHECK
# ============================================================================
if [ "$EUID" -ne 0 ]; then 
   echo -e "${RED}ERROR: Please run as root${NC}"
   echo -e "${YELLOW}Usage: sudo bash complete-setup.sh${NC}"
   exit 1
fi

# ============================================================================
# CONFIRMATION
# ============================================================================
echo -e "${YELLOW}This comprehensive script will:${NC}"
echo ""
echo -e "  ${GREEN}✓${NC} Update system packages"
echo -e "  ${GREEN}✓${NC} Install Apache Web Server with optimizations"
echo -e "  ${GREEN}✓${NC} Install PHP 8.2 with maximum performance settings"
echo -e "  ${GREEN}✓${NC} Install MariaDB 11 Database Server"
echo -e "  ${GREEN}✓${NC} Install vsftpd FTP Server with secure configuration"
echo -e "  ${GREEN}✓${NC} Install phpMyAdmin for database management"
echo -e "  ${GREEN}✓${NC} Install Webmin control panel"
echo -e "  ${GREEN}✓${NC} Configure virtual host for ${DOMAIN}"
echo -e "  ${GREEN}✓${NC} Setup firewall (UFW) with required ports"
echo -e "  ${GREEN}✓${NC} Configure FTP user and permissions"
echo -e "  ${GREEN}✓${NC} Apply security hardening"
echo ""
echo -e "${CYAN}Configuration Details:${NC}"
echo -e "  Domain: ${DOMAIN}"
echo -e "  Web Root: ${WEB_ROOT}"
echo -e "  FTP User: ${FTP_USER}"
echo -e "  FTP Password: ${FTP_PASS}"
echo ""
read -p "Continue with installation? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}Installation cancelled.${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}Starting complete installation...${NC}"
echo ""
sleep 2

# ============================================================================
# STEP 1: SYSTEM UPDATE
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[1/12] Updating System Packages${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
apt update && apt upgrade -y
apt install -y software-properties-common apt-transport-https ca-certificates \
    curl wget gnupg2 ufw unzip zip git htop net-tools
echo -e "${GREEN}✓ System updated successfully${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 2: APACHE INSTALLATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[2/12] Installing Apache Web Server${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
apt install -y apache2
a2enmod rewrite ssl headers expires deflate proxy_fcgi setenvif
systemctl start apache2
systemctl enable apache2
echo -e "${GREEN}✓ Apache installed and configured${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 3: PHP 8.2 INSTALLATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[3/12] Installing PHP 8.2 with Extensions${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common php8.2-mysql \
    php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath \
    php8.2-json php8.2-intl php8.2-soap php8.2-imagick php8.2-opcache php8.2-redis
systemctl start php8.2-fpm
systemctl enable php8.2-fpm
a2enconf php8.2-fpm
echo -e "${GREEN}✓ PHP 8.2 installed with all extensions${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 4: PHP CONFIGURATION (MAXIMUM PERFORMANCE)
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[4/12] Configuring PHP with Maximum Performance Settings${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

PHP_INI_CLI="/etc/php/8.2/cli/php.ini"
PHP_INI_FPM="/etc/php/8.2/fpm/php.ini"
PHP_INI_APACHE="/etc/php/8.2/apache2/php.ini"

# Backup original files
cp ${PHP_INI_CLI} ${PHP_INI_CLI}.backup
cp ${PHP_INI_FPM} ${PHP_INI_FPM}.backup
[ -f ${PHP_INI_APACHE} ] && cp ${PHP_INI_APACHE} ${PHP_INI_APACHE}.backup

# Function to update PHP setting
update_php_setting() {
    local ini_file=$1
    local setting=$2
    local value=$3
    
    sed -i "/^${setting}/d" ${ini_file}
    sed -i "/^;${setting}/d" ${ini_file}
    echo "${setting} = ${value}" >> ${ini_file}
}

# Configure PHP settings
for ini_file in ${PHP_INI_CLI} ${PHP_INI_FPM}; do
    # Memory and execution limits
    update_php_setting ${ini_file} "memory_limit" "5G"
    update_php_setting ${ini_file} "max_execution_time" "3600"
    update_php_setting ${ini_file} "max_input_time" "3600"
    update_php_setting ${ini_file} "max_input_vars" "100000"
    
    # Upload limits
    update_php_setting ${ini_file} "upload_max_filesize" "5G"
    update_php_setting ${ini_file} "post_max_size" "5G"
    
    # Error reporting
    update_php_setting ${ini_file} "display_errors" "Off"
    update_php_setting ${ini_file} "display_startup_errors" "Off"
    update_php_setting ${ini_file} "log_errors" "On"
    update_php_setting ${ini_file} "error_log" "/var/log/php8.2-errors.log"
    
    # Session settings
    update_php_setting ${ini_file} "session.gc_maxlifetime" "3600"
    update_php_setting ${ini_file} "session.cookie_lifetime" "3600"
    
    # OPcache settings
    update_php_setting ${ini_file} "opcache.enable" "1"
    update_php_setting ${ini_file} "opcache.memory_consumption" "256"
    update_php_setting ${ini_file} "opcache.interned_strings_buffer" "16"
    update_php_setting ${ini_file} "opcache.max_accelerated_files" "10000"
    update_php_setting ${ini_file} "opcache.revalidate_freq" "2"
    update_php_setting ${ini_file} "opcache.fast_shutdown" "1"
    
    # File upload settings
    update_php_setting ${ini_file} "file_uploads" "On"
    update_php_setting ${ini_file} "max_file_uploads" "100"
    
    # Other settings
    update_php_setting ${ini_file} "default_socket_timeout" "3600"
    update_php_setting ${ini_file} "date.timezone" "UTC"
done

# Create PHP info test file
echo "<?php phpinfo(); ?>" > /var/www/html/info.php
chmod 644 /var/www/html/info.php

echo -e "${GREEN}✓ PHP configured with maximum performance settings${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 5: MARIADB INSTALLATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[5/12] Installing MariaDB 11 Database Server${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | bash -s -- --mariadb-server-version="mariadb-11.2"
apt install -y mariadb-server mariadb-client
systemctl start mariadb
systemctl enable mariadb
echo -e "${GREEN}✓ MariaDB 11 installed successfully${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 6: VSFTPD INSTALLATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[6/12] Installing vsftpd FTP Server${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
apt install -y vsftpd
cp /etc/vsftpd.conf /etc/vsftpd.conf.backup
echo -e "${GREEN}✓ vsftpd installed${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 7: FTP USER SETUP
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[7/12] Creating FTP User and Web Directories${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Create user
id -u ${FTP_USER} &>/dev/null || useradd -m -s /bin/bash ${FTP_USER}
echo "${FTP_USER}:${FTP_PASS}" | chpasswd

# Create web directory structure
mkdir -p ${WEB_ROOT}/public_html
mkdir -p ${WEB_ROOT}/logs

# Copy existing files if they exist
if [ -d "/c/xampp/htdocs/onenetly" ]; then
    echo -e "${YELLOW}Copying existing website files...${NC}"
    cp -r /c/xampp/htdocs/onenetly/* ${WEB_ROOT}/public_html/ 2>/dev/null || true
fi

# Create default index if none exists
if [ ! -f "${WEB_ROOT}/public_html/index.php" ]; then
    cat > ${WEB_ROOT}/public_html/index.php << 'INDEXEOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Onenetly.com</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 600px;
        }
        h1 { font-size: 3em; margin-bottom: 20px; text-shadow: 2px 2px 4px rgba(0,0,0,0.2); }
        p { font-size: 1.2em; margin-bottom: 30px; }
        .info {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: left;
        }
        .info h2 { margin-bottom: 15px; }
        .info-item { margin: 10px 0; padding: 8px; background: rgba(0,0,0,0.1); border-radius: 5px; }
        .success { color: #4ade80; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Welcome to Onenetly.com</h1>
        <p class="success">✓ Server is running successfully!</p>
        <p>Your Ubuntu VPS is configured and ready to go.</p>
        
        <div class="info">
            <h2>Server Information</h2>
            <div class="info-item"><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></div>
            <div class="info-item"><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
            <div class="info-item"><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></div>
            <div class="info-item"><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></div>
            <div class="info-item"><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></div>
            <div class="info-item"><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s</div>
            <div class="info-item"><strong>Upload Max Filesize:</strong> <?php echo ini_get('upload_max_filesize'); ?></div>
        </div>
    </div>
</body>
</html>
INDEXEOF
fi

# Set proper permissions
chown -R ${FTP_USER}:www-data ${WEB_ROOT}
find ${WEB_ROOT} -type d -exec chmod 755 {} \;
find ${WEB_ROOT} -type f -exec chmod 644 {} \;

# Add user to www-data group
usermod -a -G www-data ${FTP_USER}

echo -e "${GREEN}✓ FTP user and web directories created${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 8: FTP CONFIGURATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[8/12] Configuring vsftpd FTP Server${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

cat > /etc/vsftpd.conf << 'FTPEOF'
# vsftpd Configuration - Secure and Optimized
listen=NO
listen_ipv6=YES

# Access rights
anonymous_enable=NO
local_enable=YES
write_enable=YES
local_umask=022
dirmessage_enable=YES

# Security
chroot_local_user=YES
allow_writeable_chroot=YES
secure_chroot_dir=/var/run/vsftpd/empty

# Features
xferlog_enable=YES
connect_from_port_20=YES
xferlog_std_format=YES
listen_port=21

# Performance
idle_session_timeout=600
data_connection_timeout=120

# PAM service
pam_service_name=vsftpd

# User list
userlist_enable=YES
userlist_file=/etc/vsftpd.userlist
userlist_deny=NO

# Passive mode
pasv_enable=YES
pasv_min_port=40000
pasv_max_port=50000

# Logging
log_ftp_protocol=YES
xferlog_file=/var/log/vsftpd.log
vsftpd_log_file=/var/log/vsftpd.log

# UTF8
utf8_filesystem=YES
FTPEOF

# Add FTP user to allowed list
echo "${FTP_USER}" > /etc/vsftpd.userlist

# Set user's home directory to web root
usermod -d ${WEB_ROOT} ${FTP_USER}

echo -e "${GREEN}✓ vsftpd configured successfully${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 9: PHPMYADMIN INSTALLATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[9/12] Installing phpMyAdmin${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

export DEBIAN_FRONTEND=noninteractive
debconf-set-selections <<< "phpmyadmin phpmyadmin/dbconfig-install boolean true"
debconf-set-selections <<< "phpmyadmin phpmyadmin/app-password-confirm password ${FTP_PASS}"
debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/admin-pass password ${FTP_PASS}"
debconf-set-selections <<< "phpmyadmin phpmyadmin/mysql/app-pass password ${FTP_PASS}"
debconf-set-selections <<< "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2"

apt install -y phpmyadmin
ln -sf /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf
a2enconf phpmyadmin

echo -e "${GREEN}✓ phpMyAdmin installed${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 10: WEBMIN INSTALLATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[10/12] Installing Webmin Control Panel${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

wget -qO - http://www.webmin.com/jcameron-key.asc | apt-key add -
echo "deb http://download.webmin.com/download/repository sarge contrib" > /etc/apt/sources.list.d/webmin.list
apt update
apt install -y webmin

echo -e "${GREEN}✓ Webmin installed${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 11: VIRTUAL HOST CONFIGURATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[11/12] Configuring Apache Virtual Host${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

VHOST_CONF="/etc/apache2/sites-available/${DOMAIN}.conf"

cat > ${VHOST_CONF} << EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    ServerAdmin ${ADMIN_EMAIL}
    
    DocumentRoot ${WEB_ROOT}/public_html
    
    <Directory ${WEB_ROOT}/public_html>
        Options -Indexes +FollowSymLinks +MultiViews
        AllowOverride All
        Require all granted
        
        # Enable PHP-FPM
        <FilesMatch \.php$>
            SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
        </FilesMatch>
    </Directory>
    
    # Logging
    ErrorLog ${WEB_ROOT}/logs/error.log
    CustomLog ${WEB_ROOT}/logs/access.log combined
    
    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    
    # Compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
    </IfModule>
    
    # Browser Caching
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/jpg "access plus 1 year"
        ExpiresByType image/jpeg "access plus 1 year"
        ExpiresByType image/gif "access plus 1 year"
        ExpiresByType image/png "access plus 1 year"
        ExpiresByType image/webp "access plus 1 year"
        ExpiresByType text/css "access plus 1 month"
        ExpiresByType application/javascript "access plus 1 month"
        ExpiresByType application/pdf "access plus 1 month"
    </IfModule>
</VirtualHost>
EOF

# Enable virtual host
a2ensite ${DOMAIN}.conf
a2dissite 000-default.conf

echo -e "${GREEN}✓ Virtual host configured${NC}"
echo ""
sleep 1

# ============================================================================
# STEP 12: FIREWALL CONFIGURATION
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}[12/12] Configuring Firewall (UFW)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

ufw allow OpenSSH
ufw allow 'Apache Full'
ufw allow 20/tcp      # FTP data
ufw allow 21/tcp      # FTP control
ufw allow 990/tcp     # FTPS
ufw allow 40000:50000/tcp  # FTP passive ports
ufw allow 10000/tcp   # Webmin
ufw allow 3306/tcp    # MySQL (optional, remove if not needed externally)
ufw --force enable

echo -e "${GREEN}✓ Firewall configured${NC}"
echo ""
sleep 1

# ============================================================================
# RESTART ALL SERVICES
# ============================================================================
echo -e "${MAGENTA}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${MAGENTA}Restarting All Services...${NC}"
echo -e "${MAGENTA}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

systemctl restart php8.2-fpm
systemctl restart apache2
systemctl restart mariadb
systemctl restart vsftpd
systemctl restart webmin

echo -e "${GREEN}✓ All services restarted${NC}"
echo ""
sleep 2

# ============================================================================
# SYSTEM STATUS CHECK
# ============================================================================
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}Verifying Service Status...${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

check_service() {
    local service=$1
    local name=$2
    if systemctl is-active --quiet $service; then
        echo -e "  ${name}: ${GREEN}✓ Running${NC}"
    else
        echo -e "  ${name}: ${RED}✗ Stopped${NC}"
    fi
}

check_service apache2 "Apache Web Server"
check_service php8.2-fpm "PHP 8.2 FPM"
check_service mariadb "MariaDB Database"
check_service vsftpd "FTP Server"
check_service webmin "Webmin Control Panel"
echo ""

# Get server IP
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || echo "Unable to detect")

# ============================================================================
# INSTALLATION COMPLETE - SUMMARY
# ============================================================================
clear
echo -e "${GREEN}"
cat << "EOF"
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║               🎉 INSTALLATION COMPLETED SUCCESSFULLY! 🎉            ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}                     SERVER INFORMATION                       ${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Domain Configuration:${NC}"
echo -e "  Domain: ${GREEN}${DOMAIN}${NC}"
echo -e "  Server IP: ${GREEN}${SERVER_IP}${NC}"
echo -e "  Web Root: ${GREEN}${WEB_ROOT}/public_html${NC}"
echo ""
echo -e "${YELLOW}Access Your Services:${NC}"
echo -e "  Website:       ${GREEN}http://${DOMAIN}${NC}"
echo -e "                 ${GREEN}http://${SERVER_IP}${NC}"
echo -e "  phpMyAdmin:    ${GREEN}http://${SERVER_IP}/phpmyadmin${NC}"
echo -e "  Webmin:        ${GREEN}https://${SERVER_IP}:10000${NC}"
echo -e "  PHP Info:      ${GREEN}http://${SERVER_IP}/info.php${NC}"
echo ""
echo -e "${YELLOW}FTP/SFTP Access:${NC}"
echo -e "  Host:          ${GREEN}${DOMAIN}${NC} or ${GREEN}${SERVER_IP}${NC}"
echo -e "  Protocol:      ${GREEN}FTP${NC}"
echo -e "  Port:          ${GREEN}21${NC}"
echo -e "  Username:      ${GREEN}${FTP_USER}${NC}"
echo -e "  Password:      ${GREEN}${FTP_PASS}${NC}"
echo -e "  Directory:     ${GREEN}${WEB_ROOT}${NC}"
echo ""
echo -e "${YELLOW}Database Access:${NC}"
echo -e "  Host:          ${GREEN}localhost${NC}"
echo -e "  Port:          ${GREEN}3306${NC}"
echo -e "  Root User:     ${GREEN}root${NC}"
echo -e "  Root Pass:     ${RED}(run mysql_secure_installation)${NC}"
echo ""
echo -e "${YELLOW}System Credentials:${NC}"
echo -e "  SSH User:      ${GREEN}${FTP_USER}${NC}"
echo -e "  SSH Password:  ${GREEN}${FTP_PASS}${NC}"
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}                     INSTALLED COMPONENTS                     ${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  ${GREEN}✓${NC} Apache $(apache2 -v 2>/dev/null | head -n1 | awk '{print $3}')"
echo -e "  ${GREEN}✓${NC} PHP $(php -v | head -n1 | awk '{print $2}')"
echo -e "  ${GREEN}✓${NC} MariaDB $(mysql --version | awk '{print $5}' | cut -d'-' -f1)"
echo -e "  ${GREEN}✓${NC} vsftpd FTP Server"
echo -e "  ${GREEN}✓${NC} phpMyAdmin"
echo -e "  ${GREEN}✓${NC} Webmin Control Panel"
echo -e "  ${GREEN}✓${NC} UFW Firewall"
echo ""
echo -e "${YELLOW}PHP Configuration:${NC}"
echo -e "  Memory Limit:          ${GREEN}5G${NC}"
echo -e "  Max Execution Time:    ${GREEN}3600s (1 hour)${NC}"
echo -e "  Upload Max Filesize:   ${GREEN}5G${NC}"
echo -e "  Post Max Size:         ${GREEN}5G${NC}"
echo -e "  Max Input Vars:        ${GREEN}100000${NC}"
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}                     IMPORTANT NEXT STEPS                     ${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}1. Secure MariaDB:${NC}"
echo -e "   ${GREEN}sudo mysql_secure_installation${NC}"
echo -e "   (Set root password, remove anonymous users, etc.)"
echo ""
echo -e "${YELLOW}2. Configure DNS:${NC}"
echo -e "   Point your domain ${GREEN}${DOMAIN}${NC} to IP: ${GREEN}${SERVER_IP}${NC}"
echo -e "   Add A record: ${GREEN}${DOMAIN}${NC} -> ${GREEN}${SERVER_IP}${NC}"
echo -e "   Add A record: ${GREEN}www.${DOMAIN}${NC} -> ${GREEN}${SERVER_IP}${NC}"
echo ""
echo -e "${YELLOW}3. Install SSL Certificate (after DNS propagation):${NC}"
echo -e "   ${GREEN}sudo apt install certbot python3-certbot-apache -y${NC}"
echo -e "   ${GREEN}sudo certbot --apache -d ${DOMAIN} -d www.${DOMAIN}${NC}"
echo ""
echo -e "${YELLOW}4. Security:${NC}"
echo -e "   ${GREEN}sudo rm /var/www/html/info.php${NC} (delete PHP info file)"
echo -e "   Change default passwords"
echo -e "   Configure phpMyAdmin security"
echo -e "   Review firewall rules: ${GREEN}sudo ufw status${NC}"
echo ""
echo -e "${YELLOW}5. Upload Your Website:${NC}"
echo -e "   Use FTP/SFTP to upload files to: ${GREEN}${WEB_ROOT}/public_html${NC}"
echo -e "   Or use git: ${GREEN}cd ${WEB_ROOT}/public_html && git clone <repo>${NC}"
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}                     USEFUL COMMANDS                          ${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Service Management:${NC}"
echo -e "  ${GREEN}systemctl restart apache2${NC}        - Restart Apache"
echo -e "  ${GREEN}systemctl restart php8.2-fpm${NC}     - Restart PHP"
echo -e "  ${GREEN}systemctl restart mariadb${NC}        - Restart Database"
echo -e "  ${GREEN}systemctl restart vsftpd${NC}         - Restart FTP"
echo -e "  ${GREEN}systemctl status <service>${NC}       - Check service status"
echo ""
echo -e "${YELLOW}Log Files:${NC}"
echo -e "  ${GREEN}tail -f ${WEB_ROOT}/logs/error.log${NC}  - Website errors"
echo -e "  ${GREEN}tail -f /var/log/apache2/error.log${NC}  - Apache errors"
echo -e "  ${GREEN}tail -f /var/log/php8.2-errors.log${NC}  - PHP errors"
echo -e "  ${GREEN}tail -f /var/log/vsftpd.log${NC}         - FTP logs"
echo ""
echo -e "${YELLOW}File Management:${NC}"
echo -e "  ${GREEN}cd ${WEB_ROOT}/public_html${NC}       - Go to website directory"
echo -e "  ${GREEN}chown -R ${FTP_USER}:www-data ${WEB_ROOT}${NC}  - Fix permissions"
echo -e "  ${GREEN}chmod -R 755 ${WEB_ROOT}${NC}        - Set directory permissions"
echo ""
echo -e "${YELLOW}Database:${NC}"
echo -e "  ${GREEN}mysql -u root -p${NC}                 - MySQL CLI access"
echo -e "  ${GREEN}mysqldump -u root -p db > backup.sql${NC}  - Backup database"
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}Your server is ready! Happy coding! 🚀${NC}"
echo ""
echo -e "${YELLOW}For support and documentation, visit:${NC}"
echo -e "  Apache: ${GREEN}https://httpd.apache.org/docs/${NC}"
echo -e "  PHP: ${GREEN}https://www.php.net/docs.php${NC}"
echo -e "  MariaDB: ${GREEN}https://mariadb.com/kb/en/documentation/${NC}"
echo ""

# Save installation log
INSTALL_LOG="${WEB_ROOT}/installation-info.txt"
cat > ${INSTALL_LOG} << LOGEOF
Complete VPS Setup Installation
================================
Date: $(date)
Server IP: ${SERVER_IP}
Domain: ${DOMAIN}

Services Installed:
- Apache $(apache2 -v 2>/dev/null | head -n1 | awk '{print $3}')
- PHP $(php -v | head -n1 | awk '{print $2}')
- MariaDB $(mysql --version | awk '{print $5}' | cut -d'-' -f1)
- vsftpd FTP Server
- phpMyAdmin
- Webmin Control Panel

Credentials:
- FTP User: ${FTP_USER}
- FTP Pass: ${FTP_PASS}

Web Root: ${WEB_ROOT}/public_html
LOGEOF

chmod 600 ${INSTALL_LOG}

echo -e "${GREEN}Installation details saved to: ${INSTALL_LOG}${NC}"
echo ""
