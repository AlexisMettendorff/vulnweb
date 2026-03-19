# Corrections SAST appliquees sur `vulnweb`

## Objectif

Ce document explique :

- quelle alerte SAST a ete corrigee
- quelle modification a ete faite
- pourquoi ce correctif a ete choisi

L'idee n'etait pas de masquer les alertes, mais de supprimer la cause technique reelle.

## Vue d'ensemble

Les corrections ont porte sur :

- la suppression des secrets en dur dans le code
- la protection de la requete SQL contre l'injection
- la suppression de l'execution de commandes shell a partir d'une entree utilisateur
- la suppression du risque SSRF sur la fonctionnalite de diagnostic reseau
- le durcissement general de l'application avec echappement HTML et messages d'erreur moins verbeux

## 1. Secrets AWS hardcodes dans le code

### Alertes corrigees

- `generic.secrets.security.detected-aws-access-key-id-value.detected-aws-access-key-id-value`
- `generic.secrets.security.detected-aws-secret-access-key.detected-aws-secret-access-key`

### Fichiers modifies

- `src/config.php`
- `docker-compose.yml`
- `.env.example`
- `.gitignore`

### Probleme initial

Le fichier `src/config.php` contenait des valeurs qui ressemblaient a des cles AWS.

Risques :

- fuite de secrets dans Git
- blocage CI par Semgrep et outils de secret scanning
- mauvaise pratique de configuration applicative

### Correctif applique

Les valeurs sensibles ont ete retirees du code source.  
La configuration est maintenant lue depuis des variables d'environnement via `getenv()`.

Exemples :

- `APP_DB_HOST`
- `APP_DB_NAME`
- `APP_DB_USER`
- `APP_DB_PASSWORD`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`

Un fichier `.env.example` a ete ajoute pour documenter les variables attendues, et `.env` a ete ignore via `.gitignore`.

### Pourquoi cette methode

Parce qu'un secret ne doit jamais vivre dans le code versionne.

Cette approche :

- est compatible avec Docker et la CI/CD
- separe le code de la configuration
- evite que Semgrep et les scanners de secrets bloquent la pipeline
- permet de changer les valeurs sans modifier le code

## 2. Injection SQL sur la recherche utilisateur

### Alerte corrigee

- `php.lang.security.injection.tainted-sql-string.tainted-sql-string`

### Fichier modifie

- `src/index.php`

### Probleme initial

La requete SQL etait construite en concatenant directement `$_GET['search']` dans la chaine SQL :

```php
$sql = "SELECT username, role, password FROM users WHERE username = '$search'";
```

Risques :

- injection SQL
- lecture ou modification illegitime des donnees
- exposition du champ `password`

### Correctif applique

La requete a ete remplacee par une requete preparee PDO avec parametre nomme :

```php
$statement = $pdo->prepare(
    'SELECT username, role FROM users WHERE username = :username'
);
$statement->execute(['username' => $search]);
```

Le champ `password` n'est plus selectionne.

### Pourquoi cette methode

Parce qu'une requete preparee separe la structure SQL des donnees utilisateur.

Cette approche :

- bloque l'injection SQL
- est la bonne pratique standard en PHP avec PDO
- reduit la surface de fuite en ne recuperant pas de donnees inutiles

## 3. Command injection via `system("ping ...")`

### Alertes corrigees

- `php.lang.security.exec-use.exec-use`
- `php.lang.security.injection.tainted-exec.tainted-exec`
- `php.lang.security.tainted-exec.tainted-exec`

### Fichier modifie

- `src/index.php`

### Probleme initial

L'application executait une commande shell en concatenant une valeur issue de l'utilisateur :

```php
system("ping -c 2 " . $ip);
```

Risques :

- execution de commandes arbitraires
- remote code execution
- contournement de validation si l'entree etait mal filtre

### Correctif applique

La logique shell a ete supprimee.  
La fonctionnalite de diagnostic a ete remplacee par un test de connectivite TCP avec `stream_socket_client()`.

### Pourquoi cette methode

Le bon correctif n'etait pas `escapeshellarg()`, car le besoin metier n'etait pas d'executer une commande shell.

Supprimer totalement l'appel shell est plus robuste :

- plus de surface d'attaque liee au shell
- comportement plus simple a auditer
- reponse plus previsible dans un conteneur Docker

## 4. Risque SSRF sur l'hote du diagnostic reseau

### Alerte corrigee

- `php.lang.security.injection.tainted-url-host.tainted-url-host`

### Fichier modifie

- `src/index.php`

### Probleme initial

Apres suppression du `system()`, l'application permettait encore a l'utilisateur de fournir une IP cible pour `stream_socket_client()`.

Semgrep a signale a juste titre un risque SSRF : meme avec une IP valide, un utilisateur pourrait forcer le serveur a contacter des ressources internes ou non prevues.

### Correctif applique

La saisie libre de l'hote a ete supprimee.

L'application propose maintenant une liste fermee de cibles autorisees :

- `db` -> `tcp://devsecops-bdd:3306`
- `web` -> `tcp://devsecops-web:80`
- `adminer` -> `tcp://devsecops-adminer:8080`

Le formulaire utilise un `select` et le code resolve uniquement ces cibles connues.

### Pourquoi cette methode

Valider un format d'IP ne suffit pas contre le SSRF.  
Le vrai correctif est de ne jamais laisser l'utilisateur choisir un hote arbitraire.

L'allowlist :

- supprime la possibilite de scanner des hosts libres
- limite la fonctionnalite au besoin metier reel
- est plus defendable en audit securite

## 5. Echappement HTML des sorties utilisateur

### Correction de durcissement

### Fichier modifie

- `src/index.php`

### Probleme initial

La valeur de recherche et certaines donnees etaient affichees directement dans le HTML.

Risque :

- injection HTML ou XSS reflechi

### Correctif applique

Une fonction `escapeHtml()` a ete ajoutee et les sorties affichees dans la page passent par `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

### Pourquoi cette methode

Parce qu'une entree utilisateur ne doit jamais etre reinjectee dans le HTML sans encodage de sortie.

Cela protege :

- l'affichage du terme de recherche
- les valeurs du formulaire
- les messages et resultats affiches

## 6. Messages d'erreur moins sensibles

### Correction de durcissement

### Fichier modifie

- `src/index.php`

### Probleme initial

Les erreurs SQL ou de connexion BDD pouvaient etre affichees directement au navigateur.

Risques :

- fuite d'informations techniques
- aide involontaire a un attaquant

### Correctif applique

Les erreurs detaillees sont journalisees cote serveur via `error_log()`, et l'interface affiche un message generique.

### Pourquoi cette methode

En production, il faut separer :

- le message utile pour l'utilisateur
- le detail technique utile pour l'exploitation ou le debug

## 7. Mise en coherence de la configuration Docker

### Fichiers modifies

- `docker-compose.yml`
- `.env.example`

### Correctif applique

Les variables de l'application web et de la base sont maintenant alignees avec la configuration externe.

Exemples :

- `APP_DB_HOST`
- `APP_DB_NAME`
- `APP_DB_USER`
- `APP_DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`

### Pourquoi cette methode

Parce qu'un durcissement du code sans alignement de l'environnement cree de la confusion.

Cette mise en coherence permet :

- un fonctionnement identique en local et en CI
- une configuration plus propre pour Docker Compose
- une meilleure lisibilite pour le projet

## Resume final

Les erreurs corrigees ne l'ont pas ete par des exclusions Semgrep ou des contournements cosmetiques.  
Chaque modification a vise a retirer la source de la vulnerabilite :

- secret en dur -> variable d'environnement
- SQL concatene -> requete preparee
- commande shell -> suppression de l'appel shell
- hote libre pour diagnostic -> allowlist stricte
- sortie HTML brute -> echappement
- erreurs detaillees cote client -> journalisation serveur

## Fichiers concernes

- `src/config.php`
- `src/index.php`
- `docker-compose.yml`
- `.env.example`
- `.gitignore`
- `README.md`
