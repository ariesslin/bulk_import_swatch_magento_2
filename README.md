### bulk_import_swatch_magento_2

### Script for bulk import Magento 2 Visual Swatch images

Usage:

1. copy swatch images in magentoRoot/var/import/images/ directory 
2. edit your csv file accordingly
3. modify $attributeCode and $csvFilename
4. run the script in magentoRoot

Note: 

- Only tested in Magento CE 2.4.1  
- Only set labels for default store view  
- The images better to be square, width == height

Todo: 

- labels for multistore views  
- clear exsiting swatches to avoid duplicated problem  
- check csv format  
- check image existence
