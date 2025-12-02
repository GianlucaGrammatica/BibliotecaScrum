<?php
// Tenta di includere il file di configurazione del database creato prima
if (file_exists('db_config.php')) {
    include 'db_config.php';
    $db_msg = "Connessione al Database: RIUSCITA";
} else {
    $db_msg = "Errore: File db_config.php non trovato.";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Server</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>

    <h1>PROVA CIAO</h1>
    
    <hr>
    
    <p>
        <?php 
        // Se la variabile $pdo esiste (creata in db_config.php), siamo connessi
        if (isset($pdo)) {
            echo "<span class='success'>$db_msg</span>";
        } else {
            echo "<span class='error'>Connessione al Database: FALLITA</span>";
        }
        ?>
    </p>

    <p>Versione PHP: <?php echo phpversion(); ?></p>

</body>
</html>
