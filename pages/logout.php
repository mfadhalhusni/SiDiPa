<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

// Token CSRF tetap diverifikasi walau tautan keluar memakai GET.
csrf_wajib();
auth_keluar();
alihkan('pages/login.php');
