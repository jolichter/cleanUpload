<?php
/*
* V 22.12.013
*
* cleanUpload is a MODX Revolution FileManager Plugin for Pictures and PDF's
* Testet with MODX 2.8.4 (PHP 7.4.x) and 3.0.2 (PHP 8.1.x)
*
* File name transliteration and customizing the picture size
* JPG and PDF Metadata will be removed!
* Same file names are NOT overwritten! Instead, a uniq ID is appended to these files.
* Two system events need to be enabled: Two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload
*
* Since MODX 3: Transliterate (upload_translit) must be disabled in the settings for this (cleanUpload uses its own!).
*  - Transliterate names of uploading files
*  - Type: Yes/No (default: Yes)
*  - if 'Yes' name of any uploading file will be transliterated by global transliteration rules
*
* Sources:
* https://www.php.net/manual/de/function.image-type-to-extension.php
* https://forums.modx.com/?action=thread&thread=73940&page=2
*/

// Parameter
$maxWidth = 1280;    // maximum pixel width | maximale Pixelbreite
$maxHeight = 1280;   // Maximum pixel height | maximale Pixelhöhe
$quality = 80;       // JPG quality in % (default 80) | JPG Qualität in % (Vorgabe 80)


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
    $source_gd_image = '';
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
// resize Images if necessary
foreach($files as $file) {
    global $modx;
    if ($file['error'] == 0) {
        $slug = '_';
        $dir = $directory;
        $fileDir = $directory.$file['name'];                                 # Directory + Filename.ext
        $bases = $source->getBases($directory);                              # Array
        $fullPath = $bases['pathAbsolute'].ltrim($directory, '/');           # pathAbsolute + Directory
        $pathInfo = pathinfo($file['name']);                                 # Array
        $fileName = $pathInfo['filename'];                                   # Filename without extension
        $fileNameNew = cleanFilename($modx, $fileName, $slug);               # Function
        $fileExt = '.'.$pathInfo['extension'];                               # File extension
        $fileExtLow = strtolower($fileExt);                                  # File extension to low
        $fullNameNewLow = $fileNameNew.$fileExtLow;
        $fullPathNameNew = $fullPath.$fileNameNew.$fileExtLow;

        # $modx->log(modX::LOG_LEVEL_ERROR, '[cleanUpload] '.$fileName.' <----> '.$fileNameNew);

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

        // if file is a PDF
        if ($fileExtLow == '.pdf') {
           // read the input PDF file
           $inputPDF = file_get_contents($fullPathNameNew);
           // remove PDF metadata
           $outputPDF = preg_replace('/\/Info\s\d+\s\d+\sR/s', '/Info 0 R', $inputPDF);
           // write the output PDF file
           file_put_contents($fullPathNameNew, $outputPDF);
        }

break;
} } }
