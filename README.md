# 🚀 Panel Launcher - Gestionnaire de Fichiers JSON

Un gestionnaire de fichiers PHP complet pour serveurs de Launcher Minecraft, avec interface d'administration moderne et API JSON automatique.

## ✨ Fonctionnalités

### 🎯 Pour le Launcher
- **API JSON automatique** : Génère automatiquement un JSON avec tous les fichiers, leurs chemins, SHA1 et URLs
- **Scan récursif** : Parcourt tous les dossiers et sous-dossiers
- **Gestion des dossiers de nettoyage** : Support pour la suppression automatique de dossiers côté client

### 🛠️ Panel d'Administration
- **Interface moderne en mode nuit** : Design sombre et élégant
- **Explorateur de fichiers** : Navigation intuitive dans les dossiers
- **Upload par Drag & Drop** : Glissez-déposez vos fichiers directement
- **Gestion complète** :
  - 📤 Upload de fichiers
  - 📥 Téléchargement
  - ✏️ Renommage
  - ❌ Suppression
  - 📦 Décompression de fichiers ZIP
- **Recherche instantanée** : Filtrez les fichiers en temps réel
- **Détection automatique** : Alerte si des fichiers contiennent des espaces (problème pour le launcher)
- **Correction automatique** : Remplace les espaces par des underscores lors de l'upload

### 🔒 Sécurité
- **Mot de passe auto-généré** : Un mot de passe sécurisé est créé automatiquement au premier lancement
- **Protection anti-PHP** : Bloque l'upload et le renommage de fichiers PHP
- **Protection anti-traversal** : Empêche l'accès aux dossiers système
- **Fichier secret** : Le mot de passe est stocké dans `secret_password.php` (non inclus dans les mises à jour)

### 🔄 Mise à jour automatique
- **Vérification de version** : Détecte automatiquement les nouvelles versions disponibles
- **Notification visuelle** : Popup élégant en haut à droite
- **Historique des versions** : Consultez l'historique des modifications directement depuis le panel

## 📦 Installation

### Méthode 1 : Installation simple (Recommandée)

1. **Téléchargez** le fichier `index.php` depuis ce dépôt
2. **Placez-le** dans le dossier racine de votre serveur web (ex: `htdocs/`, `www/`, etc.)
3. **Accédez** à `http://votre-serveur.com/index.php?panel`
4. **Notez le mot de passe** qui s'affiche (il est généré automatiquement et sauvegardé dans `secret_password.php`)

C'est tout ! Le script est prêt à l'emploi.

### Méthode 2 : Via Git

```bash
git clone https://github.com/ambrouw/Launcher-JSON-methode-Minelaunched-.git
cd Launcher-JSON-methode-Minelaunched-
# Placez index.php dans votre dossier web
```

## 🎮 Utilisation

### Pour le Launcher (API JSON)

Le script génère automatiquement un JSON accessible via :
```
http://votre-serveur.com/index.php
```

**Format de réponse :**
```json
[
  {
    "path": "mods/mod.jar",
    "checksumSHA1": "da39a3ee5e6b4b0d3255bfef95601890afd80709",
    "url": "http://votre-serveur.com/mods/mod.jar"
  },
  {
    "dirCheckUselessFiles": "mods"
  }
]
```

### Pour l'Administration

1. Accédez à `http://votre-serveur.com/index.php?panel`
2. Connectez-vous avec le mot de passe affiché au premier lancement
3. Utilisez l'interface pour gérer vos fichiers

## ⚙️ Configuration

### Fichiers ignorés

Par défaut, ces fichiers sont ignorés dans le scan :
- `index.php`
- `.htaccess`
- `error_log`
- `version.json`
- `secret_password.php`

Pour modifier la liste, éditez la variable `$IGNORE_FILES` dans `index.php`.

### Dossiers de nettoyage

Les dossiers spécifiés dans `$DIR_CHECK_USELESS` seront marqués pour suppression automatique côté client :
```php
$DIR_CHECK_USELESS = ["mods", "config"];
```

## 🔄 Mise à jour

### Pour les utilisateurs

1. **Téléchargez** la nouvelle version de `index.php` depuis GitHub
2. **Remplacez** votre ancien `index.php` par le nouveau
3. **C'est tout !** Votre mot de passe et vos fichiers restent intacts

Le système détectera automatiquement les nouvelles versions et vous notifiera.

### Pour les développeurs

1. Modifiez `$PANEL_VERSION` dans `index.php`
2. Mettez à jour `version.json` sur GitHub avec la nouvelle version
3. Push sur GitHub

## 🛡️ Sécurité

### Recommandations importantes

1. **Changez le mot de passe** : Le mot de passe auto-généré est sécurisé, mais vous pouvez le modifier en éditant `secret_password.php`
2. **Protégez `secret_password.php`** : Assurez-vous que ce fichier n'est pas accessible publiquement (il est déjà dans `$IGNORE_FILES`)
3. **HTTPS recommandé** : Utilisez HTTPS en production pour protéger les mots de passe
4. **Permissions** : Vérifiez que les permissions des fichiers sont correctes (644 pour les fichiers, 755 pour les dossiers)

## 📝 Structure des fichiers

```
votre-dossier/
├── index.php              # Script principal (à télécharger)
├── secret_password.php     # Mot de passe (généré automatiquement)
├── mods/                  # Vos mods
├── config/                # Vos configs
└── ...                    # Autres dossiers
```

## 🐛 Dépannage

### Le panel ne s'affiche pas
- Vérifiez que PHP est installé et activé
- Vérifiez les permissions du dossier
- Consultez les logs d'erreur PHP

### Impossible de se connecter
- Vérifiez que `secret_password.php` existe et contient un mot de passe
- Si le fichier est corrompu, supprimez-le et rechargez la page (un nouveau sera généré)

### Les fichiers ne s'uploadent pas
- Vérifiez les permissions d'écriture du dossier
- Vérifiez la taille maximale d'upload PHP (`upload_max_filesize`)

## 📄 Licence

Ce projet est libre d'utilisation. Vous pouvez le modifier et le distribuer selon vos besoins.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

## 📞 Support

Pour toute question ou problème, ouvrez une issue sur GitHub.

---

**Développé avec ❤️ pour la communauté Minelaunched**

