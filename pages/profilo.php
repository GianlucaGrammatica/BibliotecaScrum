<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', '/var/www/html/php_errors.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once './phpmailer.php';

$uid = $_SESSION['codice_utente'] ?? null;

/* -----------------------------------------------------------
   GESTIONE AJAX
----------------------------------------------------------- */

// 1. AJAX: Salva Username
if (isset($_POST['ajax_username']) && $uid) {
    header('Content-Type: application/json');
    $new_user = trim($_POST['ajax_username']);
    try {
        $chk = $pdo->prepare("SELECT 1 FROM utenti WHERE username = ? AND codice_alfanumerico != ?");
        $chk->execute([$new_user, $uid]);
        if ($chk->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Username già occupato!']);
        } else {
            $upd = $pdo->prepare("UPDATE utenti SET username = ? WHERE codice_alfanumerico = ?");
            $upd->execute([$new_user, $uid]);
            echo json_encode(['status' => 'success', 'message' => 'Username aggiornato con successo!']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Errore DB: ' . $e->getMessage()]);
    }
    exit;
}

// 2. AJAX: Invia Codice Email
if (isset($_POST['ajax_send_email_code']) && $uid) {
    header('Content-Type: application/json');
    $new_email = trim($_POST['email_dest']);
    
    try {
        $chk = $pdo->prepare("SELECT 1 FROM utenti WHERE email = ? AND codice_alfanumerico != ?");
        $chk->execute([$new_email, $uid]);
        if ($chk->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Questa email è già in uso!']);
            exit;
        }

        $otp = rand(100000, 999999);
        
        $stmt = $pdo->prepare("SELECT nome FROM utenti WHERE codice_alfanumerico = ?");
        $stmt->execute([$uid]);
        $u_data = $stmt->fetch();

        $mail = getMailer();
        $mail->addAddress($new_email, $u_data['nome']);
        $mail->isHTML(true);
        $mail->Subject = 'Codice verifica';
        $mail->Body = "Il tuo codice è: <b>$otp</b>";
        $mail->send();

        $_SESSION['temp_email_change'] = ['email' => $new_email, 'otp' => $otp];
        
        echo json_encode(['status' => 'success', 'message' => 'Codice inviato! Controlla la mail.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Errore invio: ' . $e->getMessage()]);
    }
    exit;
}

/* -----------------------------------------------------------
   GESTIONE POST CLASSICA (Ricaricamento pagina)
----------------------------------------------------------- */

$messaggio_alert = ""; // Variabile per messaggio finale PHP

if (!$uid) {
    header("Location: ./login");
    exit;
}
if (!isset($pdo)) { die('Errore connessione DB.'); }

// Conferma Codice Email (Step Finale)
if (isset($_POST['confirm_email_final'])) {
    $input_code = trim($_POST['otp_code']);
    if (isset($_SESSION['temp_email_change'])) {
        if ($input_code == $_SESSION['temp_email_change']['otp']) {
            try {
                $final_email = $_SESSION['temp_email_change']['email'];
                $upd = $pdo->prepare("UPDATE utenti SET email = ? WHERE codice_alfanumerico = ?");
                $upd->execute([$final_email, $uid]);
                unset($_SESSION['temp_email_change']);
                $messaggio_alert = "Email aggiornata con successo!";
            } catch (Exception $e) {
                $messaggio_alert = "Errore durante l'aggiornamento.";
            }
        } else {
            $messaggio_alert = "Codice errato. Riprova.";
        }
    }
}

/* ---- Recupero Dati Utente ---- */
$stm = $pdo->prepare("SELECT * FROM utenti WHERE codice_alfanumerico = ?");
$stm->execute([$uid]);
$utente = $stm->fetch(PDO::FETCH_ASSOC);

/* ---- Dati Accessori ---- */
$stm = $pdo->prepare("SELECT c.isbn FROM prestiti p JOIN copie c ON p.id_copia = c.id_copia WHERE p.codice_alfanumerico = ? AND p.data_restituzione IS NULL");
$stm->execute([$uid]);
$prestiti_attivi = $stm->fetchAll(PDO::FETCH_ASSOC);

$stm = $pdo->prepare("SELECT p.isbn FROM prenotazioni p WHERE p.codice_alfanumerico = ?");
$stm->execute([$uid]);
$prenotazioni = $stm->fetchAll(PDO::FETCH_ASSOC);

$libri_letti = [];
$badges = [];

require './src/includes/header.php';
require './src/includes/navbar.php';

function getCoverPath(string $isbn): string {
    $localPath = "public/bookCover/$isbn.png";
    if (file_exists($localPath)) { return $localPath; }
    return "public/assets/book_placeholder.jpg";
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
    /* CSS BASE */
    body { font-family: 'Poppins', sans-serif; }
    .grid { display: flex; flex-wrap: wrap; gap: 20px; }
    .card.cover-only { width: 120px; display: flex; flex-direction: column; text-decoration: none; color: #333; }
    .card.cover-only img { width: 120px; height: 180px; object-fit: cover; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    
    .info_column { 
        display: flex; flex-direction: column; width: auto; justify-content: flex-start; align-items: center; gap: 10px; 
    }
    .info_line { display: flex; flex-direction: row; width: 100%; justify-content: space-between; align-items: flex-start; gap: 20px; padding-top: 20px; }
    .info_pfp { border-radius: 100%; width: 240px; height: 240px; padding: 5px; border: solid 5px #3f5135; object-fit: cover; }
    .extend_all { width: 100%; height: 100%; justify-content: space-between; align-items: flex-start; }
    .section { width: 100%; height: auto; display: flex; flex-direction: column; .grid { width: 100%; padding: 5px; } }

    /* --- CSS EDITING E ANIMAZIONI --- */
    
    .edit-container-wrapper {
        margin-top: 20px; 
        width: 260px; /* Larghezza FISSA */
        display: flex; 
        flex-direction: column; 
        gap: 8px;
    }

    .edit-row {
        width: 100%;
        display: flex;
        align-items: center;
    }
    
    .edit-input {
        flex: 1; 
        min-width: 0;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        color: #333;
        font-family: 'Poppins', sans-serif;
        font-size: 1em;
        transition: all 0.3s ease;
    }
    .edit-input:disabled {
        background: #eee;
        color: #666;
        border: 1px solid transparent;
    }

    .btn-slide {
        width: 0; padding: 0; opacity: 0; margin-left: 0; overflow: hidden; white-space: nowrap;
        background-color: #3f5135; color: white; border: none; border-radius: 4px; font-size: 0.9em; cursor: pointer;
        transition: all 0.4s ease;
    }
    .edit-row.changed .btn-slide {
        width: 80px; padding: 8px 0; opacity: 1; margin-left: 5px;
    }

    /* --- ANIMAZIONE SOTTO LA MAIL --- */
    
    .email-expand-box {
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transition: all 0.5s ease;
        display: flex;
        gap: 5px;
        width: 100%;
    }

    .email-expand-box.open {
        max-height: 50px; 
        opacity: 1;
        margin-top: -3px; 
    }

    .otp-locked {
        background-color: #e0e0e0;
        color: #999;
        cursor: not-allowed;
        border-color: #ddd;
    }

    .btn-action-email {
        background-color: #3f5135;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 8px;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        width: 80px;
        transition: background 0.3s;
    }
    .btn-action-email:hover { background-color: #2c3a24; }
    
    .btn-success-anim { background-color: #27ae60 !important; }

    /* Modal */
    .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; backdrop-filter: blur(2px); }
    .modal-content { background-color: white; padding: 20px; border-radius: 16px; width: auto; max-width: 90%; text-align: center; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    .close-modal { position: absolute; top: 10px; right: 15px; font-size: 24px; font-weight: 600; color: #aaa; cursor: pointer; z-index: 1001; font-family: sans-serif; }
    .btn-tessera { margin-top: 10px; padding: 10px 20px; background-color: #3f5135; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-family: 'Poppins', sans-serif; transition: background 0.3s; }
    #tessera-card { font-family: 'Poppins', sans-serif; background-color: #ffffff; color: #000000; border: 2px solid #000; border-radius: 12px; width: 340px; height: 215px; margin: 25px auto; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.15); box-sizing: border-box; }
    .tessera-header { font-size: 1.1em; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; border-bottom: 2px solid #000; padding-bottom: 8px; width: 100%; text-align: center; }
    .tessera-user { font-size: 1.3em; font-weight: 500; text-align: center; word-wrap: break-word; margin-top: 10px; }
    .tessera-barcode { font-family: 'Libre Barcode 39 Text', cursive; font-size: 42px; color: #000; line-height: 1; white-space: nowrap; margin-bottom: 5px; }
    .modal-actions { display: flex; justify-content: center; gap: 15px; margin-top: 10px; width: 340px; margin-left: auto; margin-right: auto; }
    .btn-action { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-size: 14px; font-family: 'Poppins', sans-serif; font-weight: 500; flex: 1; transition: all 0.2s ease; }
    .btn-print { background-color: #3f5135; color: white; border-color: #3f5135; }
    .btn-download { background-color: #f8f9fa; color: #333; }

    /* CSS BANNER NOTIFICA */
    #notification-banner {
        position: fixed;
        bottom: -100px; /* Nascosto inizialmente */
        left: 50%;
        transform: translateX(-50%);
        background-color: #222;
        color: white;
        padding: 14px 24px;
        border-radius: 6px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: bottom 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Effetto rimbalzo leggero */
        z-index: 9999;
        min-width: 250px;
        justify-content: space-between;
        font-family: 'Poppins', sans-serif;
    }
    #notification-banner.show {
        bottom: 30px;
    }
    .notification-text {
        font-size: 15px;
        font-weight: 500;
    }
    .close-btn-banner {
        background: none;
        border: none;
        color: #bbb;
        font-size: 22px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        transition: color 0.2s;
    }
    .close-btn-banner:hover {
        color: white;
    }
</style>

<div class="info_line">

    <div class="info_column">

        <img class="info_pfp" alt="Pfp" src="<?= htmlspecialchars($utente['icona'] ?? 'public/assets/base_pfp.png') ?>">
        
        <button class="btn-tessera" onclick="apriTessera()">Tessera Utente</button>

        <div class="edit-container-wrapper">

            <div class="edit-row" id="row-username">
                <input type="text" id="inp-username" class="edit-input" 
                       value="<?= htmlspecialchars($utente['username'] ?? '') ?>" 
                       data-original="<?= htmlspecialchars($utente['username'] ?? '') ?>"
                       placeholder="Username">
                <button type="button" id="btn-user" class="btn-slide" onclick="ajaxSaveUsername()">Salva</button>
            </div>

            <div class="edit-row">
                <input type="email" id="inp-email" class="edit-input" 
                       value="<?= htmlspecialchars($utente['email'] ?? '') ?>" 
                       data-original="<?= htmlspecialchars($utente['email'] ?? '') ?>"
                       placeholder="Email"
                       oninput="handleEmailInput(this)">
            </div>

            <form method="post" class="email-expand-box" id="box-email-otp">
                <input type="text" name="otp_code" id="inp-otp" 
                       class="edit-input otp-locked" 
                       placeholder="Codice" 
                       disabled 
                       autocomplete="off">
                
                <button type="button" id="btn-email-action" class="btn-action-email" onclick="handleEmailAction()">Invia</button>
                
                <input type="hidden" name="confirm_email_final" value="1">
            </form>

            <div class="edit-row">
                <input type="text" class="edit-input" disabled value="<?= htmlspecialchars($utente['nome'] ?? '') ?>">
            </div>
            <div class="edit-row">
                <input type="text" class="edit-input" disabled value="<?= htmlspecialchars($utente['cognome'] ?? '') ?>">
            </div>
            <div class="edit-row">
                <input type="text" class="edit-input" disabled value="<?= htmlspecialchars($utente['codice_fiscale'] ?? '') ?>">
            </div>

        </div>
    </div>

    <div class="info_column extend_all">
        <div class="section">
            <h2>Badge</h2>
            <div class="grid">
                <?php if ($badges): foreach ($badges as $badge): endforeach; else: ?>
                    <h4>Nessun badge acquisito</h4>
                <?php endif; ?>
            </div>
        </div>
        <div class="section">
            <h2>Prestiti</h2>
            <div class="grid">
                <?php if ($prestiti_attivi): foreach ($prestiti_attivi as $libro): ?>
                        <a href="./libro?isbn=<?= htmlspecialchars($libro['isbn']) ?>" class="card cover-only">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                <?php endforeach; else: ?>
                    <h4>Nessun prestito trovato</h4>
                <?php endif; ?>
            </div>
        </div>
        <div class="section">
            <h2>Prenotazioni</h2>
            <div class="grid">
                <?php if ($prenotazioni): foreach ($prenotazioni as $libro): ?>
                        <a href="./libro?isbn=<?= htmlspecialchars($libro['isbn']) ?>" class="card cover-only">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                <?php endforeach; else: ?>
                    <h4>Nessuna prenotazione trovata</h4>
                <?php endif; ?>
            </div>
        </div>
        <div class="section">
            <h2>Libri Letti</h2>
            <div class="grid">
                <?php if ($libri_letti): foreach ($libri_letti as $libro): ?>
                        <a href="./libro?isbn=<?= htmlspecialchars($libro['isbn']) ?>" class="card cover-only">
                            <img src="<?= getCoverPath($libro['isbn']) ?>" alt="Libro">
                        </a>
                <?php endforeach; else: ?>
                    <h4>Nessun libro ancora letto</h4>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="modalTessera" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="chiudiTessera()">&times;</span>
        <div id="tessera-card">
            <div class="tessera-header">BibliotecaScrum</div>
            <div class="tessera-user">
                <?= htmlspecialchars(($utente['nome'] ?? '') . ' ' . ($utente['cognome'] ?? '')) ?>
            </div>
            <div class="tessera-barcode">*8473264*</div>
        </div>
        <div class="modal-actions">
            <button class="btn-action btn-download" onclick="scaricaPNG()">Scarica PNG</button>
            <button class="btn-action btn-print" onclick="stampa()">Stampa</button>
        </div>
    </div>
</div>

<div id="notification-banner">
    <span id="banner-msg" class="notification-text">Notifica</span>
    <button class="close-btn-banner" onclick="hideNotification()">&times;</button>
</div>

<script>
    // --- GESTIONE BANNER NOTIFICA ---
    let timeoutId;

    function showNotification(message) {
        const banner = document.getElementById('notification-banner');
        const msgSpan = document.getElementById('banner-msg');
        
        msgSpan.innerText = message; // Imposta il testo dinamico
        banner.classList.add('show');

        // Reset timer
        if (timeoutId) clearTimeout(timeoutId);
        timeoutId = setTimeout(() => { hideNotification(); }, 5000);
    }

    function hideNotification() {
        document.getElementById('notification-banner').classList.remove('show');
    }

    // --- CONTROLLO MESSAGGIO PHP AL CARICAMENTO ---
    // Se c'è un messaggio dal server (dopo il reload POST), lo mostriamo nel banner
    const serverMessage = "<?= addslashes($messaggio_alert) ?>";
    if (serverMessage.length > 0) {
        // Aspettiamo un attimo che la pagina sia renderizzata
        setTimeout(() => { showNotification(serverMessage); }, 500);
    }

    // --- USERNAME ---
    const inpUser = document.getElementById('inp-username');
    const rowUser = document.getElementById('row-username');
    const btnUser = document.getElementById('btn-user');

    inpUser.addEventListener('input', function() {
        if (this.value !== this.dataset.original) {
            rowUser.classList.add('changed');
            btnUser.innerText = "Salva"; btnUser.classList.remove('btn-success-anim');
        } else {
            rowUser.classList.remove('changed');
        }
    });

    async function ajaxSaveUsername() {
        const newVal = inpUser.value;
        const formData = new FormData();
        formData.append('ajax_username', newVal);

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.status === 'success') {
                showNotification(data.message); // Banner
                
                btnUser.innerText = "Fatto!";
                btnUser.classList.add('btn-success-anim');
                inpUser.dataset.original = newVal;
                setTimeout(() => { rowUser.classList.remove('changed'); }, 1500);
            } else { 
                showNotification(data.message); // Banner Errore
            }
        } catch (error) { 
            showNotification("Errore di connessione al server."); 
        }
    }

    // --- EMAIL LOGIC ---
    const boxEmailOtp = document.getElementById('box-email-otp');
    const inpEmail = document.getElementById('inp-email');
    const inpOtp = document.getElementById('inp-otp');
    const btnEmailAction = document.getElementById('btn-email-action');
    let emailStep = 1; 

    function handleEmailInput(input) {
        if (input.value !== input.dataset.original) {
            boxEmailOtp.classList.add('open');
            resetEmailState(); 
        } else {
            boxEmailOtp.classList.remove('open');
        }
    }

    function resetEmailState() {
        emailStep = 1;
        btnEmailAction.innerText = "Invia";
        btnEmailAction.type = "button";
        inpOtp.disabled = true;
        inpOtp.classList.add('otp-locked');
        inpOtp.value = "";
    }

    async function handleEmailAction() {
        if (emailStep === 1) {
            const newEmail = inpEmail.value;
            const formData = new FormData();
            formData.append('ajax_send_email_code', 1);
            formData.append('email_dest', newEmail);
            
            btnEmailAction.innerText = "...";

            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await response.json();

                if (data.status === 'success') {
                    showNotification(data.message); // Banner "Codice inviato"

                    emailStep = 2;
                    inpOtp.disabled = false;
                    inpOtp.classList.remove('otp-locked');
                    inpOtp.focus();
                    btnEmailAction.innerText = "Conferma";
                    btnEmailAction.type = "submit"; 
                } else {
                    showNotification(data.message); // Banner Errore
                    btnEmailAction.innerText = "Invia";
                }
            } catch (e) {
                console.error(e);
                showNotification("Errore di rete.");
                btnEmailAction.innerText = "Invia";
            }
        }
        // Step 2: Submit normale (gestito dal PHP + Banner al reload)
    }

    // Modale e Stampe
    const modal = document.getElementById('modalTessera');
    function apriTessera() { modal.style.display = 'flex'; }
    function chiudiTessera() { modal.style.display = 'none'; }
    window.onclick = function(event) { if (event.target == modal) { chiudiTessera(); } }
    function stampa() { window.print(); }
    function scaricaPNG() {
        const elemento = document.getElementById("tessera-card");
        html2canvas(elemento, { backgroundColor: "#ffffff", scale: 3 }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Tessera_BibliotecaScrum.png';
            link.href = canvas.toDataURL("image/png");
            link.click();
        });
    }
</script>

<?php require './src/includes/footer.php'; ?>