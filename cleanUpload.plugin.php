<?php
/*
* cleanUpload is a  MODX Revolution FileManager Plugin
* (testet with MODX 2.3.1, 2.5.6)
*
* file name transliteration and customizing the JPG image size
* (JPG file infos will be removed)
*
* two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload
*
* V 17.04.010
*
* same file names are NOT overwritten!
* instead of this, a uniqid ID is appended to these files
* this works only at MODX 2.3 or higher!
*
*
*
* sources:
* http://php.net/manual/de/function.image-type-to-extension.php
* http://forums.modx.com/?action=thread&thread=73940&page=2
*/


// Parameter
$maxWidth = 960;     // maximale Pixelbreite
$maxHeight = 960;    // maximale Pixelhöhe
$quality = 80;       // jpeg-Qualität


$eventName = $modx->event->name;

// ###################################
// cleaning filename function
if (!function_exists('cleanFilename')) {
   function cleanFilename($modx, $filename, $slug) {
   // trim, lowercase, replace special chars, transliterate
   if (function_exists('iconv')) {
   setlocale(LC_ALL, strtolower($modx->getOption('cultureKey')) . '_' . strtoupper($modx->getOption('cultureKey')));
   $filename = strtolower(trim(preg_replace('~[^0-9a-z-' . preg_quote(null, '~') . ']+~i', $slug, iconv('UTF-8', 'ASCII//TRANSLIT', $filename)), $slug));
   } else {
   // without transliterate
   $filename = strtolower(trim(preg_replace('~[^0-9a-z-' . preg_quote(null, '~') . ']+~i', $slug, $filename), $slug));
   }
   if (empty($filename)) {return 'noname';}
   return $filename;
   }
}
// ###################################
// resize JPEG function
if (!function_exists('imgResize')) {
   function imgResize($modx, $source, $target, $maxWidth, $maxHeight, $quality) {
    // check if GD extension is loaded
    if (!extension_loaded('gd') && !extension_loaded('gd2')) {return false;}
    list($source_width, $source_height, $source_type) = getimagesize($source);
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            $source_gd_image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_GIF:
            $source_gd_image = imagecreatefromgif($source);
            break;
        case IMAGETYPE_PNG:
            $source_gd_image = imagecreatefrompng($source);
            break;
    }
    if ($source_gd_image === false) {return false;}
    $source_aspect_ratio = $source_width / $source_height;
    $aspect_ratio = $maxWidth / $maxHeight;
    if ($source_width <= $maxWidth && $source_height <= $maxHeight) {
        $image_width = $source_width;
        $image_height = $source_height;
    } elseif ($aspect_ratio > $source_aspect_ratio) {
        $image_width = (int) ($maxHeight * $source_aspect_ratio);
        $image_height = $maxHeight;
    } else {
        $image_width = $maxWidth;
        $image_height = (int) ($maxWidth / $source_aspect_ratio);
    }
    // create a new temporary image
    $gd_image = imagecreatetruecolor($image_width, $image_height);
    // copy and resize old image into new image
    imagecopyresampled($gd_image, $source_gd_image, 0, 0, 0, 0, $image_width, $image_height, $source_width, $source_height);
    // save gd_image into a file
    imagejpeg($gd_image, $target, $quality);
    // release the memory
    imagedestroy($source_gd_image);
    imagedestroy($gd_image);
    }
}

// ###################################
// rename each of the uploaded Files and resize Images if necessary
foreach($files as $file) {
    global $modx;
    if ($file['error'] == 0) {
        $slug = '_';
        $dir = $directory;
        $fileDir = $directory.$file['name']; #directory/filename.ext
        //$modx->getOption('base_path') + path Media Sources + directory File:
        //modfilemediasource.class.php (function uploadObjectsToContainer)
        $bases = $source->getBases($directory);
        $fullPath = $bases['pathAbsolute'].ltrim($directory, '/');
        $pathInfo = pathinfo($file['name']);
        $fileName = $pathInfo['filename'];
        $fileNameNew = cleanFilename($modx, $fileName, $slug);
        $fileExt = '.'.$pathInfo['extension'];
        $fileExtLow = strtolower($fileExt);
        $fullPathName = $fullPath.$fileName.$fileExt;
        $fullNameNewLow = $fileNameNew.$fileExtLow;
        $fullPathNameNew = $fullPath.$fileNameNew.$fileExtLow;

//$modx->log(modX::LOG_LEVEL_ERROR, '[cleanFilename] '.fullPathNameNew.' <----> '.$fullNameNewLow);


switch($eventName) {

case 'OnFileManagerBeforeUpload':
            // if the file exist, add an unique-ID Number in file
            if (file_exists($fullPathNameNew)) {
               $uni = uniqid();
               $fileTemp= $fileNameNew.'_'.$uni;
               $fileTemp = $fileTemp.$fileExtLow;
               $source->renameObject($dir.$fullNameNewLow, $fileTemp);
            }
break;

case 'OnFileManagerUpload':
            // Transliteration necessary?
            if ($fileName != $fileNameNew) {
                $source->renameObject($fileDir, $fullNameNewLow);
            }
            else {
            // or is only the extension to lower necessary?
            if ($fileExt != $fileExtLow) {
                $source->renameObject($fileDir, $fullNameNewLow);
            }
            }

        // if file is a jpg-image
        if ($fileExtLow == '.jpg' || $fileExtLow == '.jpeg') {
        imgResize($modx, $fullPathNameNew, $fullPathNameNew, $maxWidth, $maxHeight, $quality);
        }
break;
} } }
