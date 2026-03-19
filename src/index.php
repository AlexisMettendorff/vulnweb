<?php
$host = 'devsecops-bdd';
$db   = 'myapp';
$user = 'appuser';
$pass = 'apppassword';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
die("Erreur de connexion BDD : " . $e->getMessage()); // ERREUR: Mauvaise indentation (collé à gauche)
}

$search = $_GET['search'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Annuaire Interne</title>
    <style>body { font-family: sans-serif; padding: 20px; }</style>
</head>
<body>
    <h1>Annuaire de l'entreprise</h1>

    <p>Résultats de recherche pour : <b><?php echo $search; ?></b></p>

    <form method="GET">
        <input type="text" name="search" placeholder="Rechercher un collègue..." value="<?php echo $search; ?>">
        <button type="submit">Rechercher</button>
    </form>
    <hr>

    <?php
    if($search) 
    { 
        $sql="SELECT username, role, password FROM users WHERE username = '$search'"; 
        echo "<div style='color:gray; font-size:0.8em'>DEBUG SQL: " . $sql . "</div><br>";

        try {
            $stmt = $pdo->query($sql);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($results) {
                echo "<ul>";
                foreach ($results as $row) {
                    echo "<li>";
                    echo "<strong>" . $row['username'] . "</strong> ";
                    echo "</li>";
                }
                echo "</ul>";
            }
            else { 
                echo "Aucun utilisateur trouvé.";
            }
        } catch (PDOException $e) {
            echo "Erreur SQL : " . $e->getMessage();
        }
    }
    ?>
	
	    <hr>
    <div style="background-color: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;">
        <h3>Zone Admin : Diagnostic Réseau</h3>
        <p>Vérifier la connectivité d'un serveur interne.</p>
        
        <form method="GET">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            
            <label>IP à tester :</label>
            <input type="text" name="ip" placeholder="ex: 8.8.8.8" value="<?php echo $_GET['ip'] ?? ''; ?>">
            <button type="submit">Pinger</button>
        </form>

        <?php
        if (isset($_GET['ip']) && !empty($_GET['ip'])) {
            $ip = $_GET['ip'];
            
            echo "<pre>";
            echo "Test de ping sur : " . $ip . "\n";
            echo "--------------------------\n";
            
            system("ping -c 2 " . $ip); 
            
            echo "</pre>";
        }
        ?>
    </div>
</body>
</html>
