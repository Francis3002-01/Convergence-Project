<?php

/*
 * Convergence Journal
 * Application URL Configuration
 *
 * XAMPP / ngrok:
 *   /Convergence%20Project
 *
 * Docker / Production:
 *   empty string
 */

$baseUrl = getenv('APP_BASE_URL');

if ($baseUrl === false) {
    $baseUrl = '/Convergence%20Project';
}

$baseUrl = rtrim($baseUrl, '/');