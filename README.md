# jMQTTImport

Ce plugin permet d'importer en masse des équipements reliés à un broker jMQTT.

Le plugin jMQTT est donc nécessaire pour utiliser ce plugin.

## Utilisation

### Import des équipements
* Le fichier CSV à importer doit utiliser le ";" comme séparateur.
* Vous pouvez avoir autant de colonnes que vous le souhaitez dans le fichier CSV.
* Vous devez préciser le nom de la colonne qui sera utilisé pour le nom de l'équipement. Attention à la casse, ou tout autre caractères spéciaux (', ", etc.).
* Si vous utilisez un template préexistant, vous devrez préciser le nom du topic à utiliser pour chaque équipement.
  * Il est possible d'utiliser une syntaxe utilisant le format de templating suivant `test_{{ nom de colonne }}`.
  * Également dans le template, dans la partie `commands`, il est possible pour la clé `request` d'utiliser les templates avec les noms de colonne.

### Extraction CSV
* Il est possible d'extraire les équipements existants dans un fichier CSV. Le fichier exporté utilisera notamment les données présentes dans le fichier CSV d'import.
* Le fichier CSV exporté utilisera le ";" comme séparateur.
* Le fichier exportera les colonnes suivantes :
  * name (nom de l'équipement dans Jeedom)
  * dev_eui (reprise de la colonne `DevEUI` du fichier d'import via une recherche sur le nom de la colonne)
  * join_eui (reprise de la colonne `AppEUI` du fichier d'import via une recherche sur le nom de la colonne)
  * app_key (reprise de la colonne `AppKey` du fichier d'import via une recherche sur le nom de la colonne)
  * id (reprise de la colonne `SN` du fichier d'import via une recherche sur le nom de la colonne)
* Les fichiers exportés seront nommés `import_YYYYMMDD-HHMMS_{nom de l'objet parent}-{nom du broker}.csv`
* Les fichiers exportés seront placés dans le répertoire `download` du répertoire `plugin/jMQTTImport` de Jeedom.
* Un cron tourne toutes les heures pour supprimer les fichiers.
