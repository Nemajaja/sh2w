<?php
// Session expires when browser is closed
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Database path
$dbDir = file_exists('/data') ? '/data' : __DIR__;
$dbPath = $dbDir . '/videobaza.db';

$db = new PDO("sqlite:$dbPath");
$db->exec("CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    naslov TEXT, 
    embed_kod TEXT, 
    kategorija TEXT,
    datum DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Admin credentials
$ADMIN_USER = "luka";
$ADMIN_PASS = "Lu7172775775!";

$login_error = "";
if (isset($_POST['admin_login'])) {
    if ($_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
        $_SESSION['admin_logged'] = true;
    } else {
        $login_error = "Invalid username or password!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Funkcija za automatsko pretvaranje TeraBox linka ili pCloud koda u Embed iframe
function convertToEmbed($input) {
    $input = trim($input);
    
    // Ako je već unesen <iframe> kod (npr. pCloud)
    if (strpos($input, '<iframe') !== false) {
        return $input;
    }

    // Ekstrakcija TeraBox ključa iz linka (npr. /s/1ABCxyz... ili ?surl=1ABCxyz...)
    $surl = '';
    if (preg_match('/\/s\/1([a-zA-Z0-9_-]+)/', $input, $matches)) {
        $surl = $matches[1];
    } elseif (preg_match('/surl=1?([a-zA-Z0-9_-]+)/', $input, $matches)) {
        $surl = $matches[1];
    }

    if (!empty($surl)) {
        return '<iframe src="https://www.terabox.com/sharing/embed?surl=' . htmlspecialchars($surl) . '" width="100%" height="100%" frameborder="0" allowfullscreen scrolling="no"></iframe>';
    }

    // Ako nije prepoznat TeraBox format, stavi link u osnovni iframe
    return '<iframe src="' . htmlspecialchars($input) . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>';
}

// Add New Video (Admin only)
if (isset($_POST['add_video']) && isset($_SESSION['admin_logged'])) {
    $naslov = $_POST['naslov'];
    $raw_input = $_POST['embed_kod'];
    $kat = $_POST['kategorija'];

    if (!empty($naslov) && !empty($raw_input) && !empty($kat)) {
        $embed_kod = convertToEmbed($raw_input);
        
        $stmt = $db->prepare("INSERT INTO videos (naslov, embed_kod, kategorija) VALUES (:naslov, :embed_kod, :kat)");
        $stmt->bindValue(':naslov', $naslov);
        $stmt->bindValue(':embed_kod', $embed_kod);
        $stmt->bindValue(':kat', $kat);
        $stmt->execute();
        header("Location: index.php?cat=" . urlencode($kat));
        exit();
    }
}

// Delete Video
if (isset($_GET['delete']) && isset($_SESSION['admin_logged'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM videos WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

// Category & Pagination Settings
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

$limit = 52;
$offset = ($page - 1) * $limit;

// Fetch Videos
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Portal</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #121212; color: #fff; margin: 0; padding: 0; }
        
        header { background: #1f1f1f; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; }
        .logo { font-size: 1.5rem; font-weight: bold; color: #e50914; text-decoration: none; }
        .admin-btn { background: #e50914; color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-weight: bold; text-decoration: none; }
        .admin-btn:hover { background: #b20710; }

        .container { max-width: 1400px; margin: 20px auto; padding: 0 15px; }

        .admin-panel { background: #1e1e1e; border: 1px solid #e50914; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .admin-panel input, .admin-panel select, .admin-panel textarea { width: 100%; padding: 10px; margin: 8px 0; background: #2a2a2a; color: white; border: 1px solid #444; border-radius: 5px; }
        
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 20px; }
        .cat-card { background: #222; color: white; border: 1px solid #333; padding: 15px; text-align: center; border-radius: 8px; text-decoration: none; font-weight: bold; transition: 0.2s; }
        .cat-card:hover { background: #e50914; border-color: #e50914; }

        .cat-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .back-btn { background: #333; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; }
        .back-btn:hover { background: #555; }

        .video-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        @media (max-width: 768px) {
            .video-grid { grid-template-columns: 1fr; }
        }

        .video-card { background: #1a1a1a; border-radius: 6px; padding: 10px; border: 1px solid #2a2a2a; }
        .video-card h3 { font-size: 0.95rem; margin: 0 0 8px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .video-embed { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000; border-radius: 4px; }
        .video-embed iframe { position: absolute; top:0; left:0; width:100%; height:100%; border:0; }

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
            <a href="index.php?logout=1" class="admin-btn">Logout</a>
        </div>
    <?php else: ?>
        <button onclick="document.getElementById('loginModal').style.display='block'" class="admin-btn">Admin Login</button>
    <?php endif; ?>
</header>

<div class="container">

    <!-- LOGIN MODAL -->
    <div id="loginModal" class="admin-panel" style="display: <?php echo $login_error ? 'block' : 'none'; ?>;">
        <h3>Admin Login</h3>
        <?php if ($login_error) echo "<p style='color:red;'>$login_error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="admin_login" class="admin-btn" style="width:100%; margin-top:5px;">Login</button>
        </form>
    </div>

    <!-- ADD VIDEO PANEL -->
    <?php if (isset($_SESSION['admin_logged'])): ?>
        <div class="admin-panel">
            <h3>Add New Video</h3>
            <form method="POST">
                <input type="text" name="naslov" placeholder="Video Title" required>
                <textarea name="embed_kod" rows="3" placeholder="Paste TeraBox Link (Copy Link) or pCloud Embed Code here" required></textarea>
                <select name="kategorija" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_video" class="admin-btn" style="width:100%; margin-top:10px;">Publish Video</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- CATEGORIES PAGE -->
    <?php if ($selected_cat === ''): ?>
        <h2>Select Category</h2>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat=<?php echo urlencode($cat); ?>" class="cat-card">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

    <!-- VIDEOS LIST PAGE -->
    <?php else: ?>
        <div class="cat-header">
            <a href="index.php" class="back-btn">&#8592; Back</a>
            <h2>Category: <?php echo htmlspecialchars($selected_cat); ?></h2>
        </div>

        <?php if (empty($videi)): ?>
            <p style="text-align:center; color:#888; margin: 40px 0;">No videos currently available in this category.</p>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videi as $v): ?>
                    <div class="video-card">
                        <h3><?php echo htmlspecialchars($v['naslov']); ?></h3>
                        <div class="video-embed">
                            <?php echo $v['embed_kod']; ?>
                        </div>
                        <?php if (isset($_SESSION['admin_logged'])): ?>
                            <a href="index.php?delete=<?php echo $v['id']; ?>" onclick="return confirm('Delete video?')" style="color:red; font-size:0.8rem; display:block; margin-top:5px;">Delete</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
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
