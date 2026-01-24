<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includiamo la configurazione
require_once 'db_config.php';

// Inizializziamo il messaggio per evitare errori "Undefined variable"
$messaggio_db = '';

// --- 1. TEST SCRITTURA (INSERT) ---
// Eseguiamo l'INSERT solo se la connessione ($pdo) esiste
if (isset($pdo)) {
    try {
        // Se l'utente è loggato, usiamo il suo nome nel DB, altrimenti "Utente Web"
        $nome_visitatore = isset($_SESSION['username']) ? $_SESSION['username'] . ' (Logged)' : 'Utente Web';

        $query = 'select *, u.username from classifica as c
        join utenti as u on u.codice_alfanumerico = c.codice_alfanumerico
                    order by tempo asc, data asc
                    limit 50;';
        $stmtCons = $pdo->prepare($query);

        $stmtCons->execute();
        $output = $stmtCons->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $messaggio_db = 'Errore Scrittura: ' . $e->getMessage();
        $class_messaggio = 'error';
    }
} else {
    $messaggio_db = 'Connessione al Database non riuscita (controlla db_config.php).';
    $class_messaggio = 'error';
}
?>

<?php
// ---------------- HTML HEADER ----------------
$title = 'Classifica - Biblioteca Scrum';
$path = './';
$page_css = './public/css/style_classifica.css';
require './src/includes/header.php';
require './src/includes/navbar.php';
?>

 <table>
    <tr>
    <th>Posizione</th>
    <th>Nome</th>
    <th>Tempo</th>
  </tr>
    <?php for($i = 0; $i < count($output); $i++): ?>
  <tr>
    <td><?= $i<5? "" : $i + 1 ?></td>
    <img src= <?= $i<5? "" : './public/assets/icone_classifica/classificaBase.png' ?> >
    <td><?= $output[$i]["username"] ?></td>
    
    <td><?= floatval($output[$i]["tempo"]) * 0.001 ?></td>
  </tr>

    <?php endfor; ?>
</table> 

<?php require_once './src/includes/footer.php'; ?>