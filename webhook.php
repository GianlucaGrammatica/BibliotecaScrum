<?php
// CONFIGURAZIONE
$secret = '+Pc52(17l+D£['; // <--- CAMBIA QUESTA CON UNA PASSWORD LUNGA
$script = '/home/fede_femia/deploy_rapido.sh';

// 1. Verifica che la richiesta venga da GitHub
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');

if (!$signature || !$payload) {
    http_response_code(403);
    die("Accesso negato: Nessuna firma.");
}

// 2. Verifica la Password Segreta (HMAC)
list($algo, $hash) = explode('=', $signature, 2);
$payloadHash = hash_hmac($algo, $payload, $secret);

if ($hash !== $payloadHash) {
    http_response_code(403);
    die("Accesso negato: Password segreta errata.");
}

// 3. Esegui lo script come l'utente 'fede_femia'
// Il comando 'sudo -u fede_femia' sfrutta il permesso che abbiamo dato nel Passo 2
$output = shell_exec("sudo -u fede_femia $script 2>&1");

echo "Deploy completato:\n$output";
?>
