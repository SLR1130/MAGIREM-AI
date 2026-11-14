#!/bin/bash
set -e
APP=/var/www/MAGIREM-AI

echo "1. Installing Socialite on EC2..."
cd $APP
sudo -u www-data composer require laravel/socialite --no-interaction --quiet || true

echo "2. Copying files..."
sudo cp /tmp/deploy_google/GoogleAuthController.php $APP/app/Http/Controllers/Auth/GoogleAuthController.php
sudo cp /tmp/deploy_google/RegistrationController.php $APP/app/Http/Controllers/RegistrationController.php
sudo cp /tmp/deploy_google/User.php $APP/app/Models/User.php
sudo cp /tmp/deploy_google/services.php $APP/config/services.php
sudo cp /tmp/deploy_google/web.php $APP/routes/web.php
sudo cp /tmp/deploy_google/account-verification.blade.php $APP/resources/views/emails/account-verification.blade.php
sudo cp /tmp/deploy_google/login.blade.php $APP/resources/views/auth/login.blade.php
sudo cp /tmp/deploy_google/register.blade.php $APP/resources/views/auth/register.blade.php
sudo cp /tmp/deploy_google/2026_11_14_000001_add_google_oauth_to_users.php $APP/database/migrations/2026_11_14_000001_add_google_oauth_to_users.php

echo "3. Setting permissions..."
sudo chown -R www-data:www-data $APP/app $APP/config $APP/routes $APP/resources $APP/database

echo "4. Running database migration..."
sudo -u www-data php $APP/artisan migrate --force

echo "5. Updating .env on EC2 for SMTP and Google placeholders..."
if ! grep -q "GOOGLE_CLIENT_ID" $APP/.env; then
    echo "" | sudo tee -a $APP/.env
    echo "# Google OAuth 2.0" | sudo tee -a $APP/.env
    echo "GOOGLE_CLIENT_ID=" | sudo tee -a $APP/.env
    echo "GOOGLE_CLIENT_SECRET=" | sudo tee -a $APP/.env
    echo "GOOGLE_REDIRECT_URI=\"\${APP_URL}/auth/google/callback\"" | sudo tee -a $APP/.env
fi

# Ensure real Gmail SMTP delivery is enabled on EC2
sudo sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=smtp/' $APP/.env
sudo sed -i 's/^MAIL_HOST=.*/MAIL_HOST=smtp.gmail.com/' $APP/.env
sudo sed -i 's/^MAIL_PORT=.*/MAIL_PORT=587/' $APP/.env
sudo sed -i 's/^MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/' $APP/.env
sudo sed -i 's/^MAIL_USERNAME=.*/MAIL_USERNAME=dmc688143@gmail.com/' $APP/.env
sudo sed -i 's/^MAIL_PASSWORD=.*/MAIL_PASSWORD="ajjegfduazmhynyu"/' $APP/.env
sudo sed -i 's/^MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=dmc688143@gmail.com/' $APP/.env
sudo sed -i 's/^MAIL_FROM_NAME=.*/MAIL_FROM_NAME="MAGIREM AI"/' $APP/.env

echo "6. Clearing caches..."
sudo -u www-data php $APP/artisan view:clear
sudo -u www-data php $APP/artisan route:clear
sudo -u www-data php $APP/artisan cache:clear
sudo -u www-data php $APP/artisan config:clear

echo "DEPLOY SUCCESSFUL!"
