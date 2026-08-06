<?php
session_start();

// Povezivanje sa SQLite bazom
$db = new PDO('sqlite:videobaza.db');
$db->exec("CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    naslov TEXT, 
    embed_kod TEXT, 
    kategorija TEXT,
    datum DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Kredencijali (provjeravaju se na serveru - nevidljivo u Inspect elementu)
$ADMIN_USER = "luka";
$ADMIN_PASS = "Lu7172775775!";

$login_error = "";
if (isset($_POST['admin_login'])) {
    if ($_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
        $_SESSION['admin_logged'] = true;
    } else {
        $login_error = "Pogrešno korisničko ime ili lozinka!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Dodavanje novog videa (samo za prijavljenog admina)
if (isset($_POST['add_video']) && isset($_SESSION['admin_logged'])) {
    $naslov = $_POST['naslov'];
    $embed_kod = $_POST['embed_kod'];
    $kat = $_POST['kategorija'];

    if (!empty($naslov) && !empty($embed_kod) && !empty($kat)) {
        $stmt = $db->prepare("INSERT INTO videos (naslov, embed_kod, kategorija) VALUES (:naslov, :embed_kod, :kat)");
        $stmt->bindValue(':naslov', $naslov);
        $stmt->bindValue(':embed_kod', $embed_kod);
        $stmt->bindValue(':kat', $kat);
        $stmt->execute();
        header("Location: index.php?cat=" . urlencode($kat));
        exit();
    }
}

// Brisanje videa
if (isset($_GET['delete']) && isset($_SESSION['admin_logged'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM videos WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

// Postavke kategorija i paginacije
$categories = [
    "CNC / BDSM / Hardcore",
    "Random",
    "Blowjob / Deepthroat",
    "Public",
    "Weird / Funny",
    "Anal",
    "Boob Job",
    "Hentai",
    "Group"
];

$selected_cat = isset($_GET['cat']) ? $_GET['cat'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 52; // Grid: 4x13 na PC, 1x13 na mobitelu
$offset = ($page - 1) * $limit;

// Dohvatanje videa
if ($selected_cat !== '') {
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE kategorija = :kat");
    $count_stmt->bindValue(':kat', $selected_cat);
    $count_stmt->execute();
    $total_videos = $count_stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM videos WHERE kategorija = :kat ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':kat', $selected_cat);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $videi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $total_videos = 0;
    $videi = [];
}

$total_pages = ceil($total_videos / $limit);
?>

<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Portal</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #121212; color: #fff; margin: 0; padding: 0; }
        
        /* Top Navigation Header */
        header { background: #1f1f1f; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; }
        .logo { font-size: 1.5rem; font-weight: bold; color: #e50914; text-decoration: none; }
        .admin-btn { background: #e50914; color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-weight: bold; text-decoration: none; }
        .admin-btn:hover { background: #b20710; }

        .container { max-width: 1400px; margin: 20px auto; padding: 0 15px; }

        /* Admin Dashboard & Login Forms */
        .admin-panel { background: #1e1e1e; border: 1px solid #e50914; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .admin-panel input, .admin-panel select, .admin-panel textarea { width: 100%; padding: 10px; margin: 8px 0; background: #2a2a2a; color: white; border: 1px solid #444; border-radius: 5px; }
        
        /* Category Buttons */
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 20px; }
        .cat-card { background: #222; color: white; border: 1px solid #333; padding: 15px; text-align: center; border-radius: 8px; text-decoration: none; font-weight: bold; transition: 0.2s; }
        .cat-card:hover { background: #e50914; border-color: #e50914; }

        /* Back Button & Header */
        .cat-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .back-btn { background: #333; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; }
        .back-btn:hover { background: #555; }

        /* Video Layout (Grid Layout) */
        .video-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        @media (max-width: 768px) {
            .video-grid { grid-template-columns: 1fr; }
        }

        .video-card { background: #1a1a1a; border-radius: 6px; padding: 10px; border: 1px solid #2a2a2a; }
        .video-card h3 { font-size: 0.95rem; margin: 0 0 8px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .video-embed { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000; border-radius: 4px; }
        .video-embed iframe { position: absolute; top:0; left:0; width:100%; height:100%; border:0; }

        /* Pagination Styling */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin: 30px 0; }
        .pagination a, .pagination span { display: inline-flex; justify-content: center; align-items: center; width: 40px; height: 40px; border-radius: 50%; background: #2b2b2b; color: white; text-decoration: none; font-weight: bold; }
        .pagination a.active { background: #e50914; }
        .pagination a:hover:not(.active) { background: #444; }
    </style>
</head>
<body>

<header>
    <a href="index.php" class="logo">VIDEO PORTAL</a>
    <?php if (isset($_SESSION['admin_logged'])): ?>
        <div>
            <span style="margin-right:10px;">Admin: <b>Luka</b></span>
            <a href="index.php?logout=1" class="admin-btn">Odjava</a>
        </div>
    <?php else: ?>
        <button onclick="document.getElementById('loginModal').style.display='block'" class="admin-btn">Prijava kao admin</button>
    <?php endif; ?>
</header>

<div class="container">

    <!-- LOGIN PANEL -->
    <div id="loginModal" class="admin-panel" style="display: <?php echo $login_error ? 'block' : 'none'; ?>;">
        <h3>Admin Prijava</h3>
        <?php if ($login_error) echo "<p style='color:red;'>$login_error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Korisničko ime" required>
            <input type="password" name="password" placeholder="Šifra" required>
            <button type="submit" name="admin_login" class="admin-btn" style="width:100%; margin-top:5px;">Prijavi se</button>
        </form>
    </div>

    <!-- ADMIN PANEL - ADD VIDEO -->
    <?php if (isset($_SESSION['admin_logged'])): ?>
        <div class="admin-panel">
            <h3>Dodaj novi video</h3>
            <form method="POST">
                <input type="text" name="naslov" placeholder="Naslov Videa" required>
                <textarea name="embed_kod" rows="3" placeholder="Kopiraj pCloud / TeraBox Embed (iframe) kod ovdje" required></textarea>
                <select name="kategorija" required>
                    <option value="">-- Izaberi Kategoriju --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_video" class="admin-btn" style="width:100%; margin-top:10px;">Objavi Video</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- POČETNA STRANICA - KATEGORIJE -->
    <?php if ($selected_cat === ''): ?>
        <h2>Izaberite Kategoriju</h2>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat=<?php echo urlencode($cat); ?>" class="cat-card">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

    <!-- PRIKAZ VIDEA UNUTAR KATEGORIJE -->
    <?php else: ?>
        <div class="cat-header">
            <a href="index.php" class="back-btn">&#8592; Back</a>
            <h2>Kategorija: <?php echo htmlspecialchars($selected_cat); ?></h2>
        </div>

        <?php if (empty($videi)): ?>
            <p style="text-align:center; color:#888; margin: 40px 0;">Trenutno nema video zapisa u ovoj kategoriji.</p>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videi as $v): ?>
                    <div class="video-card">
                        <h3><?php echo htmlspecialchars($v['naslov']); ?></h3>
                        <div class="video-embed">
                            <?php echo $v['embed_kod']; ?>
                        </div>
                        <?php if (isset($_SESSION['admin_logged'])): ?>
                            <a href="index.php?delete=<?php echo $v['id']; ?>" onclick="return confirm('Obriši video?')" style="color:red; font-size:0.8rem; display:block; margin-top:5px;">Obriši</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINACIJA (1, 2, 3...) -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="index.php?cat=<?php echo urlencode($selected_cat); ?>&page=<?php echo $i; ?>" class="<?php echo ($i === $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>

</body>
</html>
