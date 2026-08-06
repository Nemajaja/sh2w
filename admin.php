<?php
session_start();

// POSTAVI SVOJU ŠIFRU OVDJE
$ADMIN_PASSWORD = "MojaSigurnaSifra123!"; 

// Povezivanje sa bazom podataka (SQLite se kreira automatski)
$db = new PDO('sqlite:videobaza.db');
$db->exec("CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    naslov TEXT, 
    embed_kod TEXT, 
    datum DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Odjava
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Prijava
$error = "";
if (isset($_POST['login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "Pogrešna lozinka!";
    }
}

// Dodavanje novog videa
if (isset($_POST['add_video']) && isset($_SESSION['loggedin'])) {
    $naslov = $_POST['naslov'];
    $embed_kod = $_POST['embed_kod'];

    if (!empty($naslov) && !empty($embed_kod)) {
        $stmt = $db->prepare("INSERT INTO videos (naslov, embed_kod) VALUES (:naslov, :embed_kod)");
        $stmt->bindValue(':naslov', $naslov);
        $stmt->bindValue(':embed_kod', $embed_kod);
        $stmt->execute();
        $success = "Video uspješno dodan!";
    } else {
        $error = "Popunite sva polja!";
    }
}

// Brisanje videa
if (isset($_GET['delete']) && isset($_SESSION['loggedin'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM videos WHERE id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    header("Location: admin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Dodaj Video</title>
    <style>
        body { font-family: Arial, sans-serif; background: #121212; color: #fff; padding: 20px; text-align: center; }
        .box { background: #1e1e1e; padding: 20px; max-width: 500px; margin: auto; border-radius: 8px; }
        input, textarea { width: 90%; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #333; background: #222; color: #fff; }
        button { padding: 10px 20px; background: #e50914; color: white; border: none; cursor: pointer; border-radius: 5px; font-weight: bold; }
        button:hover { background: #b20710; }
        a { color: #ff4757; text-decoration: none; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        td, th { padding: 10px; border-bottom: 1px solid #333; }
    </style>
</head>
<body>

<div class="box">
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <h2>Prijava za Admina</h2>
        <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Unesi lozinku" required><br>
            <button type="submit" name="login">Prijavi se</button>
        </form>
    <?php else: ?>
        <h2>Dodaj novi Video (pCloud / TeraBox)</h2>
        <p><a href="admin.php?logout=1">Odjavi se</a> | <a href="index.php" target="_blank">Vidi Stranicu</a></p>
        
        <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
        <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>

        <form method="POST">
            <input type="text" name="naslov" placeholder="Naslov Videa" required><br>
            <textarea name="embed_kod" rows="4" placeholder="Zalijepi pCloud/TeraBox Embed Kod ili Link (iframe)" required></textarea><br>
            <button type="submit" name="add_video">Objavi Video</button>
        </form>

        <h3>Postojeći Videi</h3>
        <table>
            <tr>
                <th>Naslov</th>
                <th>Akcija</th>
            </tr>
            <?php
            $stmt = $db->query("SELECT * FROM videos ORDER BY id DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['naslov']) . "</td>";
                echo "<td><a href='admin.php?delete=" . $row['id'] . "' onclick='return confirm(\"Sigurno?\")'>Obriši</a></td>";
                echo "</tr>";
            }
            ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
