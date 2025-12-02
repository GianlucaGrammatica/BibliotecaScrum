<?php
// Includiamo la configurazione
require_once 'db_config.php';

// Inizializziamo il messaggio per evitare errori "Undefined variable"
$messaggio_db = ""; 

// --- 1. TEST SCRITTURA (INSERT) ---
// Eseguiamo l'INSERT solo se la connessione ($pdo) esiste
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO visitatori (nome) VALUES (:nome)");
        $stmt->execute(['nome' => 'Utente Web']);
        $messaggio_db = "Nuovo accesso registrato nel DB!";
        $class_messaggio = "success"; // Per colorare il box di verde
    } catch (PDOException $e) {
        $messaggio_db = "Errore Scrittura: " . $e->getMessage();
        $class_messaggio = "error"; // Per colorare il box di rosso
    }
} else {
    $messaggio_db = "Connessione al Database non riuscita (controlla db_config.php).";
    $class_messaggio = "error";
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Test Database</title>
    <style>
        /* --- LAYOUT GENERALE --- */
        body {
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 250px;
            background-color: #2c3e50; /* Blu scuro più professionale */
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar h3 {
            margin-top: 0;
            border-bottom: 1px solid #485f75;
            padding-bottom: 10px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li {
            margin-bottom: 10px;
        }

        .sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            display: block;
            padding: 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .sidebar a:hover {
            background-color: #34495e;
        }

        /* --- CONTENUTO --- */
        .container {
            flex: 1;
            padding: 40px;
            background-color: #f9f9f9;
            overflow-y: auto;
        }

        /* --- STILI CHE MANCAVANO (LOG BOX) --- */
        .log-box {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            display: inline-block; /* Si adatta al contenuto */
        }
        
        .log-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .log-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* --- STILI CHE MANCAVANO (TABELLA) --- */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #3498db;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h3>Home</h3>
        <ul>
            <li><bold>Links:</bold></li>
            <li><a href="home">Home</a></li>
            <li><a href="https://google.com" target="_blank">Vai su Google</a></li>
        </ul>
    </div>

    <div class="container">
        <h1>Test Connessione Database</h1>

        <?php if (!empty($messaggio_db)): ?>
            <div class="log-box <?php echo $class_messaggio; ?>">
                <?php echo $messaggio_db; ?>
            </div>
        <?php endif; ?>

        <h3>Ultimi 10 accessi registrati:</h3>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Data e Ora</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (isset($pdo)) {
                    try {
                        $sql = "SELECT * FROM visitatori ORDER BY id DESC LIMIT 10";
                        // Usiamo query() direttamente perché non ci sono parametri esterni (sicuro)
                        foreach ($pdo->query($sql) as $riga) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($riga['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($riga['nome']) . "</td>";
                            // Formattiamo la data se necessario, o stampiamo raw
                            echo "<td>" . htmlspecialchars($riga['data_visita']) . "</td>";
                            echo "</tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='3' style='color:red;'>Errore Lettura: " . $e->getMessage() . "</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' style='text-align:center;'>⚠️ Connessione al database non disponibile.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <p style="text-align: center; margin-top: 30px; color: #777;">
            <em>Ricarica la pagina per generare un nuovo inserimento.</em>
        </p>
    </div>

</body>
</html>