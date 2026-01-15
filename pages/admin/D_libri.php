<?php
/**
 * D_libri.php - FIX BARCODE ORIGINALE + FIX NGROK
 */

// --- 1. LOGICA BARCODE ORIGINALE (ROBUSTA) ---
if (isset($_GET['generate_barcode'])) {
    while (ob_get_level()) ob_end_clean();
    require_once __DIR__ . '/../../vendor/autoload.php';

    $isbn = preg_replace('/[^0-9]/', '', $_GET['isbn'] ?? '');

    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        if (empty($isbn)) throw new Exception("Empty");

        // QUESTA È LA LOGICA ORIGINALE CHE VOLEVI
        // Tenta EAN-13, se il checksum è errato o fallisce, passa a Code-128
        if (strlen($isbn) === 13) {
            try {
                echo $generator->getBarcode($isbn, $generator::TYPE_EAN_13);
            } catch (Exception $e) {
                echo $generator->getBarcode($isbn, $generator::TYPE_CODE_128);
            }
        } else {
            echo $generator->getBarcode($isbn, $generator::TYPE_CODE_128);
        }
    } catch (Exception $e) {
        $img = imagecreate(150, 30);
        imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 2, 5, 5, "ERR", imagecolorallocate($img, 255, 0, 0));
        imagepng($img);
        imagedestroy($img);
    }
    exit;
}

// --- 2. SETUP E SICUREZZA ---
require_once 'security.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!checkAccess('amministratore')) header('Location: ./');

// FIX CONNESSIONE NGROK
try {
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 120);
    $pdo->exec("SET SESSION wait_timeout = 600");
    $pdo->exec("SET SESSION max_allowed_packet = 67108864"); 
} catch (Exception $e) {}

// Percorsi
$systemCoverDir = __DIR__ . '/../../public/bookCover/';
$webCoverDir = '../../public/bookCover/';
$placeholder = '../../public/assets/book_placeholder.jpg';

if (!is_dir($systemCoverDir)) { mkdir($systemCoverDir, 0777, true); }

$msg = "";
$msg_type = "success"; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = $_GET['search'] ?? '';

// --- 3. GESTIONE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CREATE
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $isbn = preg_replace('/[^0-9]/', '', $_POST['isbn']);
        $titolo = trim($_POST['titolo']);
        $anno = $_POST['anno_pubblicazione'];

        if (strlen($isbn) < 10) {
            $msg = "ISBN non valido."; $msg_type = "error";
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM libri WHERE isbn = ?");
            $check->execute([$isbn]);
            if ($check->fetchColumn() > 0) {
                $msg = "ISBN già presente."; $msg_type = "error";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO libri (isbn, titolo, anno_pubblicazione) VALUES (?, ?, ?)");
                    $stmt->execute([$isbn, $titolo, $anno]);

                    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                            move_uploaded_file($_FILES['cover']['tmp_name'], $systemCoverDir . $isbn . '.' . $ext);
                        }
                    }
                    $msg = "Libro aggiunto!";
                } catch (Exception $e) {
                    $msg = "Errore: " . $e->getMessage(); $msg_type = "error";
                }
            }
        }
    }

    // DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $isbn_to_delete = $_POST['isbn'];
        try {
            $pdo->beginTransaction();
            $stmtCopie = $pdo->prepare("SELECT id_copia FROM copie WHERE isbn = ?");
            $stmtCopie->execute([$isbn_to_delete]);
            $copieids = $stmtCopie->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($copieids)) {
                $chunks = array_chunk($copieids, 50);
                foreach ($chunks as $chunk) {
                    $inQuery = implode(',', array_fill(0, count($chunk), '?'));
                    $pdo->prepare("DELETE FROM prestiti WHERE id_copia IN ($inQuery)")->execute($chunk);
                    $pdo->prepare("DELETE FROM prenotazioni WHERE id_copia IN ($inQuery)")->execute($chunk);
                    $pdo->prepare("DELETE FROM richieste_bibliotecario WHERE id_copia IN ($inQuery)")->execute($chunk);
                }
                $pdo->prepare("DELETE FROM copie WHERE isbn = ?")->execute([$isbn_to_delete]);
            }
            $pdo->prepare("DELETE FROM autore_libro WHERE isbn = ?")->execute([$isbn_to_delete]);
            $pdo->prepare("DELETE FROM libro_categoria WHERE isbn = ?")->execute([$isbn_to_delete]);
            $pdo->prepare("DELETE FROM recensioni WHERE isbn = ?")->execute([$isbn_to_delete]);
            $pdo->prepare("DELETE FROM libri WHERE isbn = ?")->execute([$isbn_to_delete]);
            
            @unlink($systemCoverDir . $isbn_to_delete . '.jpg');
            @unlink($systemCoverDir . $isbn_to_delete . '.png');

            $pdo->commit();
            $msg = "Libro eliminato.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "Errore: " . $e->getMessage(); $msg_type = "error";
        }
    }

    // UPDATE
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $old_isbn = $_POST['old_isbn'];
        $new_isbn = preg_replace('/[^0-9]/', '', $_POST['isbn']);
        $titolo = $_POST['titolo'];
        $anno = $_POST['anno_pubblicazione'];

        try {
            $pdo->beginTransaction();
            if ($old_isbn != $new_isbn) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                $pdo->prepare("UPDATE libri SET isbn = ?, titolo = ?, anno_pubblicazione = ? WHERE isbn = ?")->execute([$new_isbn, $titolo, $anno, $old_isbn]);
                foreach(['copie','autore_libro','libro_categoria','recensioni'] as $t) {
                    $pdo->prepare("UPDATE $t SET isbn = ? WHERE isbn = ?")->execute([$new_isbn, $old_isbn]);
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

                if (empty($_FILES['cover']['name'])) {
                    if (file_exists($systemCoverDir . $old_isbn . '.png')) rename($systemCoverDir . $old_isbn . '.png', $systemCoverDir . $new_isbn . '.png');
                    elseif (file_exists($systemCoverDir . $old_isbn . '.jpg')) rename($systemCoverDir . $old_isbn . '.jpg', $systemCoverDir . $new_isbn . '.jpg');
                }
            } else {
                $pdo->prepare("UPDATE libri SET titolo = ?, anno_pubblicazione = ? WHERE isbn = ?")->execute([$titolo, $anno, $old_isbn]);
            }

            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    @unlink($systemCoverDir . $new_isbn . '.jpg');
                    @unlink($systemCoverDir . $new_isbn . '.png');
                    move_uploaded_file($_FILES['cover']['tmp_name'], $systemCoverDir . $new_isbn . '.' . $ext);
                }
            }

            $pdo->commit();
            header("Location: dashboard-libri?page=" . $page . "&search=" . urlencode($search)); 
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $msg = "Errore: " . $e->getMessage(); $msg_type = "error";
        }
    }
}

// --- 4. LETTURA DATI (10 per pagina per sicurezza Ngrok) ---
try {
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    $whereClause = ""; $params = [];
    if (!empty($search)) {
        $whereClause = "WHERE isbn LIKE ? OR titolo LIKE ?";
        $params[] = "%$search%"; $params[] = "%$search%";
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM libri $whereClause");
    $countStmt->execute($params);
    $total_books = $countStmt->fetchColumn();
    $total_pages = ceil($total_books / $per_page);

    $sql = "SELECT isbn, titolo, anno_pubblicazione FROM libri $whereClause ORDER BY anno_pubblicazione DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $libri = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Errore DB: " . $e->getMessage()); }

$edit_isbn = $_GET['edit'] ?? null;
$title = "Dashboard Catalogo Libri";
$path = "../";
require_once './src/includes/header.php';
require_once './src/includes/navbar.php';
?>

<style>
    /* Loader e Modal */
    #loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.85); z-index: 99999; display: none; justify-content: center; align-items: center; flex-direction: column; backdrop-filter: blur(2px); }
    .spinner { width: 50px; height: 50px; border: 5px solid #eae3d2; border-top: 5px solid #333; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; }
    
    #cover-modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); animation: fadeIn 0.2s; cursor: pointer; }
    .modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; margin-top: 5vh; border-radius: 8px; box-shadow: 0 0 20px rgba(255,255,255,0.2); }
    .modal-caption { margin: auto; display: block; width: 80%; text-align: center; color: #ccc; padding: 10px 0; font-family: "Instrument Sans", sans-serif; font-size: 1.2rem; }
    @keyframes fadeIn { from {opacity: 0} to {opacity: 1} }

    /* Layout */
    .dashboard-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; font-family: "Instrument Sans", sans-serif; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 3px solid #eae3d2; padding-bottom: 15px; flex-wrap: wrap; gap: 15px;}
    .page-title { font-family: "Young Serif", serif; font-size: 2.5rem; color: #333; margin: 0; }
    .header-actions { display: flex; gap: 10px; }

    .search-bar-container { background: #faf9f6; padding: 15px; border-radius: 12px; border: 1px solid #eae3d2; margin-bottom: 25px; display: flex; gap: 10px; align-items: center; }
    .search-input { flex-grow: 1; padding: 10px 15px; border: 1px solid #ccc; border-radius: 8px; font-family: "Instrument Sans", sans-serif; font-size: 1rem; }
    .btn-search { background-color: #333; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
    .btn-reset { background-color: #eae3d2; color: #333; padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; border: 1px solid #ccc; }

    .table-card { background: #fff; border-radius: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #f0f0f0; margin-bottom: 20px; }
    .admin-table { width: 100%; border-collapse: collapse; min-width: 800px; }
    .admin-table thead { background-color: #eae3d2; }
    .admin-table th { padding: 18px 20px; text-align: left; font-family: "Young Serif", serif; font-size: 1.1rem; color: #2c2c2c; font-weight: normal; }
    .admin-table td { padding: 15px 20px; border-bottom: 1px solid #f5f5f5; vertical-align: middle; color: #555; }
    
    .barcode-wrapper { display: flex; flex-direction: column; align-items: center; background: #fff; padding: 5px; border-radius: 8px; border: 1px solid #eee; width: fit-content; margin: 0 auto; }
    .barcode-img { display: block; max-width: 120px; height: 30px; }
    .isbn-text { font-family: monospace; font-size: 0.85rem; margin-top: 2px; color: #666; }

    .btn-action { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-family: "Instrument Sans", sans-serif; font-weight: 600; font-size: 0.95rem; transition: all 0.2s; text-decoration: none; display: inline-block; }
    .btn-save { background-color: #333; color: #fff; }
    .btn-save:hover { background-color: #555; transform: translateY(-2px); }
    .btn-fetcher { background-color: #5d5dff; color: #fff; }
    .btn-preview { background-color: #f0f0f0; color: #333; border: 1px solid #ccc; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 5px; font-weight: 600; margin-bottom: 5px;}
    .btn-preview:hover { background-color: #e0e0e0; }

    .btn-edit { background-color: #eae3d2; color: #333; border: 1px solid #dcdcdc; padding: 8px 16px; font-size: 0.9rem; }
    .btn-delete { background-color: #fff; border: 2px solid #f8d7da; color: #721c24; padding: 8px 16px; font-size: 0.9rem; margin-left: 5px; }
    .btn-cancel { background-color: #ccc; color: #333; margin-left: 5px; padding: 8px 16px; font-size: 0.9rem;}
    
    .edit-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 5px; box-sizing: border-box;}
    .file-input-wrapper { margin-top: 10px; border: 1px dashed #ccc; padding: 10px; border-radius: 6px; background: #fafafa; }

    .alert-msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert-success { background: #d4edda; color: #155724; }
    .alert-error { background: #f8d7da; color: #721c24; }
    
    .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; font-family: "Instrument Sans", sans-serif; }
    .page-link { padding: 10px 15px; background: #fff; border: 1px solid #eae3d2; border-radius: 8px; text-decoration: none; color: #333; }
    .page-link.active { background: #333; color: #fff; border-color: #333; }

    #add-book-section { display: none; margin-bottom: 30px; animation: slideDown 0.3s ease-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .add-form-wrapper { background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #eae3d2; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
    .form-group { flex: 1; min-width: 200px; }
    .form-label { display: block; font-size: 0.9rem; margin-bottom: 5px; color: #666; font-weight: 600; }
</style>

<div id="loading-overlay">
    <div class="spinner"></div>
    <div class="loading-text">Elaborazione...</div>
</div>

<div id="cover-modal">
    <img class="modal-content" id="img-full">
    <div id="caption" class="modal-caption"></div>
</div>

<div class="dashboard-container">
    <div class="page-header">
        <h2 class="page-title">Gestione Catalogo</h2>
        <div class="header-actions">
            <a href="/cover-fetcher" class="btn-action btn-fetcher trigger-loader">Cover Fetcher</a>
            <button onclick="toggleAddForm()" class="btn-action btn-save">+ Nuovo Libro</button>
        </div>
    </div>

    <?php if(!empty($msg)): ?>
        <div class="alert-msg <?= ($msg_type == 'error') ? 'alert-error' : 'alert-success' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div id="add-book-section">
        <form method="POST" class="add-form-wrapper form-spam-protect" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">ISBN (Solo numeri)</label>
                <input type="text" name="isbn" class="edit-input" required placeholder="Es. 9788804719999">
            </div>
            <div class="form-group">
                <label class="form-label">Titolo del Libro</label>
                <input type="text" name="titolo" class="edit-input" required placeholder="Titolo completo">
            </div>
            <div class="form-group" style="flex: 0 0 100px;">
                <label class="form-label">Anno</label>
                <input type="number" name="anno_pubblicazione" class="edit-input" required placeholder="2024">
            </div>
            <div class="form-group">
                <label class="form-label">Copertina</label>
                <input type="file" name="cover" accept=".jpg,.jpeg,.png" style="font-size:0.9rem;">
            </div>
            <button type="submit" class="btn-action btn-save" style="margin-bottom: 5px;">Salva Libro</button>
        </form>
    </div>

    <form method="GET" class="search-bar-container">
        <input type="text" name="search" class="search-input" placeholder="Cerca per ISBN o Titolo..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-search trigger-loader">Cerca</button>
        <?php if(!empty($search)): ?>
            <a href="dashboard-libri" class="btn-reset trigger-loader">Resetta</a>
        <?php endif; ?>
    </form>

    <div class="table-card">
        <div class="table-responsive" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 180px; text-align: center;">Barcode</th>
                        <th>Dettagli Libro</th>
                        <th style="width: 200px; text-align: center;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($libri)): ?>
                    <tr><td colspan="3" style="text-align:center; padding: 30px;">Nessun libro trovato.</td></tr>
                <?php else: ?>
                    <?php foreach ($libri as $b): ?>
                        <?php 
                            $is_editing = ($edit_isbn == $b['isbn']); 
                            
                            // CALCOLO URL COVER MA SENZA MOSTRARLA
                            $coverSrc = $placeholder;
                            if (file_exists($systemCoverDir . $b['isbn'] . '.png')) { 
                                $coverSrc = $webCoverDir . $b['isbn'] . '.png'; 
                            } elseif (file_exists($systemCoverDir . $b['isbn'] . '.jpg')) { 
                                $coverSrc = $webCoverDir . $b['isbn'] . '.jpg'; 
                            }
                            $coverSrc .= '?v=' . time();
                        ?>
                        <tr>
                            <td style="text-align: center;">
                                <div class="barcode-wrapper">
                                    <img src="dashboard-libri?generate_barcode=1&isbn=<?= htmlspecialchars($b['isbn']) ?>"
                                         alt="Barcode" class="barcode-img">
                                    <div class="isbn-text"><?= htmlspecialchars($b['isbn']) ?></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($is_editing): ?>
                                    <form id="form_edit_<?= $b['isbn'] ?>" method="POST" action="dashboard-libri?page=<?= $page ?>&search=<?= urlencode($search) ?>" enctype="multipart/form-data" class="form-spam-protect">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="old_isbn" value="<?= htmlspecialchars($b['isbn']) ?>">
                                        <input type="text" name="titolo" class="edit-input" value="<?= htmlspecialchars($b['titolo']) ?>" required placeholder="Titolo">
                                        <div style="display:flex; gap:10px;">
                                            <input type="text" name="isbn" class="edit-input" value="<?= htmlspecialchars($b['isbn']) ?>" required placeholder="ISBN">
                                            <input type="number" name="anno_pubblicazione" class="edit-input" style="width:100px;" value="<?= htmlspecialchars($b['anno_pubblicazione']) ?>" placeholder="Anno">
                                        </div>
                                        <div class="file-input-wrapper">
                                            <label>Nuova Copertina (JPG/PNG):</label>
                                            <input type="file" name="cover" accept=".jpg,.jpeg,.png">
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div>
                                        <button type="button" class="btn-preview" onclick="openCover('<?= $coverSrc ?>', '<?= addslashes($b['titolo']) ?>')">
                                            👁️ Vedi Cover
                                        </button>
                                        
                                        <div style="font-weight: 600; font-size: 1.1rem; color: #333;">
                                            <?= htmlspecialchars($b['titolo']) ?>
                                        </div>
                                        <div style="font-size: 0.9rem; color: #888; margin-top: 4px;">
                                            <?= htmlspecialchars($b['anno_pubblicazione']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($is_editing): ?>
                                    <button type="submit" form="form_edit_<?= $b['isbn'] ?>" class="btn-action btn-save trigger-loader">Salva</button>
                                    <a href="dashboard-libri?page=<?= $page ?>&search=<?= urlencode($search) ?>" class="btn-action btn-cancel trigger-loader">Annulla</a>
                                <?php else: ?>
                                    <div style="display: flex; justify-content: center; gap: 5px;">
                                        <a href="?page=<?= $page ?>&search=<?= urlencode($search) ?>&edit=<?= htmlspecialchars($b['isbn']) ?>" class="btn-action btn-edit trigger-loader">Modifica</a>
                                        <form method="POST" class="form-spam-protect" onsubmit="return confirm('Eliminare libro e storico?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="isbn" value="<?= htmlspecialchars($b['isbn']) ?>">
                                            <button type="submit" class="btn-action btn-delete trigger-loader">Elimina</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-link trigger-loader">&laquo;</a>
        <?php endif; ?>
        <span class="page-link active">Pag <?= $page ?>/<?= $total_pages ?></span>
        <?php if($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-link trigger-loader">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    function toggleAddForm() {
        var x = document.getElementById("add-book-section");
        x.style.display = (x.style.display === "block") ? "none" : "block";
    }

    // GESTIONE MODAL PREVIEW
    var modal = document.getElementById("cover-modal");
    var modalImg = document.getElementById("img-full");
    var captionText = document.getElementById("caption");

    function openCover(src, title) {
        modal.style.display = "block";
        modalImg.src = src;
        captionText.innerHTML = title;
    }

    modal.onclick = function() { 
        modal.style.display = "none";
    }

    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('loading-overlay');
        document.querySelectorAll('.trigger-loader').forEach(btn => {
            btn.addEventListener('click', () => overlay.style.display = 'flex');
        });
        document.querySelectorAll('.form-spam-protect').forEach(form => {
            form.addEventListener('submit', () => overlay.style.display = 'flex');
        });
    });
</script>

<?php require_once './src/includes/footer.php'; ?>