#!/bin/bash
set -e

echo "=========================================================="
echo "🚀 MAGIREM AI - Automated Production Deployment on AWS"
echo "=========================================================="

# 1. System packages
echo "📦 Step 1: Installing Nginx, PHP 8.2, Python, and system tools..."
export DEBIAN_FRONTEND=noninteractive
sudo apt-get update -y
sudo apt-get install -y software-properties-common curl git unzip sqlite3 ca-certificates gnupg

sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -y

sudo apt-get install -y nginx \
    php8.2 php8.2-fpm php8.2-cli php8.2-sqlite3 php8.2-curl \
    php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath php8.2-intl \
    python3 python3-pip python3-venv

# 2. Composer
if ! command -v composer &> /dev/null; then
    echo "📦 Step 2: Installing Composer..."
    curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
fi

# 3. Node.js 20 & npm
if ! command -v node &> /dev/null; then
    echo "📦 Step 3: Installing Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
fi

# 4. Ollama & gemma3:4b
echo "🧠 Step 4: Installing Ollama..."
if ! command -v ollama &> /dev/null; then
    curl -fsSL https://ollama.com/install.sh | sh
fi
sudo systemctl enable --now ollama

echo "📥 Pulling gemma3:4b model (3.3 GB)..."
ollama pull gemma3:4b

# 5. Clone repository
echo "📂 Step 5: Setting up MAGIREM AI codebase..."
sudo rm -rf /var/www/MAGIREM-AI
sudo git clone https://github.com/SLR1130/MAGIREM-AI.git /var/www/MAGIREM-AI
sudo chown -R ubuntu:ubuntu /var/www/MAGIREM-AI
cd /var/www/MAGIREM-AI

# 6. Install dependencies
echo "📦 Installing PHP & NPM dependencies..."
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 7. Configure .env
echo "⚙️ Configuring environment (.env)..."
cp .env.example .env
sed -i "s|APP_ENV=.*|APP_ENV=production|g" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|g" .env
sed -i "s|APP_URL=.*|APP_URL=http://13.210.46.29|g" .env
sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|g" .env
sed -i "s|OLLAMA_BASE_URL=.*|OLLAMA_BASE_URL=http://127.0.0.1:11434|g" .env
sed -i "s|OLLAMA_MODEL=.*|OLLAMA_MODEL=gemma3:4b|g" .env
sed -i "s|ML_SERVER_URL=.*|ML_SERVER_URL=http://127.0.0.1:5000|g" .env
sed -i "s|MINDCARE_ML_API_URL=.*|MINDCARE_ML_API_URL=http://127.0.0.1:5000|g" .env

php artisan key:generate --force
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link || true

# 8. Setup Python Emotion ML Microservice
echo "🐍 Step 8: Setting up Python Emotion ML virtual environment..."
cd /var/www/MAGIREM-AI/ml_service
python3 -m venv venv
venv/bin/pip install --upgrade pip
venv/bin/pip install flask requests torch transformers accelerate --no-cache-dir || venv/bin/pip install -r requirements.txt || true

# Systemd daemon for Python ML
sudo tee /etc/systemd/system/magirem-ml.service > /dev/null << 'EOF'
[Unit]
Description=MAGIREM AI Python Emotion ML Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/MAGIREM-AI/ml_service
Environment="PATH=/var/www/MAGIREM-AI/ml_service/venv/bin"
ExecStart=/var/www/MAGIREM-AI/ml_service/venv/bin/python app.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now magirem-ml || true

# 9. Configure Nginx
echo "🌐 Step 9: Configuring Nginx web server..."
sudo tee /etc/nginx/sites-available/magirem > /dev/null << 'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name 13.210.46.29 _;
    root /var/www/MAGIREM-AI/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;
    client_max_body_size 35M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -sf /etc/nginx/sites-available/magirem /etc/nginx/sites-enabled/

# 10. File permissions & Cache optimize
echo "🔒 Step 10: Setting permissions & optimizing cache..."
cd /var/www/MAGIREM-AI
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data /var/www/MAGIREM-AI/storage /var/www/MAGIREM-AI/bootstrap/cache /var/www/MAGIREM-AI/database
sudo chmod -R 775 /var/www/MAGIREM-AI/storage /var/www/MAGIREM-AI/bootstrap/cache /var/www/MAGIREM-AI/database

sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm

echo "=========================================================="
echo "🎉 SUCCESS: MAGIREM AI is now LIVE on AWS!"
echo "👉 URL: http://13.210.46.29"
echo "=========================================================="
