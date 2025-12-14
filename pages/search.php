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

// Risultati inizializzati
$result = [];

if (!empty($search_query)) {
    // Recupero tutti i libri senza filtrare, i filtri saranno lato client
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
        GROUP BY l.isbn
        ORDER BY l.titolo ASC
    ";

    try {
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $result = [];
    }
}

require './src/includes/header.php';
require './src/includes/navbar.php';
?>

<div class="page_contents">
    <h2>🔎 Filtri di Ricerca</h2>

    <form id="filter_form" method="GET">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">

        <label><input type="checkbox" name="filtra_titolo" checked> Titolo</label><br>
        <label><input type="checkbox" name="filtra_nome" checked> Nome autore</label><br>
        <label><input type="checkbox" name="filtra_cognome" checked> Cognome autore</label><br>
        <label><input type="checkbox" name="filtra_editore" checked> Editore</label><br>
        <label><input type="checkbox" name="filtra_descrizione" checked> Descrizione</label>
    </form>

    <hr>

    <h1>Risultati</h1>

    <?php if (!empty($search_query) && !empty($result)): ?>
        <p>Trovati <strong id="results_count"><?= count($result) ?></strong> risultati per <strong><?= htmlspecialchars($search_query) ?></strong></p>

        <div style="display:flex;flex-wrap:wrap;gap:15px;" id="results_container">
            <?php foreach ($result as $book): ?>
                <div class="book_card"
                     data-titolo="<?= htmlspecialchars($book['titolo']) ?>"
                     data-nome="<?= htmlspecialchars($book['nome_autore']) ?>"
                     data-cognome="<?= htmlspecialchars($book['cognome_autore']) ?>"
                     data-editore="<?= htmlspecialchars($book['editore']) ?>"
                     data-descrizione="<?= htmlspecialchars($book['descrizione'] ?? '') ?>"
                     style="width:180px;border:1px solid #ccc;padding:10px;border-radius:5px;">
                    <img src="<?= htmlspecialchars($book['copertina'] ?? 'src/assets/placeholder.jpg') ?>" style="width:100%">
                    <h3 class="book_titolo"><?= highlight_text($book['titolo'], $search_query) ?></h3>

                    <p class="book_nome"><strong>Nome:</strong> <?= highlight_text($book['nome_autore'], $search_query) ?></p>
                    <p class="book_cognome"><strong>Cognome:</strong> <?= highlight_text($book['cognome_autore'], $search_query) ?></p>
                    <p class="book_editore"><strong>Editore:</strong> <?= highlight_text($book['editore'], $search_query) ?></p>
                    <p class="book_descrizione"><strong>Descrizione:</strong> <?= highlight_text(substr($book['descrizione'] ?? '', 0, 100), $search_query) ?>...</p>

                    <a href="/info_libro?isbn=<?= htmlspecialchars($book['isbn']) ?>">Dettagli</a>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif (!empty($search_query)): ?>
        <p>Nessun risultato trovato.</p>
    <?php else: ?>
        <p>Inserisci un termine di ricerca.</p>
    <?php endif; ?>
</div>

<script>
    const checkboxes = document.querySelectorAll('#filter_form input[type=checkbox]');
    const cards = document.querySelectorAll('.book_card');

    function highlightText(text, search) {
        if (!search) return text;
        const regex = new RegExp(`(${search})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    function filterResults() {
        const search = document.querySelector('input[name="search"]').value.trim();
        const activeFilters = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.name.replace('filtra_', ''));

        let visibleCount = 0;

        cards.forEach(card => {
            let show = false;

            activeFilters.forEach(field => {
                const value = card.dataset[field] || '';
                if (value.toLowerCase().includes(search.toLowerCase())) {
                    show = true;
                }
            });

            card.style.display = show ? 'block' : 'none';

            if (show) visibleCount++;

            // Aggiorna highlight dinamico
            if (show && search) {
                card.querySelectorAll('h3, p').forEach(el => {
                    const field = el.className.replace('book_', '');
                    if (activeFilters.includes(field)) {
                        el.innerHTML = highlightText(card.dataset[field], search);
                    }
                });
            }
        });

        // Aggiorna il contatore dinamicamente
        const countElem = document.getElementById('results_count');
        if(countElem) countElem.textContent = visibleCount;
    }

    // Evento onchange sui checkbox
    checkboxes.forEach(cb => cb.addEventListener('change', filterResults));

    // Applica filtro iniziale
    filterResults();
</script>

<?php require './src/includes/footer.php'; ?>
