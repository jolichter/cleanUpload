<?php
/*
* V 22.12.015
*
* cleanUpload is a MODX Revolution FileManager Plugin when uploading with Media Browser
* Clean up and optimize data, JPEG and PDF Metadata will be removed, GDPR compliant (DSGVO Konform)
*
* Testet with MODX 2.8.4 (PHP 7.4.x) and 3.0.2 (PHP 8.1.x)
* File name transliteration and customizing the picture size
* Same file names are NOT overwritten, instead a uniq ID is appended to these files
* Two system events need to be enabled: OnFileManagerBeforeUpload, OnFileManagerUpload
*
* Since MODX 3: Transliterate (upload_translit) must be disabled in the settings for this (cleanUpload uses its own).
*  - Transliterate names of uploading files
*  - Type: Yes/No (default: Yes)
*  - if 'Yes' name of any uploading file will be transliterated by global transliteration rules
*
* Reference and inspiration:
* https://www.php.net/manual/de/function.image-type-to-extension.php
* https://forums.modx.com/?action=thread&thread=73940&page=2
*/

// Settings
$maxWidth = 1280;    // Maximum pixel width | Maximale Pixelbreite
$maxHeight = 1280;   // Maximum pixel height | Maximale Pixelhöhe
$quality = 80;       // JPEG quality in % (default 80) | JPEG Qualität in % (Vorgabe 80)
$slug ='_';          // Replacement character | Ersetzungszeichen

global $modx;
$eventName = $modx->event->name;

// Checks if GD extension is loaded
   if (!extension_loaded('gd') && !extension_loaded('gd2')) {
      $modx->log(modX::LOG_LEVEL_ERROR, '[cleanUpload] Error: GD extension not loaded');
      return false;
   }

// ###################################
// Cleaning filename function
if (!function_exists('cleanFilename')) {
   function cleanFilename($modx, $filename, $slug) {
   // trim, replace special chars, transliterate

   // Replace German Umlaute (no problem if use meta charset="UTF-8")
   # $filename = str_replace(array('ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'), array('ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'), $filename);

   // Trying to use iconv (I've disabled this because I want to have German Umlaute)
   # if (function_exists('iconv')) {
      # setlocale(LC_ALL, strtolower($modx->getOption('cultureKey')) . '_' . strtoupper($modx->getOption('cultureKey')));
      # $filename = trim(preg_replace('~[^a-zA-Z0-9-' . preg_quote(null, '~') . ']+~i', $slug, iconv('UTF-8', 'ASCII//TRANSLIT', $filename)), $slug);
   # } else {
      // Without transliterate (If you don't want to have Umlaute, remove: äöüÄÖÜß)
      $filename = trim(preg_replace('~[^a-zA-Z0-9äöüÄÖÜß-' . preg_quote(null, '~') . ']+~i', $slug, $filename), $slug);
   # }
   if (empty($filename)) {return false;}
	  return $filename;
   }
}


// ###################################
// Resize JPEG function
if (!function_exists('imgResize')) {
   function imgResize($modx, $source, $target, $maxWidth, $maxHeight, $quality) {

    list($source_width, $source_height, $source_type) = getimagesize($source);
    $source_gd_image = '';
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            $source_gd_image = imagecreatefromjpeg($source);

            // Rotate image, if info available, because metadata will be removed
            $exif = exif_read_data($source);
                   if ($exif === false) {
                       // No EXIF-Metadata
                   } else {
                        if (isset($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                          case 3:
                              $source_gd_image = imagerotate($source_gd_image, 180, 0);
                              break;
                          case 6:
                              $source_gd_image = imagerotate($source_gd_image, -90, 0);
                              break;
                          case 8:
                              $source_gd_image = imagerotate($source_gd_image, 90, 0);
                              break;
                              }
                        }
                   }
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

    // Create a new temporary image
    $gd_image = imagecreatetruecolor($image_width, $image_height);
    // Copy and resize old image into new image
    imagecopyresampled($gd_image, $source_gd_image, 0, 0, 0, 0, $image_width, $image_height, $source_width, $source_height);
    // Save gd_image into a file
    imagejpeg($gd_image, $target, $quality);
    // Destroy the images to free up memory
    imagedestroy($source_gd_image);
    imagedestroy($gd_image);
    }
}

// ###################################
// Resize images if necessary
foreach($files as $file) {

    // Checks if an error occurred while uploading the file
    if ($file['error'] != 0) {
       $modx->log(modX::LOG_LEVEL_ERROR, '[cleanUpload] Error: '.$file);
       return false;
    }

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
            // If the file exist, add an unique-ID Number in file
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
                  // Or is only the extension to lower necessary?
                  if ($fileExt != $fileExtLow) {
                      $source->renameObject($fileDir, $fullNameNewLow);
                  }
           }

        // If file is a JPEG-Picture
        if ($fileExtLow == '.jpg' || $fileExtLow == '.jpeg') {
           imgResize($modx, $fullPathNameNew, $fullPathNameNew, $maxWidth, $maxHeight, $quality);
        }

        // If file is a PDF
        if ($fileExtLow == '.pdf') {
           // Read the input PDF file
           $inputPDF = file_get_contents($fullPathNameNew);
           // Remove PDF metadata
           $outputPDF = preg_replace('/\/Info\s\d+\s\d+\sR/s', '/Info 0 R', $inputPDF);
           // Write the output PDF file
           file_put_contents($fullPathNameNew, $outputPDF);
        }

   break;
} }
