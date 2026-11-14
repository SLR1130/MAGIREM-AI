#!/bin/bash
set -e
APP=/var/www/MAGIREM-AI

# PHP files
sudo cp /tmp/deploy/AdminController.php $APP/app/Http/Controllers/AdminController.php
sudo cp /tmp/deploy/StudentController.php $APP/app/Http/Controllers/StudentController.php
sudo cp /tmp/deploy/CounselorController.php $APP/app/Http/Controllers/CounselorController.php
sudo cp /tmp/deploy/web.php $APP/routes/web.php

# JS / CSS assets
sudo cp /tmp/deploy/notification-center.js $APP/public/js/notification-center.js
sudo cp /tmp/deploy/notification-center.css $APP/public/css/notification-center.css

# Blade views
sudo cp /tmp/deploy/app.blade.php $APP/resources/views/layouts/app.blade.php
sudo cp /tmp/deploy/notification-dropdown.blade.php $APP/resources/views/components/notification-dropdown.blade.php
sudo cp /tmp/deploy/admin-notifications.blade.php $APP/resources/views/admin/notifications.blade.php
sudo cp /tmp/deploy/student-notifications.blade.php $APP/resources/views/student/notifications.blade.php
sudo cp /tmp/deploy/counselor-notifications.blade.php $APP/resources/views/counselor/notifications.blade.php

# Permissions
sudo chown -R www-data:www-data $APP/public/js/notification-center.js $APP/public/css/notification-center.css
sudo chown -R www-data:www-data $APP/resources/views $APP/app/Http/Controllers $APP/routes

# Clear Laravel caches
sudo -u www-data php $APP/artisan view:clear
sudo -u www-data php $APP/artisan route:clear
sudo -u www-data php $APP/artisan cache:clear
sudo -u www-data php $APP/artisan config:clear

echo "DONE"
