#!/usr/bin/env bash
set -euo pipefail

echo "==> Move talhaoui-trans to :8404"
cp /var/www/talhaoui-trans.com/deploy/talhaoui-trans-backend.service /etc/systemd/system/talhaoui-trans-backend.service
# Keep certbot SSL blocks; only change proxy_pass in live site
sed -i 's|proxy_pass http://127.0.0.1:8400;|proxy_pass http://127.0.0.1:8404;|' /etc/nginx/sites-available/talhaoui-trans.com
systemctl daemon-reload
systemctl restart talhaoui-trans-backend.service

echo "==> Update optima-v2 nginx -> :8400"
cp /var/www/achraf.es/optima_v2_laravel_react/deploy/nginx-optima-v2.achraf.es.conf /etc/nginx/sites-available/optima-v2.achraf.es
ln -sf /etc/nginx/sites-available/optima-v2.achraf.es /etc/nginx/sites-enabled/optima-v2.achraf.es

# Prevent corgi from accidentally proxying to optima_v2 on :8400
# (corgi was incorrectly pointing at 8400 which belonged to talhaoui)
if grep -q 'server 0.0.0.0:8400;' /etc/nginx/sites-available/corgi.achraf.es 2>/dev/null; then
  echo "==> Fixing corgi upstream away from :8400 (was colliding)"
  sed -i 's|server 0.0.0.0:8400;|server 127.0.0.1:8410;|' /etc/nginx/sites-available/corgi.achraf.es
fi

nginx -t
systemctl reload nginx

echo "==> Done system changes"
systemctl is-active talhaoui-trans-backend.service
ss -tln | grep -E ':(8400|8404)\s' || true
