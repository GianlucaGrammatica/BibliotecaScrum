<?php
session_start();
require_once 'db_config.php';
// Includo la tua libreria per il calcolo
require_once __DIR__ . '/../src/includes/codiceFiscaleMethods.php';

// --- FIX PER L'HTML DEL TUO COMPAGNO ---
// Definisco le variabili che causavano "Undefined variable"
// Se nell'URL c'è ?mode=manuale (es. clicchi su "Hai il CF?"), attiva la modalità manuale
$registratiConCodice = isset($_GET['mode']) && $_GET['mode'] == 'manuale';
$tipologia = $registratiConCodice ? 'manuale' : 'automatico';

$error_msg = "";
$success_msg = "";

// Funzione ID casuale per la tua tabella
function genID($l=6) { return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"),0,$l); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupero dati dal form
    $nome     = $_POST['nome'] ?? '';
    $cognome  = $_POST['cognome'] ?? '';
    $username = $_POST['username'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Dati specifici per il calcolo
    $data     = $_POST['data_nascita'] ?? '';
    $sesso    = $_POST['sesso'] ?? '';
    $comune   = $_POST['codice_comune'] ?? ''; // Controlla il name nel tuo HTML
    
    // CF Manuale (se inserito)
    $cf_input = $_POST['codice_fiscale'] ?? '';

    if (isset($pdo)) {
        try {
            $cf_finale = "";

            // LOGICA: Usiamo il CF manuale se presente, altrimenti calcoliamo
            if (!empty($cf_input)) {
                $cf_finale = strtoupper($cf_input);
            } else {
                // TUA FUNZIONE DI CALCOLO
                if ($nome && $cognome && $data && $sesso && $comune) {
                    $cf_finale = generateCodiceFiscale($nome, $cognome, $data, $sesso, $comune);
                } else {
                    throw new Exception("Compila tutti i campi (Data, Sesso, Comune) per calcolare il CF.");
                }
            }

            // Inserimento nel DB (Compatibile con il tuo Login SHA256)
            $id = genID();
            $hash = hash('sha256', $password);

            $stmt = $pdo->prepare("INSERT INTO utenti 
                (codice_alfanumerico, username, nome, cognome, email, codice_fiscale, password_hash, email_confermata, data_creazione) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");
            
            $stmt->execute([$id, $username, $nome, $cognome, $email, $cf_finale, $hash]);

            $success_msg = "Registrazione riuscita! Il tuo CF è: " . $cf_finale;
            // header("Location: /login"); exit; // Scommenta per redirect

        } catch (PDOException $e) {
            $error_msg = "Errore Database: " . $e->getMessage();
        } catch (Exception $e) {
            $error_msg = "Errore: " . $e->getMessage();
        } catch (TypeError $e) {
             $error_msg = "Errore dati: Controlla che la data e i campi siano corretti.";
        }
    } else {
        $error_msg = "Errore connessione database.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registrazione</title>
    </head>
    <body>

        <?php require_once './src/includes/header.php'; ?>
        <?php require_once './src/includes/navbar.php'; ?>

        <div class="container">

            <?php if (!empty($error_msg)): ?>
                <div class="error"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <h2>Registrati<?php echo $tipologia ?></h2>
            <form method="post">

                <label for="username">Username:</label>
                <input placeholder="Username" required type="text" id="username" name="username">

                <label for="nome">Nome:</label>
                <input placeholder="Nome" required type="text" id="nome" name="nome">

                <label for="cognome">Cognome:</label>
                <input placeholder="Cognome" required type="text" id="cognome" name="cognome">

                <?php if ($registratiConCodice) { ?>
                    <label for="codice_fiscale">Codice Fiscale:</label>
                    <input placeholder="Codice Fiscale" required type="text" id="codice_fiscale" name="codice_fiscale">
                <?php } else { ?>
                    <label for="comune_nascita">Comune di Nascita:</label>
                    <input placeholder="Comune di Nascita" required type="text" id="comune_nascita" name="comune_nascita">
                    <label for="data_nascita">Data di Nascita:</label>
                    <input placeholder="Data di Nascita" required type="date" id="data_nascita" name="data_nascita">
                    <label for="sesso">Sesso:</label>
                    <select required name="sesso" id="sesso">
                        <option value="">--Sesso--</option>
                        <optgroup label="Preferenze">
                            <option value="M">Maschio</option>
                            <option value="F">Femmina</option>
                        </optgroup>
                    </select>

                <?php } ?>
                <label for="email">Email:</label>
                <input placeholder="Email" required type="email" id="email" name="email">
                <label for="password">Password:</label>
                <input required type="password" id="password" name="password">
                <input placeholder="Password" type="submit" value="Registrami">
            </form>
            <?php if ($registratiConCodice) { ?>
                <a href="#" onclick='redirectConCodice(false)'>Non hai il codice fiscale?</a>
            <?php } else { ?>
                <a href="#" onclick='redirectConCodice(true)'>Hai il codice fiscale?</a>
            <?php } ?>

        </div>

        <?php require_once "./src/includes/footer.php" ?>

        <script>
            const redirectConCodice = (conCodice) => {
                const virtual_form = document.createElement("form");
                virtual_form.style.display = "none"
                virtual_form.method = "POST";
                virtual_form.action = "./signup"
                const decision = document.createElement("input");
                decision.name = "conCodiceFiscale";
                decision.type = "hidden";
                decision.value = conCodice;
                virtual_form.appendChild(decision)
                document.body.appendChild(virtual_form);
                virtual_form.submit();
            }
        </script>
    </body>
</html>