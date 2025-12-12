<?php
session_start();
require_once 'db_config.php';

if (isset($_SESSION['logged']) && $_SESSION['logged'] === true) {
    header("Location: ./");
    exit;
}

$error_msg = ""; 
$user_input = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_input = trim($_POST['username'] ?? '');
    $pass_input = trim($_POST['password'] ?? '');

    if (!$user_input || !$pass_input) {
        $error_msg = "Compila tutti i campi.";
    } elseif (!isset($pdo)) {
        $error_msg = "Errore di connessione al database.";
    } else {
        try {
            // Recupero utente dal DB
            $stmt = $pdo->prepare("SELECT password_hash, codice_alfanumerico FROM utenti WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$user_input, $user_input]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $error_msg = "Utente non trovato.";
            } elseif (!password_verify($pass_input, $row['password_hash'])) {
                $error_msg = "Password errata.";
            } else {
                // Login riuscito
                session_regenerate_id(true);
                $_SESSION['logged'] = true;
                $_SESSION['codice_utente'] = $row['codice_alfanumerico'];
                $_SESSION['username'] = $user_input;

                setcookie('auth', 'ok', time() + 604800, '/', '', false, true);

                header("Location: ./");
                exit;
            }

        } catch (PDOException $e) {
            $error_msg = "Errore di sistema: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <!--style>
        .error { color: red; background-color: #fdd; padding: 10px; border: 1px solid red; margin-bottom: 15px; }
        .container { padding: 20px; max-width: 400px; margin: auto; }
        input { display: block; width: 100%; margin-bottom: 10px; padding: 8px; }
        button { padding: 10px 20px; cursor: pointer; }
    </style-->
</head>
<body>

    <?php include './src/includes/header.php'; ?>
    <?php include './src/includes/navbar.php'; ?>

    <div class="container">
        <h2>Accedi</h2>

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Username, Email o Codice Fiscale</label>
            <input name="username" type="text" placeholder="Inserisci credenziali" required value="<?php echo htmlspecialchars($user_input ?? ''); ?>">
            
            <label>Password</label>
            <input name="password" type="password" placeholder="Password" required>
            
            <button type="submit">Login</button>
        </form>

        <br>
        <a href="./signup">Non hai un account? Registrati</a>
    </div>

    <?php include './src/includes/footer.php'; ?>

</body>
</html>