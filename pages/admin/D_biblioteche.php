<?php
require_once 'security.php';
require_once 'db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica accesso amministratore
if (!checkAccess('amministratore')) {
    header('Location: ./');
    exit;
}

$messaggio_db = "";
$class_messaggio = "";

// Verifica connessione database
if (!isset($pdo)) {
    die("Connessione al Database non riuscita");
}

// --- OPERAZIONI CRUD ---
try {
    // ELIMINA
    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM biblioteche WHERE id = :id");
        $stmt->execute(['id' => $_POST['delete_id']]);
        header("Location: dashboard-biblioteche.php");
        exit;
    }

    // MODIFICA
    if (isset($_POST['edit_id'])) {
        $stmt = $pdo->prepare("
            UPDATE biblioteche 
            SET nome = :nome, indirizzo = :indirizzo, lat = :lat, lon = :lon
            WHERE id = :id
        ");
        $stmt->execute([
                'nome' => $_POST['nome'],
                'indirizzo' => $_POST['indirizzo'],
                'lat' => $_POST['lat'],
                'lon' => $_POST['lon'],
                'id' => $_POST['edit_id']
        ]);
        header("Location: dashboard-biblioteche.php");
        exit;
    }

    // INSERISCI
    if (isset($_POST['inserisci'])) {
        $stmt = $pdo->prepare("
            INSERT INTO biblioteche (nome, indirizzo, lat, lon, orari)
            VALUES (:nome, :indirizzo, :lat, :lon, :orari)
        ");
        $stmt->execute([
                'nome' => $_POST['nome'],
                'indirizzo' => $_POST['indirizzo'],
                'lat' => $_POST['lat'],
                'lon' => $_POST['lon'],
                'orari' => $_POST['orari'] ?? ''
        ]);
        header("Location: dashboard-biblioteche.php");
        exit;
    }

    // Recupera tutte le biblioteche
    $stmt = $pdo->prepare("SELECT * FROM biblioteche ORDER BY nome");
    $stmt->execute();
    $biblioteche = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $messaggio_db = "Errore: " . $e->getMessage();
    $class_messaggio = "error";
}
?>

<?php
$title = "Dashboard Biblioteche";
$path = "../";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

<div class="page_contents">
    <?php if ($messaggio_db): ?>
        <div class="message <?= $class_messaggio ?>">
            <?= htmlspecialchars($messaggio_db) ?>
        </div>
    <?php endif; ?>

    <h2>Gestione Biblioteche</h2>

    <!-- Form inserimento nuova biblioteca -->
    <h3>Inserisci nuova biblioteca</h3>
    <table style="margin-bottom: 40px">
        <tr>
            <th>Nome</th>
            <th>Indirizzo</th>
            <th>Latitudine</th>
            <th>Longitudine</th>
            <th>Orari</th>
            <th>Azioni</th>
        </tr>
        <tr>
            <form method="post">
                <td><input type="text" placeholder="Nome biblioteca" name="nome" required></td>
                <td><input type="text" placeholder="Via, Città" name="indirizzo" required></td>
                <td><input type="number" step="any" placeholder="45.123" name="lat" required></td>
                <td><input type="number" step="any" placeholder="9.123" name="lon" required></td>
                <td><input type="text" placeholder="Lun-Ven 9-18" name="orari"></td>
                <input type="hidden" name="inserisci" value="1">
                <td><button type="submit">Inserisci</button></td>
            </form>
        </tr>
    </table>

    <!-- Elenco biblioteche esistenti -->
    <h3>Biblioteche esistenti</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Indirizzo</th>
            <th>Latitudine</th>
            <th>Longitudine</th>
            <th>Azioni</th>
        </tr>

        <?php if (empty($biblioteche)): ?>
            <tr>
                <td colspan="6" style="text-align: center;">Nessuna biblioteca presente</td>
            </tr>
        <?php else: ?>
            <?php foreach ($biblioteche as $b): ?>
                <tr>
                    <form method="POST">
                        <td><?= htmlspecialchars($b['id']) ?></td>
                        <td>
                            <input type="text" name="nome"
                                   value="<?= htmlspecialchars($b['nome']) ?>" required>
                        </td>
                        <td>
                            <input type="text" name="indirizzo"
                                   value="<?= htmlspecialchars($b['indirizzo']) ?>" required>
                        </td>
                        <td>
                            <input type="number" step="any" name="lat"
                                   value="<?= htmlspecialchars($b['lat']) ?>" required>
                        </td>
                        <td>
                            <input type="number" step="any" name="lon"
                                   value="<?= htmlspecialchars($b['lon']) ?>" required>
                        </td>
                        <td>
                            <input type="hidden" name="edit_id" value="<?= $b['id'] ?>">
                            <button type="submit">Salva</button>
                    </form>

                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= $b['id'] ?>">
                        <button type="submit"
                                onclick="return confirm('Eliminare <?= htmlspecialchars($b['nome']) ?>?')">
                            Elimina
                        </button>
                    </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<?php require_once './src/includes/footer.php'; ?>

<style>
    th, td {
        padding: 15px;
        border: solid 1px black;
    }
    table {
        border-collapse: collapse;
        width: 100%;
    }
    .message {
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
    }
    .success {
        background-color: #d4edda;
        color: #155724;
    }
    .error {
        background-color: #f8d7da;
        color: #721c24;
    }
    input[type="text"], input[type="number"] {
        width: 90%;
        padding: 5px;
    }
</style>