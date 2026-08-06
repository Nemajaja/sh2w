<?php
// Povezivanje sa bazom
$db = new PDO('sqlite:videobaza.db');
$db->exec("CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    naslov TEXT, 
    embed_kod TEXT, 
    datum DATETIME DEFAULT CURRENT_TIMESTAMP
)");
?>

<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moja Video Stranica</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #141414; color: #ffffff; margin: 0; padding: 20px; }
        header { text-align: center; padding: 20px; border-bottom: 2px solid #333; }
        h1 { margin: 0; color: #e50914; }
        .container { max-width: 1000px; margin: 20px auto; }
        .ad-banner { background: #222; border: 1px dashed #555; text-align: center; padding: 15px; margin: 20px 0; min-height: 90px; }
        .video-card { background: #1f1f1f; margin-bottom: 30px; border-radius: 8px; overflow: hidden; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        .video-card h2 { margin-top: 0; font-size: 1.2rem; }
        .video-embed { position: relative; padding-bottom: 56.25%; /* 16:9 aspect ratio */ height: 0; overflow: hidden; max-width: 100%; background: #000; }
        .video-embed iframe, .video-embed embed, .video-embed object { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
    </style>
</head>
<body>

<header>
    <h1>Moja Video Stranica</h1>
</header>

<div class="container">

    <!-- MJESTO ZA 18+ REKLAMU (VRH) -->
    <div class="ad-banner">
        <!-- ZALIJEPI KOD ZA REKLAMU (npr. ExoClick ili JuicyAds) OVDJE -->
        <p style="color:#777;">Reklama (ExoClick / JuicyAds)</p>
    </div>

    <!-- PRIKAZ VIDEO ZAPISA -->
    <?php
    $stmt = $db->query("SELECT * FROM videos ORDER BY id DESC");
    $videi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($videi) == 0) {
        echo "<p style='text-align:center;'>Trenutno nema objavljenih video zapisa.</p>";
    } else {
        foreach ($videi as $v) {
            echo '<div class="video-card">';
            echo '<h2>' . htmlspecialchars($v['naslov']) . '</h2>';
            echo '<div class="video-embed">';
            // Prikaz iframe koda direktno iz baze
            echo $v['embed_kod'];
            echo '</div>';
            echo '</div>';
        }
    }
    ?>

    <!-- MJESTO ZA 18+ REKLAMU (DNO) -->
    <div class="ad-banner">
        <!-- ZALIJEPI KOD ZA REKLAMU OVDJE -->
        <p style="color:#777;">Reklama (ExoClick / JuicyAds)</p>
    </div>

</div>

</body>
</html>
