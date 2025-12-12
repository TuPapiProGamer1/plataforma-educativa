<?php
/**
 * PLATAFORMA EDUCATIVA - PÁGINA DE INICIO
 *
 * Redirige al login o dashboard según el estado de autenticación
 */

define('APP_ACCESS', true);
session_start();

// Si ya está autenticado, ir al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    header('Location: dashboard.php');
    exit;
}

// Si no está autenticado, ir al login
header('Location: login.php');
exit;
