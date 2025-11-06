<?php
/**
 * eclectyc-energy/tools/health.php
 * Simple health check for monitoring services
 * Last updated: 06/11/2024 14:45:00
 */

// Quick health check
header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('d/m/Y H:i:s'),
    'service' => 'eclectyc-energy',
    'version' => '1.0.0'
];

echo json_encode($health);