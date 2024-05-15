# jMQTTImport

Ce plugin permet d'importer en masse des équipements reliés à un broker jMQTT.

Le plugin jMQTT est donc nécessaire pour utiliser ce plugin.

## Utilisation

* Le fichier CSV à importer doit utiliser le ";" comme séparateur.
* vous pouvez avoir autant de colonnes que vous le souhaitez dans le fichier csv.
* vous devez préciser le nom de la colonne qui sera utilisé pour le nom de l'équipement. Attention à la casse.
* si vous utiliser un template préexistant, vous devrez préciser le nom du topic à utiliser pour chaque équipement
  * il est possible d'utiliser une syntaxe utilisant le format templating suivant `test_{{ nom de colonne }}`.
  * également dans le template, dans la partie `commands`, il est possible pour la clé `request` d'utiliser les templates avec les noms de colonne.