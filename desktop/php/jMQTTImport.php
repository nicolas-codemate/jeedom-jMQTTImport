<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('jMQTTImport');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

include_file('desktop', 'jMQTT.globals', 'js', 'jMQTT');
include_file('desktop', 'jMQTT.functions', 'js', 'jMQTT');

// Include style sheet file
include_file('desktop', 'jMQTTImport', 'css', 'jMQTTImport');

$objects = jeeObject::all();
$objectsById = [];
foreach (jeeObject::all() as $object) {
    $objectsById[$object->getId()] = $object->getName();
}

$buildDownloadUrl = static function (string $path) {
    return './core/php/downloadFile.php?pathfile=' . urlencode($path) . '&plugin=jMQTTImport';
};

/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();
$eqBrokersName = array();
foreach ($eqBrokers as $id => $eqL) {
    $eqBrokersName[$id] = $eqL->getName();
}
sendVarToJS('jmqtt_globals.eqBrokers', $eqBrokersName);
sendVarToJS('jeeObjects', $objectsById);

$importedFiles = scandir(__DIR__ . '/../../download', SCANDIR_SORT_DESCENDING);
$importedFiles = array_diff($importedFiles, ['.', '..']);
// keep only csv files
$importedFiles = array_filter($importedFiles, static function ($file) {
    return pathinfo($file, PATHINFO_EXTENSION) === 'csv';
});

?>

<div class="row row-overflow">
	<!-- Page d'accueil du plugin -->
	<div class="col-xs-12">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<!-- Boutons de gestion du plugin -->
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="importEq">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Importer des équipements}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes Imports}}</legend>
		<?php
		if (count($importedFiles) == 0) {
			echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun fichier d\'import trouvé.}}</div>';
		} else {
			// Liste des équipements du plugin
			echo '<div class="exportListContainer">';
			foreach ($importedFiles as $k => $importedFile) {
                echo '<div class="exportName cursor displayAsTable">';
                echo '<span class="file"><strong> '.$importedFile.'</strong></span>';
                echo '<span class="displayTableRight" style="font-size:12px">';
                echo '<a target="_blank" href="'.$buildDownloadUrl(__DIR__ . '/../../download/' . $importedFile).'"><span class="label label-success">{{Télécharger}}</span></a>&nbsp;';
//                echo '<span class="label label-danger">Supprimer</span>';
                echo '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		?>
	</div> <!-- /.eqLogicThumbnailDisplay -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'jMQTTImport', 'js', 'jMQTTImport'); ?>

<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>
