<?php
require_once 'db_config.php';

function highlight_text(?string $text, string $search): string {
    if ($text === null) return '';
    if ($search === '') return htmlspecialchars($text);
    $safe = htmlspecialchars($text);
    return preg_replace('/' . preg_quote($search, '/') . '/iu', '<mark>$0</mark>', $safe);
}

// Recupero della query di ricerca
$search_query = trim($_GET['search'] ?? '');

// Filtri checkbox
$filters = [
        'titolo' => $_GET['filtra_titolo'] ?? null,
        'nome' => $_GET['filtra_nome'] ?? null,
        'cognome' => $_GET['filtra_cognome'] ?? null,
        'editore' => $_GET['filtra_editore'] ?? null,
        'descrizione' => $_GET['filtra_descrizione'] ?? null,
];

// Filtri attivi (solo quelli effettivamente selezionati)
$active_filters = array_filter($filters);

// Risultati inizializzati
$result = [];

// Esegui ricerca solo se c'è query e almeno un filtro selezionato
if (!empty($search_query) && !empty($active_filters)) {
    $conditions = [];
    $params = [];

    if (!empty($filters['titolo'])) {
        $conditions[] = "l.titolo LIKE :titolo";
        $params[':titolo'] = "%$search_query%";
    }
    if (!empty($filters['nome'])) {
        $conditions[] = "COALESCE(a.nome,'') LIKE :nome";
        $params[':nome'] = "%$search_query%";
    }
    if (!empty($filters['cognome'])) {
        $conditions[] = "COALESCE(a.cognome,'') LIKE :cognome";
        $params[':cognome'] = "%$search_query%";
    }
    if (!empty($filters['editore'])) {
        $conditions[] = "COALESCE(c.editore,'') LIKE :editore";
        $params[':editore'] = "%$search_query%";
    }
    if (!empty($filters['descrizione'])) {
        $conditions[] = "l.descrizione LIKE :descrizione";
        $params[':descrizione'] = "%$search_query%";
    }

    if (!empty($conditions)) {
        $where = 'WHERE ' . implode(' OR ', $conditions);

        $sql = "
            SELECT
                l.*,
                c.copertina,
                c.editore,
                GROUP_CONCAT(DISTINCT a.nome SEPARATOR ', ') AS nome_autore,
                GROUP_CONCAT(DISTINCT a.cognome SEPARATOR ', ') AS cognome_autore
            FROM libri l
            LEFT JOIN autore_libro al ON al.isbn = l.isbn
            LEFT JOIN autori a ON a.id_autore = al.id_autore
            LEFT JOIN copie c ON c.isbn = l.isbn
            $where
            GROUP BY l.isbn
            ORDER BY l.titolo ASC
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        try {
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $result = [];
        }
    }
}

require './src/includes/header.php';
require './src/includes/navbar.php';
?>

<div class="page_contents">
    <h2>🔎 Filtri di Ricerca</h2>

    <form id="filter_form" method="GET">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">

        <label>
            <input type="checkbox" name="filtra_titolo" <?= !empty($filters['titolo']) ? 'checked' : '' ?>> Titolo
        </label><br>

        <label>
            <input type="checkbox" name="filtra_nome" <?= !empty($filters['nome']) ? 'checked' : '' ?>> Nome autore
        </label><br>

        <label>
            <input type="checkbox" name="filtra_cognome" <?= !empty($filters['cognome']) ? 'checked' : '' ?>> Cognome autore
        </label><br>

        <label>
            <input type="checkbox" name="filtra_editore" <?= !empty($filters['editore']) ? 'checked' : '' ?>> Editore
        </label><br>

        <label>
            <input type="checkbox" name="filtra_descrizione" <?= !empty($filters['descrizione']) ? 'checked' : '' ?>> Descrizione
        </label>
    </form>

    <hr>

    <h1>Risultati</h1>

    <?php if (!empty($search_query) && !empty($active_filters) && !empty($result)): ?>
        <p>Trovati <strong><?= count($result) ?></strong> risultati per <strong><?= htmlspecialchars($search_query) ?></strong></p>

        <div style="display:flex;flex-wrap:wrap;gap:15px;">
            <?php foreach ($result as $book): ?>
                <div style="width:180px;border:1px solid #ccc;padding:10px;border-radius:5px;">
                    <img src="<?= htmlspecialchars($book['copertina'] ?? 'src/assets/placeholder.jpg') ?>" style="width:100%">
                    <h3><?= highlight_text($book['titolo'], $search_query) ?></h3>

                    <?php if (!empty($filters['nome'])): ?>
                        <p><strong>Nome:</strong> <?= highlight_text($book['nome_autore'], $search_query) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($filters['cognome'])): ?>
                        <p><strong>Cognome:</strong> <?= highlight_text($book['cognome_autore'], $search_query) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($filters['editore'])): ?>
                        <p><strong>Editore:</strong> <?= highlight_text($book['editore'], $search_query) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($filters['descrizione'])): ?>
                        <p><strong>Descrizione:</strong> <?= highlight_text(substr($book['descrizione'] ?? '', 0, 100), $search_query) ?>...</p>
                    <?php endif; ?>

                    <a href="/info_libro?isbn=<?= htmlspecialchars($book['isbn']) ?>">Dettagli</a>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif (!empty($search_query) && !empty($active_filters)): ?>
        <p>Nessun risultato trovato.</p>
    <?php elseif (!empty($search_query)): ?>
        <p>Nessun filtro selezionato. Nessun risultato.</p>
    <?php else: ?>
        <p>Inserisci un termine di ricerca.</p>
    <?php endif; ?>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);

    // Aggiunge filtra_titolo=on solo se c'è search e nessun filtro selezionato
    // e non è un submit del form (auto_redirect)
    if (urlParams.has('search') &&
        !urlParams.has('filtra_titolo') &&
        !urlParams.has('filtra_nome') &&
        !urlParams.has('filtra_cognome') &&
        !urlParams.has('filtra_editore') &&
        !urlParams.has('filtra_descrizione') &&
        !urlParams.has('auto_redirect')) {

        urlParams.set('filtra_titolo', 'on');
        urlParams.set('auto_redirect', '1'); // evita loop
        window.location.search = urlParams.toString();
    }

    // Submit automatico al cambio dei checkbox
    document.querySelectorAll('#filter_form input[type=checkbox]').forEach(cb => {
        cb.addEventListener('change', () => {
            const form = document.getElementById('filter_form');
            const input = form.querySelector('input[name="auto_redirect"]');
            if(input) input.remove(); // rimuove parametro auto_redirect se presente
            form.submit();
        });
    });
</script>

<?php require './src/includes/footer.php'; ?>
