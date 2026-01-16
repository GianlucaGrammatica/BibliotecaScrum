<?php
require_once 'security.php';

if (!checkAccess('amministratore') && !checkAccess('bibliotecario')) {
    header('Location: ./');
    exit;
}

require_once 'db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$messaggio_db = "";
$class_messaggio = "";

// Paginazione e filtri
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$filterStato = $_GET['stato'] ?? ''; // pagata, non_pagata, tutto
$orderBy = $_GET['order'] ?? 'recenti';

// --- GESTIONE AZIONI POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // SEGNA COME PAGATA
    if (isset($_POST['paga_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE multe SET pagata = 1 WHERE id_multa = ?");
            $stmt->execute([$_POST['paga_id']]);
            $_SESSION['messaggio'] = "Multa segnata come pagata!";
            $_SESSION['tipo_messaggio'] = "success";
        } catch (PDOException $e) {
            $_SESSION['messaggio'] = "Errore: " . $e->getMessage();
            $_SESSION['tipo_messaggio'] = "error";
        }
        header("Location: dashboard-multe?page=$page&search=" . urlencode($search) . "&stato=$filterStato&order=$orderBy");
        exit;
    }

    // ELIMINA MULTA
    if (isset($_POST['delete_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM multe WHERE id_multa = ?");
            $stmt->execute([$_POST['delete_id']]);
            $_SESSION['messaggio'] = "Multa eliminata!";
            $_SESSION['tipo_messaggio'] = "success";
        } catch (PDOException $e) {
            $_SESSION['messaggio'] = "Errore: " . $e->getMessage();
            $_SESSION['tipo_messaggio'] = "error";
        }
        header("Location: dashboard-multe?page=$page&search=" . urlencode($search) . "&stato=$filterStato&order=$orderBy");
        exit;
    }
}

// Recupera messaggi dalla sessione
if (isset($_SESSION['messaggio'])) {
    $messaggio_db = $_SESSION['messaggio'];
    $class_messaggio = $_SESSION['tipo_messaggio'];
    unset($_SESSION['messaggio']);
    unset($_SESSION['tipo_messaggio']);
}

// --- STATISTICHE RAPIDE ---
$stats = [
    'totale_multe' => 0,
    'multe_aperte' => 0,
    'multe_pagate' => 0,
    'importo_totale_aperto' => 0,
    'importo_totale_incassato' => 0
];

try {
    // Totale multe
    $stmt = $pdo->query("SELECT COUNT(*) FROM multe");
    $stats['totale_multe'] = $stmt->fetchColumn();

    // Multe aperte
    $stmt = $pdo->query("
        SELECT COUNT(*) as num, SUM(importo) as totale 
        FROM multe 
        WHERE pagata = 0
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['multe_aperte'] = $result['num'] ?? 0;
    $stats['importo_totale_aperto'] = $result['totale'] ?? 0;

    // Multe pagate
    $stmt = $pdo->query("
        SELECT COUNT(*) as num, SUM(importo) as totale 
        FROM multe 
        WHERE pagata = 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['multe_pagate'] = $result['num'] ?? 0;
    $stats['importo_totale_incassato'] = $result['totale'] ?? 0;

} catch (PDOException $e) {
    error_log("Errore statistiche multe: " . $e->getMessage());
}

// --- QUERY MULTE CON FILTRI ---
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(u.username LIKE ? OR u.nome LIKE ? OR u.cognome LIKE ? OR m.causale LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filterStato === 'pagata') {
    $where[] = "m.pagata = 1";
} elseif ($filterStato === 'non_pagata') {
    $where[] = "m.pagata = 0";
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Ordinamento
$orderSQL = "ORDER BY m.data_creata DESC";
switch ($orderBy) {
    case 'vecchi':
        $orderSQL = "ORDER BY m.data_creata ASC";
        break;
    case 'importo_alto':
        $orderSQL = "ORDER BY m.importo DESC, m.data_creata DESC";
        break;
    case 'importo_basso':
        $orderSQL = "ORDER BY m.importo ASC, m.data_creata DESC";
        break;
}

try {
    // Count totale
    $countSQL = "
        SELECT COUNT(*) 
        FROM multe m
        JOIN utenti u ON m.codice_alfanumerico = u.codice_alfanumerico
        $whereSQL
    ";
    $stmtCount = $pdo->prepare($countSQL);
    $stmtCount->execute($params);
    $totalMulte = $stmtCount->fetchColumn();
    $totalPages = ceil($totalMulte / $perPage);

    // Query principale
    $sql = "
        SELECT m.*, 
               u.username, u.nome, u.cognome,
               l.titolo as libro_titolo,
               p.data_prestito, p.data_scadenza, p.data_restituzione
        FROM multe m
        JOIN utenti u ON m.codice_alfanumerico = u.codice_alfanumerico
        LEFT JOIN prestiti p ON m.id_prestito = p.id_prestito
        LEFT JOIN copie c ON p.id_copia = c.id_copia
        LEFT JOIN libri l ON c.isbn = l.isbn
        $whereSQL
        $orderSQL
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $multe = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $messaggio_db = "Errore caricamento multe: " . $e->getMessage();
    $class_messaggio = "error";
    $multe = [];
    $totalMulte = 0;
    $totalPages = 1;
}

$title = "Dashboard Multe";
$path = "../";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

<div class="page_contents">

    <h2 class="pages-title">
        Dashboard Multe
    </h2>

    <?php if (!empty($messaggio_db)): ?>
        <div class="alert <?= $class_messaggio === 'error' ? 'alert-error' : 'alert-success' ?>">
            <?= htmlspecialchars($messaggio_db) ?>
        </div>
    <?php endif; ?>

    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-label">Totale Multe</div>
            <div class="stat-value"><?= $stats['totale_multe'] ?></div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Multe Aperte</div>
            <div class="stat-value"><?= $stats['multe_aperte'] ?></div>
            <div class="stat-sublabel">€ <?= number_format($stats['importo_totale_aperto'], 2) ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Multe Pagate</div>
            <div class="stat-value"><?= $stats['multe_pagate'] ?></div>
            <div class="stat-sublabel">€ <?= number_format($stats['importo_totale_incassato'], 2) ?></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-label">Totale da Incassare</div>
            <div class="stat-value">€ <?= number_format($stats['importo_totale_aperto'], 2) ?></div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="filters-wrapper">
        <form method="GET" action="dashboard-multe">
            <div class="filters-row">
                <div class="filter-group">
                    <label class="filter-label">Cerca (Utente o Causale)</label>
                    <input type="text" name="search" class="filter-input"
                           placeholder="Username o causale..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="filter-group" style="flex: 0 0 150px;">
                    <label class="filter-label">Stato</label>
                    <select name="stato" class="filter-select">
                        <option value="">Tutte</option>
                        <option value="non_pagata" <?= $filterStato === 'non_pagata' ? 'selected' : '' ?>>Non Pagate</option>
                        <option value="pagata" <?= $filterStato === 'pagata' ? 'selected' : '' ?>>Pagate</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 0 0 150px;">
                    <label class="filter-label">Ordina per</label>
                    <select name="order" class="filter-select">
                        <option value="recenti" <?= $orderBy === 'recenti' ? 'selected' : '' ?>>Più recenti</option>
                        <option value="vecchi" <?= $orderBy === 'vecchi' ? 'selected' : '' ?>>Più vecchi</option>
                        <option value="importo_alto" <?= $orderBy === 'importo_alto' ? 'selected' : '' ?>>Importo alto</option>
                        <option value="importo_basso" <?= $orderBy === 'importo_basso' ? 'selected' : '' ?>>Importo basso</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <button type="submit" class="btn-filter">Filtra</button>
                    <a href="dashboard-multe" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Risultati -->
    <p style="margin-bottom: 15px; color: #666; font-family: 'Instrument Sans', sans-serif;">
        Trovate <strong><?= number_format($totalMulte) ?></strong> multe
    </p>

    <?php if (empty($multe)): ?>
        <div class="alert" style="background: #e9ecef; color: #333; text-align: center; padding: 40px;">
            <p style="margin: 0; font-size: 1.1rem;">💰 Nessuna multa trovata</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="multe-table">
                <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Utente</th>
                    <th>Libro</th>
                    <th>Causale</th>
                    <th style="width: 100px;">Importo</th>
                    <th style="width: 100px;">Data</th>
                    <th style="width: 100px;">Stato</th>
                    <th style="width: 200px;">Azioni</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($multe as $m): ?>
                    <tr>
                        <td><?= $m['id_multa'] ?></td>

                        <td>
                            <span class="user-info"><?= htmlspecialchars($m['username']) ?></span>
                            <br>
                            <small style="color: #888;">
                                <?= htmlspecialchars($m['nome'] . ' ' . $m['cognome']) ?>
                            </small>
                        </td>

                        <td>
                            <?php if ($m['libro_titolo']): ?>
                                <span style="font-weight: 600;"><?= htmlspecialchars($m['libro_titolo']) ?></span>
                                <br>
                                <small style="color: #888;">
                                    Prestito: <?= date('d/m/Y', strtotime($m['data_prestito'])) ?>
                                    <br>
                                    Scadenza: <?= date('d/m/Y', strtotime($m['data_scadenza'])) ?>
                                    <?php if ($m['data_restituzione']): ?>
                                        <br>
                                        Reso: <?= date('d/m/Y', strtotime($m['data_restituzione'])) ?>
                                    <?php endif; ?>
                                </small>
                            <?php else: ?>
                                <em style="color: #999;">Nessun prestito collegato</em>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= nl2br(htmlspecialchars($m['causale'])) ?>
                        </td>

                        <td>
                            <span class="importo">€ <?= number_format($m['importo'], 2) ?></span>
                        </td>

                        <td>
                            <?= date('d/m/Y', strtotime($m['data_creata'])) ?>
                        </td>

                        <td>
                            <?php if ($m['pagata'] == 1): ?>
                                <span class="badge-status badge-pagata">✓ Pagata</span>
                            <?php else: ?>
                                <span class="badge-status badge-aperta">Aperta</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a href="dettaglio-multa?id=<?= $m['id_multa'] ?>"
                               class="btn-action"
                               style="background-color: #3498db; color: white; text-decoration: none; display: inline-block; padding: 6px 12px; border-radius: 6px; margin: 2px;">
                                👁️ Dettaglio
                            </a>

                            <?php if ($m['pagata'] == 0): ?>
                                <form method="POST" style="display: inline;"
                                      onsubmit="return confirm('Segnare multa come pagata?\nImporto: €<?= number_format($m['importo'], 2) ?>')">
                                    <input type="hidden" name="paga_id" value="<?= $m['id_multa'] ?>">
                                    <button type="submit" class="btn-action btn-paga">Segna Pagata</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display: inline;"
                                  onsubmit="return confirm('Eliminare questa multa?\n\nATTENZIONE: Operazione irreversibile!')">
                                <input type="hidden" name="delete_id" value="<?= $m['id_multa'] ?>">
                                <button type="submit" class="btn-action btn-delete">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginazione -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&stato=<?= $filterStato ?>&order=<?= $orderBy ?>"
                       class="page-link">&laquo; Precedente</a>
                <?php endif; ?>

                <span class="page-link active">Pagina <?= $page ?> di <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&stato=<?= $filterStato ?>&order=<?= $orderBy ?>"
                       class="page-link">Successiva &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php require_once './src/includes/footer.php'; ?>
