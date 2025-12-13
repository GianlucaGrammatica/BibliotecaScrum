<?php
require_once 'db_config.php';

$search_query = trim($_GET['search'] ?? '');
$result = [];

if (!empty($search_query)) {

    // array per condizioni WHERE
    $conditions = [];
    $params = [];

    // se non ci sono checkbox selezionate, consideriamo il titolo di default
    $is_filtered = isset($_GET['filtra_nome']) || isset($_GET['filtra_cognome']) || isset($_GET['filtra_editore']) || isset($_GET['filtra_descrizione']) || isset($_GET['filtra_titolo']);
    if (!$is_filtered || isset($_GET['filtra_titolo'])) {
        $conditions[] = "l.titolo LIKE :search";
        $params[':search'] = "%$search_query%";
    }

    if (isset($_GET['filtra_nome'])) {
        $conditions[] = "a.nome LIKE :search_nome";
        $params[':search_nome'] = "%$search_query%";
    }
    if (isset($_GET['filtra_cognome'])) {
        $conditions[] = "a.cognome LIKE :search_cognome";
        $params[':search_cognome'] = "%$search_query%";
    }
    if (isset($_GET['filtra_editore'])) {
        $conditions[] = "c.editore LIKE :search_editore";
        $params[':search_editore'] = "%$search_query%";
    }
    if (isset($_GET['filtra_descrizione'])) {
        $conditions[] = "l.descrizione LIKE :search_descrizione";
        $params[':search_descrizione'] = "%$search_query%";
    }

    if (!empty($conditions)) {
        $where_clause = "WHERE " . implode(" OR ", $conditions);
    } else {
        $where_clause = "";
    }

    // query completa con JOIN su autori e copie, DISTINCT per non ripetere libri
    $sql = "
        SELECT DISTINCT l.*, c.copertina
        FROM libri l
        LEFT JOIN autore_libro al ON al.isbn = l.isbn
        LEFT JOIN autori a ON a.id_autore = al.id_autore
        LEFT JOIN copie c ON c.isbn = l.isbn
        $where_clause
        ORDER BY l.titolo ASC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require './src/includes/header.php';
require './src/includes/navbar.php';
?>

<div class="page_contents">
    <h2>🔎 Filtri di Ricerca</h2>
    <form method="GET" action="">
        <!-- Manteniamo il valore della ricerca dalla navbar -->
        <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">

        <p>
            <label>
                <input type="checkbox" name="filtra_titolo" value="1" <?= isset($_GET['filtra_titolo']) ? 'checked' : '' ?> />
                Titolo
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="filtra_nome" value="1" <?= isset($_GET['filtra_nome']) ? 'checked' : '' ?> />
                Nome Autore
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="filtra_cognome" value="1" <?= isset($_GET['filtra_cognome']) ? 'checked' : '' ?> />
                Cognome Autore
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="filtra_editore" value="1" <?= isset($_GET['filtra_editore']) ? 'checked' : '' ?> />
                Editore
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="filtra_descrizione" value="1" <?= isset($_GET['filtra_descrizione']) ? 'checked' : '' ?> />
                Descrizione
            </label>
        </p>

        <button type="submit">Applica Filtri</button>
        <a href="?" style="margin-left:10px;">Reset</a>
    </form>


    <hr>

    <h1>Risultati della Ricerca</h1>

    <?php if (!empty($search_query) && !empty($result)): ?>
        <p>Trovati <strong><?= count($result) ?></strong> risultati per: <strong><?= $search_query ?></strong></p>

        <div class="grid" style="display:flex; flex-wrap: wrap; gap: 15px;">
            <?php foreach ($result as $book): ?>
                <div class="card" style="width: 180px; border:1px solid #ddd; padding:10px; border-radius:5px;">
                    <img src="<?= $book['copertina'] ?? 'src/assets/placeholder.jpg' ?>" alt="" style="width:100%; height:auto; display:block; margin-bottom:5px;">
                    <h3><?= $book['titolo'] ?></h3>
                    <p><strong>Editore:</strong> <?= $book['editore'] ?? 'Non specificato' ?></p>
                    <p style="font-size:0.9em;"><?= $book['descrizione'] ?? 'Descrizione non disponibile' ?></p>
                    <a href="/info_libro?isbn=<?= $book['isbn'] ?>" class="btn-dettagli" style="display:inline-block; margin-top:5px; padding:5px 10px; background:#2c3e50; color:#fff; text-decoration:none; border-radius:3px;">Dettagli</a>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif (!empty($search_query) && empty($result)): ?>
        <p>Nessun risultato trovato per: <strong><?= $search_query ?></strong></p>
        <p>Prova a modificare i filtri o il termine di ricerca.</p>
    <?php else: ?>
        <p>Inserisci un termine nella barra di ricerca in alto per iniziare.</p>
    <?php endif; ?>
</div>

<?php require './src/includes/footer.php'; ?>
