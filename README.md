# vulnweb

Application PHP de demonstration reprise et durcie pour le chantier DevSecOps.

## Correctifs SAST appliques

- secrets retires du code source et lus depuis les variables d'environnement
- requete SQL remplacee par une requete preparee PDO
- sorties HTML echappees pour eviter les injections cote navigateur
- fonctionnalite de diagnostic reseau remplacee par un test TCP valide cote serveur

## Configuration locale

Les variables attendues sont documentees dans `.env.example`.
