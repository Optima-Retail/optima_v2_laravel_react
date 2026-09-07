#!/usr/bin/env bash
set -euo pipefail
cp /var/www/achraf.es/optima_v2_laravel_react/deploy/phpmyadmin-optima-v2.php /etc/phpmyadmin/conf.d/optima-v2.php
chmod 644 /etc/phpmyadmin/conf.d/optima-v2.php
echo "Installed Optima v2 server into shared phpMyAdmin (:3308)."
