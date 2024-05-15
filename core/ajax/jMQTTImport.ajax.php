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

try {
    require_once dirname(__FILE__).'/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    require_once __DIR__.'/../../core/class/jMQTTImport.class.php';
    ajax::init(['fileUploadForImport']);

    $action = init('action');

    switch ($action) {
        case 'importCsv':
            $topic = init('topic');
            $parentObject = init('parentObject');
            $broker = init('broker');
            $columnForEqName = init('columnForEqName');
            $template = init('template');
            $csvFile = $_FILES['csvFile'];

            if(!$csvFile) {
                ajax::error(['message' => __('Aucun fichier reçu', __FILE__)]);

                return;
            }

            if (!$broker) {
                ajax::error(['message' => __('Aucun broker selectionné', __FILE__)]);

                return;
            }

            if(!$parentObject) {
                ajax::error(['message' => __('Aucun objet parent selectionné', __FILE__)]);

                return;
            }

            if(!$columnForEqName) {
                ajax::error(['message' => __('Aucune colonne pour le nom de l\'équipement', __FILE__)]);

                return;
            }

            if ($template && !$topic) {
                ajax::error(['message' => __('Topic obligatoire lorsque le template est renseigné', __FILE__)]);

                return;
            }

            jMQTTImport::importCsv($csvFile, $broker, $parentObject, $columnForEqName, $topic, $template);
            break;
        case 'fileUploadForImport':
            if (!isset($_FILES['file'])) {
                throw new \RuntimeException(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
            }

            $allowed_ext = '.csv';

            $fileName = $_FILES['file']['name'];

            $extension = strtolower(strrchr($fileName, '.'));
            if ($extension !== $allowed_ext) {
                throw new \RuntimeException(
                    sprintf(__("L'extension de fichier '%s' n'est pas autorisée", __FILE__), $extension)
                );
            }

            $uploadDir = dirname(__DIR__, 2).'/csvImport';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException(__('Répertoire de téléversement non trouvé :', __FILE__) . ' ' . $uploadDir);
            }

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . '/' . $fileName)) {
                throw new \RuntimeException(__('Impossible de déplacer le fichier temporaire', __FILE__));
            }

            ajax::success($fileName);

            break;
        default:
            throw new RuntimeException(__('Aucune méthode correspondante à', __FILE__).' : '.$action);
    }
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
