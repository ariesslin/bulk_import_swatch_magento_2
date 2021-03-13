<?php
/**
 * Script for bulk import Magento 2 Visual Swatch images
 * -----------------------------------------------------
 * Usage:
 *   1, copy swatch images in magentoRoot/var/import/images/ directory 
 *   2, edit your csv file accordingly
 *   3, modify $attributeCode and $csvFilename
 *   4, run the script in magentoRoot
 * -----------------------------------------------------
 * Note1: Only tested in Magento CE 2.4.1
 * Note2: Only set labels for default store view
 * Note3: The images better to be square, width == height
 *
 * Todo: labels for multistore views
 *       clear exsiting swatches to avoid duplicated problem
 *       check csv format
 *       check image existence
 *
 * Contact: ariess.lin#gmail.com
 */


/* >>>>>>INPUT */
$attributeCode = 'color'; //modify to your attribute code
$csvFilename = __DIR__ . DIRECTORY_SEPARATOR . 'mySwatchList.csv'; //modify to your csv file name

// Add Bootstrap
use \Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstraps = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstraps->getObjectManager();

// Get Config
$appState = $objectManager->get('\Magento\Framework\App\State');
$appState->setAreaCode('frontend');
$eavConfig = $objectManager->get('\Magento\Eav\Model\Config');
$attribute = $eavConfig->getAttribute('catalog_product', $attributeCode);

// Prepare media files path
$filesystem = $objectManager->create('\Magento\Framework\Filesystem');
$driverFile = $objectManager->create('\Magento\Framework\Filesystem\Driver\File');
$swatchHelper = $objectManager->create('\Magento\Swatches\Helper\Media');

$productMediaConfig = $objectManager->create('\Magento\Catalog\Model\Product\Media\Config');
$mediaDirectory = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
$fullTmpMediaPath = $mediaDirectory->getAbsolutePath($productMediaConfig->getBaseTmpMediaPath());
// echo $fullTmpMediaPath; // 'magentoRoot/pub/media/tmp/catalog/product'
$driverFile->createDirectory($fullTmpMediaPath);


/* Convert csv file to import array
 * Visual Swatch format: adminKey => array("label"=>defautStoreLabel, "url"=>imageName)
 * csv header: key,label,url
 *
 * $newSwatchData = array (
 *     'redShirt' => array("label" => "red shirt", "url" => "red.jpg"),
 * );
 */
$newSwatchData = csvConvertToArray($csvFilename);

// Generate options as we are creating Visual Swatch hence passing 'visual' as parameter 
$data = generateOptions($newSwatchData, 'visual');

foreach ($data as $key => $attributeOptionsData) {
	if($key == "swatchvisual" ) {
		$swatchVisualFiles = isset($attributeOptionsData['value']) ? $attributeOptionsData['value'] : [];
		
		foreach ($swatchVisualFiles as $index => $swatchVisualFile) {
			if(!empty($swatchVisualFile)) {
				$imageImportPathFile = __DIR__ . DIRECTORY_SEPARATOR . 'var'. DIRECTORY_SEPARATOR. 'import'
					. DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $swatchVisualFile;
				if(file_exists($imageImportPathFile)) {
					// Copy images to tmp directory
					// note: use basename to remove the path
					$driverFile->copy($imageImportPathFile,
						$fullTmpMediaPath . DIRECTORY_SEPARATOR . basename($swatchVisualFile));
					
					$newFile = $swatchHelper->moveImageFromTmp(basename($swatchVisualFile));
					// Fix generating swatch variations for files beginning with "."
					if (substr($newFile, 0, 1) == '.') {
						$newFile = substr($newFile, 1);
					}
					// The images are copied in 'magentoRoot\pub\media\attribute\swatch'
					$swatchHelper->generateSwatchVariations($newFile);

					$data[$key]['value'][$index] = $newFile;
				} else {
					$data[$key]['value'][$index] = "";
				}
			}
		}
	}
}

// Import data
$attribute->addData($data)->save();


/**
 * Convert array to product attribute data format
 * 
 * keyword: swatchvisual
 */
function generateOptions($values, $swatchType = 'visual') {
	$i = 0;
	foreach($values as $key => $value) {
		$order["option_{$i}"] = $i;
		$optionsStore["option_{$i}"] = array(
			0 => $key, // admin
			1 => $value['label'], // default store view
		);
		$textSwatch["option_{$i}"] = array(
			1 => $value['label'],
		);
		$visualSwatch["option_{$i}"] = $value['url'];
		$delete["option_{$i}"] = '';
		$i ++;
	}
	
	switch($swatchType)
	{
	case 'text':
		return [
			'optiontext' => [
				'order'     => $order,
				'value'     => $optionsStore,
				'delete'    => $delete,
			],
			'swatchtext' => [
				'value'     => $textSwatch,
			],
		];
		break;
	case 'visual':
		return [
			'optionvisual' => [
				'order'     => $order,
				'value'     => $optionsStore,
				'delete'    => $delete,
			],
			'swatchvisual' => [
				'value'     => $visualSwatch,
			],
		];
		break;
	default:
		return [
			'option' => [
				'order'     => $order,
				'value'     => $optionsStore,
				'delete'    => $delete,
			],
		];
	}
}

/*
 * Convert csv file to array
 */
function csvConvertToArray($filename) {
	$data = array();
	$i = 0;
	$handle = fopen($filename, 'r');
	if ($handle) {
		while (($line = fgetcsv($handle)) !== FALSE) {
			if( $i == 0 ) {
				$header = $line;
			} else {
				$entry = array_combine($header, $line);
				$data[] = $entry;
			}
			$i++;
		}
		fclose($handle);
		
		foreach ($data as $key => $_value) {
			$_data[$_value['key']]['label'] = $_value['label'];
			$_data[$_value['key']]['url'] = $_value['url'];
		}
		//print_r($_data);
		return $_data;
	} else {
		echo "csv file NOT exist";
		return $data;
	}
}

/**
 * Acknowledgements:
 * https://magento.stackexchange.com/questions/133237/magento-2-create-custom-swatch-attribute-programmatically
 * @deepakd
 */
?>

