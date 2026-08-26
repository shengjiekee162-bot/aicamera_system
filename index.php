<?php
require __DIR__ . '/includes/bootstrap.php';
if (!user()) redirect('login.php');
redirect(user()['role'] === 'admin' ? 'admin/dashboard.php' : 'cashier/pos.php');
