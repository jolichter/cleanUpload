<?php
/*
* V 25.01.022 - Optimiert
*
* cleanUpload is a MODX Revolution FileManager Plugin when uploading with Media Browser
* Clean up and optimize data, JPEG and PDF Metadata will be removed, GDPR compliant (DSGVO Konform)
*
* Testet with MODX 2.8.8 (PHP 8.2.27) and 3.1.0 (PHP 8.3.14)
* File name transliteration and customizing the picture size
* Same file names are NOT overwritten, instead a unique ID is appended to these files
* Two system events need to be enabled: OnFileManagerBeforeUpload, OnFileManagerUpload
*
* Since MODX 3: Transliterate (upload_translit) must be disabled in the settings for this (cleanUpload uses its own).
*  - Transliterate names of uploading files
*  - Type: Yes/No (default: Yes)
*  - if 'Yes', the name of any uploading file will be transliterated by global transliteration rules
*
* Reference and inspiration:
* https://www.php.net/manual/en/function.image-type-to-extension.php
* https://forums.modx.com/?action=thread&thread=73940&page=2
*/

// Einstellungen für die PDF-Verarbeitung
if (!defined('PDF_PROCESSING_WAIT')) {
    define('PDF_PROCESSING_WAIT', 5); // Maximale Wartezeit (Sekunden)
}
if (!defined('PDF_PROCESSING_ATTEMPTS')) {
    define('PDF_PROCESSING_ATTEMPTS', 3); // Maximale Versuche für PDF-Verarbeitung
}

// Settings
$maxWidth = 1280;    // Maximum pixel width | Maximale Pixelbreite
$maxHeight = 1280;   // Maximum pixel height | Maximale Pixelhöhe
$quality = 80;       // JPEG quality in % (default 80) | JPEG Qualität in % (Vorgabe 80)
$slug = '_';         // Replacement character | Ersetzungszeichen

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
        if (empty($filename)) {
            return false;
        }
        return $filename;
    }
}

// ###################################
// Resize JPEG function
if (!function_exists('imgResize')) {
    function imgResize($modx, $source, $target, $maxWidth, $maxHeight, $quality) {
        list($source_width, $source_height, $source_type) = getimagesize($source);
        $source_gd_image = match ($source_type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($source),
            IMAGETYPE_GIF => imagecreatefromgif($source),
            IMAGETYPE_PNG => imagecreatefrompng($source),
            default => false,
        };

        if ($source_gd_image === false) {
            return false;
        }

        $source_aspect_ratio = $source_width / $source_height;
        $aspect_ratio = $maxWidth / $maxHeight;

        [$image_width, $image_height] = ($source_width <= $maxWidth && $source_height <= $maxHeight)
            ? [$source_width, $source_height]
            : ($aspect_ratio > $source_aspect_ratio
                ? [(int) ($maxHeight * $source_aspect_ratio), $maxHeight]
                : [$maxWidth, (int) ($maxWidth / $source_aspect_ratio)]);

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
// Resize images and process PDFs
foreach ($files as $file) {
    try {
        if ($file['error'] != 0) {
            throw new Exception('[cleanUpload] Error during upload: ' . $file['error']);
        }

        $dir = $directory;
        $fileDir = $directory . $file['name'];
        $bases = $source->getBases($directory);
        $fullPath = $bases['pathAbsolute'] . ltrim($directory, '/');
        $pathInfo = pathinfo($file['name']);
        $fileName = $pathInfo['filename'];
        $fileNameNew = cleanFilename($modx, $fileName, $slug);
        $fileExt = '.' . $pathInfo['extension'];
        $fileExtLow = strtolower($fileExt);
        $fullNameNewLow = $fileNameNew . $fileExtLow;
        $fullPathNameNew = $fullPath . $fileNameNew . $fileExtLow;

        switch ($eventName) {
            case 'OnFileManagerBeforeUpload':
                if (file_exists($fullPathNameNew)) {
                    $uni = uniqid();
                    $fileTemp = $fileNameNew . '_' . $uni . $fileExtLow;
                    $source->renameObject($dir . $fullNameNewLow, $fileTemp);
                }
                break;

            case 'OnFileManagerUpload':
                if ($fileName != $fileNameNew) {
                    $source->renameObject($fileDir, $fullNameNewLow);
                } elseif ($fileExt != $fileExtLow) {
                    $source->renameObject($fileDir, $fullNameNewLow);
                }

                if ($fileExtLow == '.jpg' || $fileExtLow == '.jpeg') {
                    imgResize($modx, $fullPathNameNew, $fullPathNameNew, $maxWidth, $maxHeight, $quality);
                }

                if ($fileExtLow == '.pdf') {
                    for ($attempt = 0; $attempt < PDF_PROCESSING_ATTEMPTS; $attempt++) {
                        if (file_exists($fullPathNameNew)) {
                            break;
                        }
                        sleep(PDF_PROCESSING_WAIT);
                    }

                    if (!file_exists($fullPathNameNew)) {
                        throw new Exception('[cleanUpload] PDF not found after attempts.');
                    }

                    $inputPDF = $fullPathNameNew;
                    $outputPDF = $fullPathNameNew . '.tmp';
                    $tempPS = $fullPathNameNew . '.ps';

                    $command1 = "pdf2ps $inputPDF $tempPS";
                    $command2 = "ps2pdf -dPDFSETTINGS=/prepress $tempPS $outputPDF";

                    exec($command1, $output1, $return1);
                    exec($command2, $output2, $return2);

                    if ($return1 !== 0 || $return2 !== 0 || !file_exists($outputPDF)) {
                        throw new Exception('[cleanUpload] PDF processing failed.');
                    }

                    rename($outputPDF, $inputPDF);
                    unlink($tempPS);
                }
                break;
        }
    } catch (Exception $e) {
        $modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
    }
}
