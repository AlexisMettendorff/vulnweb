<?php

$config = require __DIR__ . '/config.php';

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function diagnosticTargets(): array
{
    return [
        'db' => [
            'label' => 'Base de donnees',
            'address' => 'tcp://devsecops-bdd:3306',
            'port' => 3306,
        ],
        'web' => [
            'label' => 'Application web',
            'address' => 'tcp://devsecops-web:80',
            'port' => 80,
        ],
        'adminer' => [
            'label' => 'Adminer',
            'address' => 'tcp://devsecops-adminer:8080',
            'port' => 8080,
        ],
    ];
}

function resolveDiagnosticTarget(string $targetId): ?array
{
    switch ($targetId) {
        case 'db':
            return [
                'label' => 'Base de donnees',
                'address' => 'tcp://devsecops-bdd:3306',
                'port' => 3306,
            ];

        case 'web':
            return [
                'label' => 'Application web',
                'address' => 'tcp://devsecops-web:80',
                'port' => 80,
            ];

        case 'adminer':
            return [
                'label' => 'Adminer',
                'address' => 'tcp://devsecops-adminer:8080',
                'port' => 8080,
            ];

        default:
            return null;
    }
}

$dbConfig = $config['database'];
$diagnosticTargets = diagnosticTargets();
$search = trim($_GET['search'] ?? '');
$targetId = trim($_GET['target'] ?? '');
$results = [];
$databaseError = null;
$searchError = null;
$diagnosticMessage = null;
$diagnosticDetails = null;

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'],
        $dbConfig['name']
    );
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    $databaseError = 'La base de donnees est temporairement indisponible.';
    $pdo = null;
}

if ($search !== '' && $pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare(
            'SELECT username, role FROM users WHERE username = :username'
        );
        $statement->execute(['username' => $search]);
        $results = $statement->fetchAll();
    } catch (PDOException $exception) {
        error_log('Search query failed: ' . $exception->getMessage());
        $searchError = 'La recherche est momentanement indisponible.';
    }
}

if ($targetId !== '') {
    $diagnosticTarget = resolveDiagnosticTarget($targetId);

    if ($diagnosticTarget === null) {
        $diagnosticMessage = 'Cible de diagnostic invalide.';
    } else {
        $startTime = microtime(true);
        $socket = @stream_socket_client(
            $diagnosticTarget['address'],
            $errorCode,
            $errorMessage,
            2,
            STREAM_CLIENT_CONNECT
        );
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        if (is_resource($socket)) {
            fclose($socket);
            $diagnosticMessage = sprintf(
                'Connexion TCP reussie vers %s sur le port %d.',
                $diagnosticTarget['label'],
                $diagnosticTarget['port']
            );
            $diagnosticDetails = sprintf(
                'Temps de reponse approx. : %d ms',
                $durationMs
            );
        } else {
            $diagnosticMessage = sprintf(
                'Connexion TCP impossible vers %s sur le port %d.',
                $diagnosticTarget['label'],
                $diagnosticTarget['port']
            );
            $diagnosticDetails = $errorMessage !== ''
                ? sprintf('Detail technique : %s (%d)', $errorMessage, $errorCode)
                : 'Aucun detail supplementaire disponible.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Annuaire Interne</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
        }

        .panel {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
        }

        .error {
            color: #842029;
        }

        .muted {
            color: #555555;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <h1>Annuaire de l'entreprise</h1>

    <?php if ($databaseError !== null) : ?>
        <p class="error"><?php echo escapeHtml($databaseError); ?></p>
    <?php endif; ?>

    <p>
        Resultats de recherche pour :
        <b><?php echo escapeHtml($search); ?></b>
    </p>

    <form method="GET">
        <input
            type="text"
            name="search"
            placeholder="Rechercher un collegue..."
            value="<?php echo escapeHtml($search); ?>"
        >
        <button type="submit">Rechercher</button>
    </form>

    <hr>

    <?php if ($search !== '' && $databaseError === null) : ?>
        <?php if ($searchError !== null) : ?>
            <p class="error"><?php echo escapeHtml($searchError); ?></p>
        <?php elseif ($results) : ?>
            <ul>
                <?php foreach ($results as $row) : ?>
                    <li>
                        <strong><?php echo escapeHtml($row['username']); ?></strong>
                        <span class="muted">
                            (<?php echo escapeHtml($row['role']); ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>Aucun utilisateur trouve.</p>
        <?php endif; ?>
    <?php endif; ?>

    <hr>

    <div class="panel">
        <h3>Zone Admin : Diagnostic Reseau</h3>
        <p>Verification d'accessibilite TCP sur une cible interne autorisee.</p>

        <form method="GET">
            <input
                type="hidden"
                name="search"
                value="<?php echo escapeHtml($search); ?>"
            >

            <label for="target">Cible :</label>
            <select id="target" name="target">
                <option value="">Selectionner une cible</option>
                <?php foreach ($diagnosticTargets as $targetKey => $targetConfig) : ?>
                    <option
                        value="<?php echo escapeHtml($targetKey); ?>"
                        <?php echo $targetId === $targetKey ? 'selected' : ''; ?>
                    >
                        <?php
                        echo escapeHtml(
                            sprintf(
                                '%s (port %d)',
                                $targetConfig['label'],
                                $targetConfig['port']
                            )
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Tester</button>
        </form>

        <?php if ($diagnosticMessage !== null) : ?>
            <pre><?php echo escapeHtml($diagnosticMessage); ?></pre>
            <?php if ($diagnosticDetails !== null) : ?>
                <p class="muted"><?php echo escapeHtml($diagnosticDetails); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
