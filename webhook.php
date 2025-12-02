<?php
// CONFIGURAZIONE
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$secret = $_ENV['SECRET_WEBHOOK']; // <--- RIMETTI LA PASSWORD CHE HAI SU GITHUB
$script = '/home/fede_femia/deploy_rapido.sh';

// 1. Verifica Firma
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');

if (!$signature || !$payload) {
    http_response_code(403);
    die("Accesso negato: Nessuna firma.");
}

// 2. Verifica Password
list($algo, $hash) = explode('=', $signature, 2);
$payloadHash = hash_hmac($algo, $payload, $secret);

if ($hash !== $payloadHash) {
    http_response_code(403);
    die("Accesso negato: Password segreta errata.");
}

// 3. Esegui Script
$output = shell_exec("sudo -u fede_femia $script 2>&1");
echo "Deploy completato:\n$output";
?>
