<?php
/*
* MODX Revo Plugin für den Datei Upload Manager
* (getestet mit Revo 2.2.6, 2.3.1, 2.5.5)
*
* Dateiname Transliteration und jpeg-Bildgröße Anpassung
* (jpeg-Dateiinfos werden entfernt)
*
* System Events: OnFileManagerUpload!
*
* V 17.03.008
*
* Gleiche Dateinamen werden wie gehabt überschrieben!
* Ein 'OnBeforeFileManagerUpload' gibt es leider (noch) nicht.
* Bei der Transliteration verhindere ich das, indem ich an der neuen Datei ein uniqid() anhänge.
*
*
* Quellen:
* http://php.net/manual/de/function.image-type-to-extension.php
* http://forums.modx.com/?action=thread&thread=73940&page=2
*/


# Parameter
$maxWidth = 960;    // maximale Pixelbreite
$maxHeight = 960;   // maximale Pixelhöhe
$quality = 80;       // jpeg-Qualität



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
        $fileDir = $directory.$file['name']; #directory/filename.ext

        //$modx->getOption('base_path') + path Media Sources + directory File:
        //modfilemediasource.class.php (function uploadObjectsToContainer)
        $bases = $source->getBases($directory);
        $fullPath = $bases['pathAbsolute'].ltrim($directory, '/');

        $pathInfo = pathinfo($file['name']);
        $fileName = $pathInfo['filename'];
        $fileExt = '.'.$pathInfo['extension'];
        $fullName = $fullPath.$fileName.$fileExt;

        $fileNameNew = cleanFilename($modx, $fileName, $slug);
        $fileExt = strtolower($fileExt);
        $fullNameNew = $fullPath.$fileNameNew.$fileExt;
        //$modx->log(modX::LOG_LEVEL_ERROR, '[cleanFilename] '.$fullName.' <----> '.$fullNameNew);


        // Transliteration necessary?
        if ($fileName != $fileNameNew){

            // if the file exist, add an unique-ID Number in file
            if (file_exists($fullNameNew)) {
            $uni = uniqid();
            $fileNameNew = $fileNameNew.'_'.$uni;
            $fullNameNew = $fullPath.$fileNameNew.$fileExt;
                 } 
        $source->renameObject($fileDir, $fileNameNew.$fileExt);
        }
        //$modx->log(modX::LOG_LEVEL_ERROR, '[cleanFilename] '.$fileDir.' - '.$fileNameNew.$fileExt);


        // if file is a jpeg-image
        if ($fileExt == '.jpg' || $fileExt == '.jpeg') {
        imgResize($modx, $fullNameNew, $fullNameNew, $maxWidth, $maxHeight, $quality);
        //$modx->log(modX::LOG_LEVEL_ERROR, '[cleanFilename] '.$fullNameNew);
        }
    }
}
