<?php
session_start();

// ==========================================
// CONFIGURATION DU PANEL
// ==========================================
$PANEL_VERSION = "1.0.0";
$REMOTE_VERSION_URL = "https://raw.githubusercontent.com/ambrouw/Launcher-JSON-methode-Minelaunched/main/version.json";

// GESTION AUTOMATIQUE DU MOT DE PASSE
$SECRET_FILE = 'secret_password.php';

// Si le fichier secret existe, on prend le mot de passe dedans
if (file_exists($SECRET_FILE)) {
    $loadedPass = include($SECRET_FILE);
    // Vérifier que le fichier contient bien un mot de passe valide
    if ($loadedPass && is_string($loadedPass) && strlen($loadedPass) > 0) {
        $ADMIN_PASSWORD = $loadedPass;
    } else {
        // Fichier corrompu, on le régénère
        unlink($SECRET_FILE);
        // On continue dans le bloc "else" ci-dessous pour recréer
        $ADMIN_PASSWORD = null;
    }
} else {
    $ADMIN_PASSWORD = null; // Force la génération
}

// Si pas de mot de passe valide, on en génère un
if (empty($ADMIN_PASSWORD)) {
    // Génération d'un mot de passe aléatoire
    try {
        $randomPass = bin2hex(random_bytes(8)); // 16 caractères hex
    } catch (Exception $e) {
        $randomPass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 16);
    }
    
    // On sauvegarde le mot de passe dans un fichier PHP sécurisé
    $content = "<?php return '$randomPass';";
    $writeSuccess = @file_put_contents($SECRET_FILE, $content);
    
    if ($writeSuccess === false) {
        // Erreur : impossible d'écrire le fichier (permissions ?)
        // On utilise quand même le mot de passe généré, mais on alerte l'admin
        $ADMIN_PASSWORD = $randomPass;
        $firstLaunchMsg = "⚠️ <strong>ATTENTION :</strong> Impossible de sauvegarder le mot de passe dans <em>$SECRET_FILE</em> (problème de permissions).<br>Votre mot de passe temporaire est : <strong style='font-size:1.2em;user-select:all;'>$randomPass</strong><br><small>Notez-le bien, il ne sera pas sauvegardé !</small>";
    } else {
        $ADMIN_PASSWORD = $randomPass;
        // Message pour le premier lancement (succès)
        $firstLaunchMsg = "Premier lancement détecté.<br>Votre mot de passe sécurisé est : <strong style='font-size:1.2em;user-select:all;'>$randomPass</strong><br><small>Il a été sauvegardé dans <em>$SECRET_FILE</em>.</small>";
    }
}

$IGNORE_FILES = ['.', '..', 'index.php', ".htaccess", "README.md","error_log", "version.json", $SECRET_FILE];
$IGNORE_DIRS = [];
$DIR_CHECK_USELESS = ["mods", "config"]; // Dossiers surveillés pour suppression client

// ==========================================
// LOGIQUE DU PANEL D'ADMINISTRATION
// ==========================================
if (isset($_GET['panel'])) {
    
    // 1. GESTION DU LOGIN
    if (isset($_POST['login_pass'])) {
        if ($_POST['login_pass'] === $ADMIN_PASSWORD) {
            $_SESSION['is_admin'] = true;
        } else {
            $error = "Mot de passe incorrect.";
        }
    }
    
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: index.php?panel");
        exit;
    }

    // Si pas connecté, afficher le formulaire de login
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Connexion Administration</title>
    <style>
        body{font-family:'Segoe UI',sans-serif;background:#18191a;color:#e4e6eb;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
        .login-box{background:#242526;padding:30px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.5);width:300px;text-align:center;}
        input{width:90%;padding:10px;margin:10px 0;border:1px solid #3e4042;border-radius:5px;background:#3a3b3c;color:#e4e6eb;}
        button{width:100%;padding:10px;background:#007bff;color:white;border:none;border-radius:5px;cursor:pointer;}
        button:hover{background:#0056b3;}
        .error{color:#ff6b6b;font-size:0.9em;}
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Panel Launcher</h2>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
        
        <?php if(isset($firstLaunchMsg)): ?>
            <div style="background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;border:1px solid #c3e6cb;text-align:left;font-size:0.95em;">
                <?= $firstLaunchMsg ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="password" name="login_pass" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>
<?php
        exit; // Arrête le script ici pour ne pas afficher le JSON
    }

    // 2. TRAITEMENT DES ACTIONS (Une fois connecté)
    $msg = "";
    $msgType = "";

    // Upload
    if (isset($_POST['action']) && $_POST['action'] === 'upload' && isset($_FILES['file'])) {
        $targetDirInput = isset($_POST['dir']) ? trim($_POST['dir']) : '';
        // Sécurité : pas de retour en arrière
        $targetDirInput = str_replace(['..', '<', '>'], '', $targetDirInput);
        $targetDirInput = trim($targetDirInput, '/');
        
        // Auto-rename : Remplacer les espaces par des underscores dans le dossier
        $targetDir = str_replace(' ', '_', $targetDirInput);
        $dirRenamed = ($targetDir !== $targetDirInput);

        // Créer le dossier s'il n'existe pas
        if (!empty($targetDir) && !is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $originalFileName = basename($_FILES['file']['name']);
        // Auto-rename : Remplacer les espaces par des underscores dans le fichier
        $fileName = str_replace(' ', '_', $originalFileName);
        $fileRenamed = ($fileName !== $originalFileName);

        // Bloquer l'upload de fichiers PHP pour la sécurité
        if (pathinfo($fileName, PATHINFO_EXTENSION) === 'php') {
            $msg = "Upload de fichiers PHP interdit par sécurité.";
            $msgType = "error";
        } else {
            $targetPath = empty($targetDir) ? $fileName : $targetDir . '/' . $fileName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                $msg = "Fichier envoyé : $targetPath";
                if ($fileRenamed || $dirRenamed) {
                    $msg .= " <br><strong>Note :</strong> Les espaces ont été remplacés par des underscores (_).";
                }
                $msgType = "success";
            } else {
                $msg = "Erreur lors de l'envoi.";
                $msgType = "error";
            }
        }
    }

    // Suppression
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['file'])) {
        $fileToDelete = $_POST['file'];
        // Sécurité de base
        if (!in_array($fileToDelete, $IGNORE_FILES) && file_exists($fileToDelete) && strpos($fileToDelete, 'index.php') === false) {
            unlink($fileToDelete);
            $msg = "Fichier supprimé : $fileToDelete";
            $msgType = "success";
        } else {
            $msg = "Impossible de supprimer ce fichier.";
            $msgType = "error";
        }
    }

    // Renommer
    if (isset($_POST['action']) && $_POST['action'] === 'rename' && isset($_POST['file']) && isset($_POST['new_name'])) {
        $oldPath = $_POST['file'];
        $newName = trim($_POST['new_name']);
        
        // Sécurité
        $newName = str_replace(['/', '\\', '..'], '', $newName); // Pas de déplacement de dossier
        $dir = dirname($oldPath);
        $newPath = $dir . '/' . $newName;

        // Vérifications
        if (!file_exists($oldPath)) {
            $msg = "Le fichier original n'existe pas.";
            $msgType = "error";
        } elseif (file_exists($newPath)) {
            $msg = "Un fichier avec ce nom existe déjà.";
            $msgType = "error";
        } elseif (pathinfo($newName, PATHINFO_EXTENSION) === 'php') {
            $msg = "Interdit de renommer en .php !";
            $msgType = "error";
        } else {
            if (rename($oldPath, $newPath)) {
                $msg = "Fichier renommé avec succès.";
                $msgType = "success";
            } else {
                $msg = "Erreur lors du renommage.";
                $msgType = "error";
            }
        }
    }

    // Dézipper
    if (isset($_POST['action']) && $_POST['action'] === 'unzip' && isset($_POST['file'])) {
        $zipFile = $_POST['file'];
        
        if (file_exists($zipFile) && strtolower(pathinfo($zipFile, PATHINFO_EXTENSION)) === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                // On extrait dans le même dossier que le zip
                $extractPath = dirname($zipFile);
                $zip->extractTo($extractPath);
                $zip->close();
                $msg = "Archive décompressée avec succès dans : $extractPath";
                $msgType = "success";
            } else {
                $msg = "Erreur : Impossible d'ouvrir l'archive ZIP.";
                $msgType = "error";
            }
        } else {
            $msg = "Fichier invalide ou extension non supportée.";
            $msgType = "error";
        }
    }

    // Récupérer la liste complète pour la vérification JSON (récursif) et le Launcher
    $allFilesForCheck = ScanDirectory('.', $DIR_CHECK_USELESS, false, $IGNORE_FILES, $IGNORE_DIRS);

    // Vérification préventive des espaces (Liste précise)
    $filesWithSpaces = [];
    if (is_array($allFilesForCheck)) {
        foreach($allFilesForCheck as $f) {
            if (isset($f['path']) && strpos($f['path'], ' ') !== false) {
                $filesWithSpaces[] = $f['path'];
            }
        }
    }

    // LOGIQUE D'EXPLORATEUR DE FICHIERS (Pour l'affichage)
    $currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';
    // Sécurité anti-traversal
    $currentDir = str_replace(['..', '<', '>'], '', $currentDir);
    $currentDir = trim($currentDir, '/');
    
    // Définition du chemin réel à scanner
    $scanPath = empty($currentDir) ? '.' : $currentDir;
    
    // Vérifier que le dossier existe, sinon retour à la racine
    if (!is_dir($scanPath)) {
        $currentDir = '';
        $scanPath = '.';
    }

    // Récupération des dossiers et fichiers du répertoire actuel
    $items = scandir($scanPath);
    $dirs = [];
    $filesView = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $IGNORE_FILES)) continue;
        
        $fullPath = empty($currentDir) ? $item : $currentDir . '/' . $item;
        
        if (is_dir($fullPath)) {
            // Vérifier si dossier ignoré
            if (!in_array($item, $IGNORE_DIRS) && !in_array($fullPath, $IGNORE_DIRS)) {
                $dirs[] = [
                    'name' => $item,
                    'path' => $fullPath
                ];
            }
        } else {
            $filesView[] = [
                'name' => $item,
                'path' => $fullPath,
                'sha1' => sha1_file($fullPath),
                'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . str_replace("index.php", "", strtok($_SERVER['REQUEST_URI'], '?')) . $fullPath
            ];
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Gestion Fichiers</title>
    <style>
        body{font-family:'Segoe UI',sans-serif;background:#18191a;color:#e4e6eb;margin:0;padding:20px;}
        .container{max-width:1200px;margin:0 auto;}
        .card{background:#242526;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.3);padding:20px;margin-bottom:20px;}
        h1{margin-top:0;color:#e4e6eb;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
        table{width:100%;border-collapse:collapse;margin-top:10px;}
        th,td{padding:12px;text-align:left;border-bottom:1px solid #3e4042;color:#e4e6eb;}
        th{background:#3a3b3c;}
        tr:hover{background:#3a3b3c;}
        .btn{padding:8px 15px;border:none;border-radius:4px;cursor:pointer;color:white;text-decoration:none;font-size:14px;}
        .btn-red{background:#dc3545;}
        .btn-blue{background:#007bff;}
        .msg{padding:10px;border-radius:4px;margin-bottom:15px;}
        .msg.success{background:#1e4620;color:#d4edda;}
        .msg.error{background:#5c1a21;color:#f8d7da;}
        .form-inline{display:flex;gap:10px;align-items:center;}
        input[type="text"]{padding:8px;border:1px solid #3e4042;border-radius:4px;background:#3a3b3c;color:#e4e6eb;}
        code{background:#3a3b3c;padding:2px 5px;border-radius:3px;font-family:monospace;color:#e4e6eb;}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Panel de Gestion</h1>
            <form method="post"><input type="hidden" name="logout" value="1"><button class="btn btn-red">Déconnexion</button></form>
        </div>

        <?php if($msg): ?><div class="msg <?= $msgType ?>"><?= $msg ?></div><?php endif; ?>
        
        <?php if(!empty($filesWithSpaces)): ?>
            <div class="msg error">
                <strong>⚠️ ATTENTION :</strong> Les fichiers suivants contiennent des espaces et feront planter le launcher :<br>
                <ul style="margin:5px 0 0 0;padding-left:20px;">
                    <?php foreach($filesWithSpaces as $badFile): ?>
                        <li><?= htmlspecialchars($badFile) ?></li>
                    <?php endforeach; ?>
                </ul>
                Veuillez les renommer ou les supprimer.
            </div>
        <?php endif; ?>

        <!-- Zone d'Upload -->
        <div class="card">
            <h3>Uploader un fichier dans : <code><?= empty($currentDir) ? 'Racine' : htmlspecialchars($currentDir) ?>/</code></h3>
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <div style="flex:1;">
                        <label style="display:block;margin-bottom:5px;color:#aaa;font-size:0.9em;">Dossier de destination</label>
                        <input type="text" name="dir" value="<?= htmlspecialchars($currentDir) ?>" placeholder="<?= empty($currentDir) ? 'Racine (laisser vide)' : 'Dossier actuel' ?>" style="width:100%;box-sizing:border-box;">
                    </div>
                </div>

                <!-- Zone de Drag & Drop -->
                <div id="dropZone" style="border:2px dashed #3e4042;border-radius:8px;padding:40px 20px;text-align:center;cursor:pointer;transition:all 0.3s;background:#2a2b2c;position:relative;">
                    <input type="file" name="file" id="fileInput" required style="position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;cursor:pointer;">
                    <div id="dropText" style="pointer-events:none;">
                        <div style="font-size:40px;margin-bottom:10px;">📂</div>
                        <div style="font-size:1.1em;margin-bottom:5px;">Glissez-déposez votre fichier ici</div>
                        <div style="color:#888;font-size:0.9em;">ou cliquez pour parcourir</div>
                    </div>
                    <div id="fileInfo" style="display:none;pointer-events:none;">
                        <div style="font-size:40px;margin-bottom:10px;">📄</div>
                        <div id="fileName" style="font-weight:bold;font-size:1.1em;margin-bottom:5px;">nom_du_fichier.jar</div>
                        <div style="color:#4caf50;font-size:0.9em;">Prêt à être envoyé</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-blue" style="margin-top:15px;width:100%;padding:12px;font-size:1.1em;">Envoyer le fichier 🚀</button>
            </form>
        </div>

        <!-- Liste des fichiers -->
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h3 style="margin:0;">
                    Explorer : 
                    <a href="index.php?panel" style="text-decoration:none;color:#007bff;">Racine</a> 
                    <?php if(!empty($currentDir)): ?>
                        / <?= htmlspecialchars($currentDir) ?>
                    <?php endif; ?>
                </h3>
                <input type="text" id="searchInput" placeholder="Filtrer la vue..." style="width:200px;">
            </div>
            <div style="max-height:600px;overflow-y:auto;border:1px solid #3e4042;border-radius:4px;">
                <table id="filesTable">
                    <thead>
                        <tr>
                            <th style="position:sticky;top:0;z-index:1;box-shadow:0 2px 2px -1px rgba(0,0,0,0.4);">Nom / Chemin</th>
                            <th style="position:sticky;top:0;z-index:1;box-shadow:0 2px 2px -1px rgba(0,0,0,0.4);">Info</th>
                            <th style="position:sticky;top:0;z-index:1;box-shadow:0 2px 2px -1px rgba(0,0,0,0.4);">Action</th>
                        </tr>
                    </thead>
                <tbody>
                    <!-- Bouton Retour -->
                    <?php if(!empty($currentDir)): 
                        $parentDir = dirname($currentDir);
                        $parentDir = ($parentDir === '.') ? '' : $parentDir;
                    ?>
                    <tr style="background:#2a2b2c;">
                        <td colspan="3">
                            <a href="index.php?panel&dir=<?= urlencode($parentDir) ?>" style="text-decoration:none;color:#e4e6eb;display:block;">
                                📁 <strong>.. (Retour)</strong>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <!-- Dossiers -->
                    <?php foreach($dirs as $d): ?>
                    <tr>
                        <td>
                            <a href="index.php?panel&dir=<?= urlencode($d['path']) ?>" style="text-decoration:none;color:#61dafb;font-weight:bold;">
                                📁 <?= htmlspecialchars($d['name']) ?>
                            </a>
                        </td>
                        <td><span style="color:#aaa;">Dossier</span></td>
                        <td>-</td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Fichiers -->
                    <?php foreach($filesView as $f): ?>
                    <tr>
                        <td>
                            <span style="color:#e4e6eb;">📄 <?= htmlspecialchars($f['name']) ?></span>
                            <?php if(strpos($f['name'], ' ') !== false): ?>
                                <span title="Contient des espaces !" style="margin-left:8px; cursor:help;">⚠️</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-size:0.85em;color:#bbb;"><?= $f['sha1'] ?></span>
                        </td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <!-- Bouton Dézipper (Si c'est un zip) -->
                                <?php if(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'zip'): ?>
                                <form method="post" onsubmit="return confirm('Décompresser l\'archive <?= $f['name'] ?> ici ?');" style="display:inline;">
                                    <input type="hidden" name="action" value="unzip">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($f['path']) ?>">
                                    <button type="submit" class="btn btn-blue" style="padding:4px 8px;font-size:12px;background:#ffc107;color:#000;" title="Dézipper">📦</button>
                                </form>
                                <?php endif; ?>

                                <a href="<?= $f['url'] ?>" download class="btn btn-blue" style="padding:4px 8px;font-size:12px;" title="Télécharger">⬇️</a>
                                <button type="button" class="btn btn-blue" style="padding:4px 8px;font-size:12px;background:#17a2b8;" onclick="renameFile('<?= htmlspecialchars($f['path']) ?>', '<?= htmlspecialchars($f['name']) ?>')" title="Renommer">✏️</button>
                                <form method="post" onsubmit="return confirm('Supprimer <?= $f['name'] ?> ?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($f['path']) ?>">
                                    <button type="submit" class="btn btn-red" style="padding:4px 8px;font-size:12px;" title="Supprimer">❌</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if(empty($dirs) && empty($filesView)): ?>
                        <tr><td colspan="3" style="text-align:center;padding:20px;">Dossier vide.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Bouton Historique (en bas à gauche) -->
    <div style="position:fixed;bottom:20px;left:20px;z-index:999;">
        <button onclick="showHistory()" style="background:#3a3b3c;color:#e4e6eb;border:1px solid #555;padding:8px 12px;border-radius:20px;cursor:pointer;font-size:0.9em;opacity:0.8;transition:opacity 0.3s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.8">📜 Historique</button>
    </div>

    <!-- Popup Historique -->
    <div id="historyPopup" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#242526;padding:20px;border-radius:8px;box-shadow:0 0 20px rgba(0,0,0,0.7);z-index:10000;width:400px;max-height:80vh;overflow-y:auto;color:#e4e6eb;border:1px solid #3e4042;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #3e4042;padding-bottom:10px;">
            <h3 style="margin:0;color:#61dafb;">Historique des versions</h3>
            <button onclick="document.getElementById('historyPopup').style.display='none'" style="background:none;border:none;color:#aaa;cursor:pointer;font-size:24px;">&times;</button>
        </div>
        <div id="historyContent" style="font-size:0.9em;">Chargement...</div>
    </div>

    <script>
        // Script de recherche simple
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#filesTable tbody tr');
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Script Historique
        function showHistory() {
            document.getElementById('historyPopup').style.display = 'block';
            const content = document.getElementById('historyContent');
            
            fetch('https://api.github.com/repos/ambrouw/Launcher-JSON-methode-Minelaunched-/commits')
                .then(response => response.json())
                .then(data => {
                    let html = '<ul style="padding-left:20px;list-style:none;margin:0;">';
                    data.slice(0, 10).forEach(commit => {
                        let date = new Date(commit.commit.author.date).toLocaleDateString('fr-FR');
                        let msg = commit.commit.message;
                        html += `<li style="margin-bottom:15px;border-left:2px solid #555;padding-left:10px;">
                            <div style="color:#888;font-size:0.85em;margin-bottom:2px;">${date}</div>
                            <div style="color:#e4e6eb;">${msg}</div>
                        </li>`;
                    });
                    html += '</ul>';
                    content.innerHTML = html;
                })
                .catch(err => {
                    content.innerHTML = '<p style="color:#ff6b6b;">Impossible de charger l\'historique (Rate limit ou erreur réseau).</p>';
                });
        }

        // Script Drag & Drop
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const dropText = document.getElementById('dropText');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        // Effets visuels au survol
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#007bff';
                dropZone.style.background = '#2f3542';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#3e4042';
                dropZone.style.background = '#2a2b2c';
            }, false);
        });

        // Gestion du fichier sélectionné
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                showFile(this.files[0].name);
            }
        });

        function showFile(name) {
            dropText.style.display = 'none';
            fileInfo.style.display = 'block';
            fileName.textContent = name;
            dropZone.style.borderStyle = 'solid';
            dropZone.style.borderColor = '#4caf50';
        }

        function renameFile(filePath, currentName) {
            let newName = prompt("Nouveau nom pour " + currentName + " :", currentName);
            if (newName && newName !== currentName) {
                // Création d'un formulaire invisible pour envoyer la requête POST
                let form = document.createElement('form');
                form.method = 'POST';
                
                let inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'rename';
                
                let inputFile = document.createElement('input');
                inputFile.type = 'hidden';
                inputFile.name = 'file';
                inputFile.value = filePath;
                
                let inputNewName = document.createElement('input');
                inputNewName.type = 'hidden';
                inputNewName.name = 'new_name';
                inputNewName.value = newName;
                
                form.appendChild(inputAction);
                form.appendChild(inputFile);
                form.appendChild(inputNewName);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <?php
    // LOGIQUE DE MISE A JOUR
    // On vérifie la maj silencieusement
    $updateAvailable = false;
    $versionUnknown = false;
    $remoteData = null;
    
    // On utilise @ pour éviter les erreurs si pas d'internet
    $jsonContent = @file_get_contents($REMOTE_VERSION_URL, false, stream_context_create(['http' => ['timeout' => 2]]));
    
    if ($jsonContent) {
        $remoteData = json_decode($jsonContent, true);
        if ($remoteData && isset($remoteData['version'])) {
            if (version_compare($remoteData['version'], $PANEL_VERSION, '>')) {
                $updateAvailable = true;
            } elseif (version_compare($PANEL_VERSION, $remoteData['version'], '>')) {
                $versionUnknown = true;
            }
        }
    }

    if($updateAvailable): 
    ?>
    <div id="updatePopup" style="position:fixed;top:20px;right:20px;background:#242526;border-left:5px solid #007bff;padding:15px;border-radius:4px;box-shadow:0 5px 15px rgba(0,0,0,0.5);z-index:9999;color:#e4e6eb;animation:slideIn 0.5s ease-out;max-width:300px;">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:5px;">
            <strong style="font-size:1.1em;color:#61dafb;">🚀 Mise à jour disponible !</strong>
            <button onclick="document.getElementById('updatePopup').remove()" style="background:none;border:none;color:#888;cursor:pointer;font-size:16px;">&times;</button>
        </div>
        <p style="margin:5px 0;font-size:0.9em;">La version <strong><?= htmlspecialchars($remoteData['version']) ?></strong> est disponible.</p>
        <a href="<?= htmlspecialchars($remoteData['url']) ?>" target="_blank" style="display:block;text-align:center;background:#007bff;color:white;text-decoration:none;padding:8px;border-radius:4px;margin-top:10px;font-size:0.9em;">Télécharger sur GitHub</a>
    </div>
    <?php endif; ?>

    <?php if($versionUnknown): ?>
    <div style="position:fixed;top:20px;right:20px;background:#242526;border-left:5px solid #ffc107;padding:15px;border-radius:4px;box-shadow:0 5px 15px rgba(0,0,0,0.5);z-index:9999;color:#e4e6eb;animation:slideIn 0.5s ease-out;max-width:300px;">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:5px;">
            <strong style="font-size:1.1em;color:#ffc107;">⚠️ Version non officielle</strong>
            <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;color:#888;cursor:pointer;font-size:16px;">&times;</button>
        </div>
        <p style="margin:5px 0;font-size:0.9em;">Votre version (<?= $PANEL_VERSION ?>) semble plus récente que l'officielle (<?= $remoteData['version'] ?>). Attention aux bugs !</p>
    </div>
    <?php endif; ?>

    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</body>
</html>
<?php
    exit; // IMPORTANT : Ne pas exécuter le reste du script (JSON)
}

// ==========================================
// LOGIQUE JSON ORIGINALE (POUR LE LAUNCHER)
// ==========================================

function ScanDirectory($Directory, $dirCheckUselessFiles = array(), $tableau=false, $ignoreEntry = [], $ignoreDirectory = array()){
    // On utilise les globales définies en haut si les paramètres sont vides par défaut
    global $IGNORE_FILES, $IGNORE_DIRS;
    if(empty($ignoreEntry)) $ignoreEntry = $IGNORE_FILES;
    if(empty($ignoreDirectory)) $ignoreDirectory = $IGNORE_DIRS;

    $slash = '';
    $MyDirectory = opendir($Directory) or die('Erreur');
    
    while($Entry = @readdir($MyDirectory)){
        if(!equal($Entry,$ignoreEntry)){
            if(is_dir($Directory.'/'.$Entry)){
                $slash = '/';           
            } else {
                $slash = '';
            }
            
            // Calcul du chemin relatif propre
            $fullPath = $Directory.'/'.$Entry;
            // Astuce pour récupérer le chemin relatif sans le './' du début si présent
            if(substr($fullPath, 0, 2) == './') $elem = substr($fullPath, 2) . $slash;
            else $elem = $fullPath . $slash;

            // Construction de l'URL absolue
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $host = $_SERVER['HTTP_HOST'];
            // On nettoie l'URI pour enlever index.php et les paramètres GET
            $scriptPath = str_replace("index.php", "", strtok($_SERVER['REQUEST_URI'], '?'));
            $url = $protocol . "://" . $host . $scriptPath . $elem;

            $tableau[] = array(
                "path"=>$elem,
                "checksumSHA1"=>is_dir($Directory.'/'.$Entry) ? false : sha1_file($Directory.'/'.$Entry),
                "url"=>$url
            );

            if(!contain($Directory,$ignoreDirectory) && is_dir($Directory.'/'.$Entry)){
                $tableau = ScanDirectory($Directory.'/'.$Entry, array(), $tableau, $ignoreEntry, $ignoreDirectory);
            }
        }
    }
    
    // Ajout des instructions de nettoyage à la fin (seulement au premier appel)
    if(!empty($dirCheckUselessFiles) && is_array($dirCheckUselessFiles)){
        foreach($dirCheckUselessFiles as $v){
            $tableau[] = array(
                "dirCheckUselessFiles"=>$v
            );
        }
    }
    
    closedir($MyDirectory);
    return $tableau;
}

function contain($file,$array){
    foreach ($array as $value) {
        if(strpos($file,$value) !== false){
            return true;
        }
    }
    return false;
}

function equal($file,$array){
    foreach ($array as $value) {
        if($file == $value){
            return true;
        }
    }
    return false;
}

function contains($files,$array){
    foreach ($files as $value) {
        if(contain($value,$array)){
            return true;
        }
    }
    return false;
}

// Exécution du scan JSON
$tableau = ScanDirectory('.', $DIR_CHECK_USELESS, false, $IGNORE_FILES, $IGNORE_DIRS);
$tableau = $tableau == false ? [] : $tableau;

header('Content-Type: application/json'); // Indique au client que c'est du JSON
echo json_encode($tableau);
?>