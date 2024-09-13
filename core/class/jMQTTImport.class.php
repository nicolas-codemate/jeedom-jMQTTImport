<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__.'/../../../../core/php/core.inc.php';
require_once __DIR__.'/../../core/class/jMQTTImported.php';

class jMQTTImport extends eqLogic
{
    private const DEFAULT_CSV_SEPARATOR = ';';

    /*     * *************************Attributs****************************** */

    /*
    * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
    * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
    public static $_widgetPossibility = array();
    */

    /*
    * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
    * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
    public static $_encryptConfigKey = array('param1', 'param2');
    */

    /*     * ***********************Methode static*************************** */

    /**
     * @return jMQTTImported[]|null Return null if error occurs, or return an array of created jMQTT imported model
     */
    public static function importCsv(
        $csvFile,
        bool $flushToDb,
        string $brokerId,
        string $parentObjectId,
        string $columnForEqName,
        string $topic,
        string $template,
        bool $isVisible,
        bool $isEnable
    ): ?array {
        self::logger(
            'debug',
            sprintf(
                'Import du fichier "%s" avec comme input %s. Savegarde en bdd : %s',
                $csvFile['name'],
                $flushToDb ? 'true' : 'false',
                var_export(compact('brokerId', 'parentObjectId', 'topic', 'template'), true)
            )
        );

        $handle = fopen($csvFile['tmp_name'], 'rb');
        if (!$handle) {
            $message = sprintf('Impossible d\'ouvrir le fichier %s', $csvFile['name']);
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }
        $authorizedMimeTypes = [
            'text/x-comma-separated-values',
            'text/comma-separated-values',
            'application/octet-stream',
            'application/vnd.ms-excel',
            'application/x-csv',
            'text/x-csv',
            'text/csv',
            'application/csv',
            'application/excel',
            'application/vnd.msexcel',
            'text/plain',
        ];

        // check the mime type
        if (false === \in_array($csvFile['type'], $authorizedMimeTypes, true)) {
            $message = sprintf('Le fichier %s n\'est pas un fichier CSV', $csvFile['name']);
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }

        // get the broker from the id
        try {
            $broker = jMQTT::byId($brokerId);
            if (null === $broker) {
                throw new \RuntimeException();
            }
        } catch (Exception $exception) {
            $message = sprintf('Impossible de trouver le broker %s', $brokerId);
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }

        // get the parent object from the id
        $parentObject = jeeObject::byId($parentObjectId);
        if (!$parentObject) {
            $message = sprintf('Impossible de trouver l\'objet parent %s', $parentObjectId);
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }

        $templateAsJson = null;
        if ($template) {
            try {
                $templateAsJson = jMQTT::templateByName($template);
            } catch (\Exception $e) {
                $message = sprintf('Impossible de trouver le template %s', $template);
                self::logger('error', $message);
                ajax::error($message, 400);

                return null;
            }
        }

        if (!class_exists('jMQTT')) {
            $message = 'Impossible de trouver la classe jMQTT. Vérifier que le plugin jMQTT est bien installé';
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }

        // get the csv header column
        $header = fgetcsv($handle, 0, self::DEFAULT_CSV_SEPARATOR);
        if (!$header) {
            $message = sprintf('Impossible de lire la première ligne du fichier %s', $csvFile['name']);
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }

        $header = array_map(static function ($column) {
            return preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $column);
        }, $header);

        if (false === \in_array($columnForEqName, $header, true)) {
            $message = sprintf('La colonne %s n\'existe pas dans le fichier %s', $columnForEqName, $csvFile['name']);
            self::logger('error', $message);
            ajax::error($message, 400);

            return null;
        }

        $egLogicData = [];

        // read the csv lines
        while ($line = fgetcsv($handle, null, self::DEFAULT_CSV_SEPARATOR)) {
            $line = array_map(static function ($value) {
                return strtolower(trim($value));
            }, $line);
            $overlay = array_fill(0, count($header), null);
            $data = array_combine($header, $line + $overlay); // fill the missing columns with null

            $data['name'] = $data[$columnForEqName];
            $egLogicData[] = $data;
        }

        if ($flushToDb) {
            // we use transaction to be able to rollback if any errors occurs during the import
            db::beginTransaction();
        }

        $eqLogicCreated = [];
        try {
            foreach ($egLogicData as $datum) {
                $eqLogic = new jMQTT();
                $eqLogicConfiguration = [
                    'name' => $datum['name'],
                    'type' => 'eqpt',
                    'eqLogic' => $broker->getId(),
                    'object_id' => $parentObject->getId(),
                    'isVisible' => (int)$isVisible,
                    'isEnable' => (int)$isEnable,
                ];
                utils::a2o($eqLogic, $eqLogicConfiguration);
                if ($flushToDb) {
                    $eqLogic->save();
                    self::logger(
                        'debug',
                        sprintf('Création de l\'équipement %s', $datum['name'])
                    );
                }

                if ($flushToDb && $templateAsJson) {
                    $eqLogicTopic = self::replaceVariables($topic, $datum);

                    $eqLogic->applyATemplate($templateAsJson, $eqLogicTopic);

                    /** @var cmd[] $cmds */
                    $cmds = $eqLogic->getCmd();
                    foreach ($cmds as $cmd) {
                        $request = $cmd->getConfiguration('request');
                        $request = self::replaceVariables($request, $datum);
                        $cmd->setConfiguration('request', $request);
                        $cmd->save();
                    }

                    self::logger(
                        'debug',
                        sprintf('Application du template %s à l\'équipement %s', $template, $datum['name'])
                    );
                }
                $eqLogicCreated[] = new jMQTTImported($eqLogic, $datum);
            }
        } catch (\Throwable $exception) {
            if ($flushToDb) {
                db::rollback();
            }
            self::logger(
                'error',
                sprintf('Erreur lors de l\'import du fichier %s : %s', $csvFile['name'], $exception->getMessage())
            );
            ajax::error(
                sprintf('Erreur lors de l\'import du fichier %s : %s', $csvFile['name'], $exception->getMessage()),
                400
            );

            return null;
        }

        if ($flushToDb) {
            db::commit();
        }

        return $eqLogicCreated;
    }

    /**
     * @param jMQTTImported[] $JMQTTImporteds
     */
    public static function buildCsv(array $JMQTTImporteds): void
    {
        if (0 === count($JMQTTImporteds)) {
            return;
        }

        $slugify = static function (?string $value) {
            if (null === $value) {
                return 'unknown';
            }

            return str_replace(' ', '-', strtolower($value));
        };

        $firstEqLogic = $JMQTTImporteds[0]->getJMQTT();

        $parentObjectName = $firstEqLogic->getObject() ? $firstEqLogic->getObject()->getName() : 'unknown';

        $brokerName = $firstEqLogic->getBroker() ? $firstEqLogic->getBroker()->getName() : 'unknown';

        $csvName = sprintf(
            'import_%s_%s_%s.csv',
            date('Ymd-His'),
            $slugify($parentObjectName),
            $slugify($brokerName),
        );

        $csvPath = __DIR__.'/../../download/'.$csvName;

        $handle = fopen($csvPath, 'wb');

        fputcsv(
            $handle,
            [
                'name',
                'dev_eui',
                'join_eui',
                'app_key',
                'id',
            ],
            self::DEFAULT_CSV_SEPARATOR
        );

        foreach ($JMQTTImporteds as $eqLogic) {
            fputcsv($handle, [
                $eqLogic->getName(),
                $eqLogic->getDevEUI(),
                $eqLogic->getJoinEUI(),
                $eqLogic->getAppKey(),
                $eqLogic->getId(),
            ], self::DEFAULT_CSV_SEPARATOR);
        }

        $message = sprintf('Fichier %s créé', $csvName);
        self::logger('info', $message);


        fclose($handle);
    }

    /**
     * Log messages to jMQTT log file
     *
     * @param string $level
     * @param string $msg
     */
    public static function logger($level, $msg): void
    {
        log::add(__CLASS__, $level, $msg);
    }

    /**
     * Replace {{variable}} in a template with the corresponding value in the data array
     * Fallback to the variable name if the value is not found in the data array
     */
    public static function replaceVariables(string $template, array $data): string
    {
        $toReturn = preg_replace_callback(
            '/{{(.*?)}}/s',
            static function ($matches) use ($data) {
                return strtolower($data[$matches[1]]) ?? $matches[1];
            },
            $template
        );

        if (!$toReturn) {
            return $template;
        }

        return $toReturn;
    }

    /*
    * Fonction exécutée automatiquement toutes les minutes par Jeedom
    */

    /*
    * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
    public static function cron5() {}
    */

    /*
    * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
    public static function cron10() {}
    */

    /*
    * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
    public static function cron15() {}
    */

    /*
    * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
     * /
    public static function cron30() {}
    */

    /*
    * Fonction exécutée automatiquement toutes les heures par Jeedom
    public static function cronHourly() {}
    */

    /*
    * Fonction exécutée automatiquement tous les jours par Jeedom
    */
    public static function cronDaily()
    {
        // empty download directory
        $files = glob(__DIR__.'/../../download/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /*
    * Permet de déclencher une action avant modification d'une variable de configuration du plugin
    * Exemple avec la variable "param3"
    public static function preConfig_param3( $value ) {
      // do some checks or modify on $value
      return $value;
    }
    */

    /*
    * Permet de déclencher une action après modification d'une variable de configuration du plugin
    * Exemple avec la variable "param3"
    public static function postConfig_param3($value) {
      // no return value
    }
    */

    /*
     * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
     * lors de la création semi-automatique d'un post sur le forum community
     public static function getConfigForCommunity() {
        // Cette function doit retourner des infos complémentataires sous la forme d'un
        // string contenant les infos formatées en HTML.
        return "les infos essentiel de mon plugin";
     }
     */

    /*     * *********************Méthodes d'instance************************* */

    // Fonction exécutée automatiquement avant la création de l'équipement
    public function preInsert()
    {
    }

    // Fonction exécutée automatiquement après la création de l'équipement
    public function postInsert()
    {
    }

    // Fonction exécutée automatiquement avant la mise à jour de l'équipement
    public function preUpdate()
    {
    }

    // Fonction exécutée automatiquement après la mise à jour de l'équipement
    public function postUpdate()
    {
    }

    // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
    public function preSave()
    {
    }

    // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
    public function postSave()
    {
    }

    // Fonction exécutée automatiquement avant la suppression de l'équipement
    public function preRemove()
    {
    }

    // Fonction exécutée automatiquement après la suppression de l'équipement
    public function postRemove()
    {
    }

    /*
    * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
    * Exemple avec le champ "Mot de passe" (password)
    public function decrypt() {
      $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
    }
    public function encrypt() {
      $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
    }
    */

    /*
    * Permet de modifier l'affichage du widget (également utilisable par les commandes)
    public function toHtml($_version = 'dashboard') {}
    */

    /*     * **********************Getteur Setteur*************************** */
}

class jMQTTImportCmd extends cmd
{
    /*     * *************************Attributs****************************** */

    /*
    public static $_widgetPossibility = array();
    */

    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
    * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
    public function dontRemoveCmd() {
      return true;
    }
    */

    // Exécution d'une commande
    public function execute($_options = array())
    {
    }

    /*     * **********************Getteur Setteur*************************** */
}
