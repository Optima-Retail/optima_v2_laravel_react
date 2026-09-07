<?php

/**
 * Optima v2 (Laravel Sail MySQL) — connect via shared phpMyAdmin on :3308
 * Install:
 *   sudo cp deploy/phpmyadmin-optima-v2.php /etc/phpmyadmin/conf.d/optima-v2.php
 */
$i++;
$cfg['Servers'][$i]['verbose'] = 'Optima v2 (Sail)';
$cfg['Servers'][$i]['host'] = '127.0.0.1';
$cfg['Servers'][$i]['port'] = '3311';
$cfg['Servers'][$i]['connect_type'] = 'tcp';
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['AllowNoPassword'] = false;
