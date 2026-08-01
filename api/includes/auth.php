<?php
/**
 * Helper otentikasi admin berbasis PHP session.
 * File ini HARUS di-include setelah session_start() dipanggil.
 */

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** Panggil di awal setiap halaman yang cuma boleh diakses admin. */
function wajibLogin(): void
{
    if (!isAdminLoggedIn()) {
        setFlash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect('login.php');
    }
}
