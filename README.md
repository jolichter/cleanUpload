# cleanUpload is a MODX Revolution FileManager Plugin
- Testet with MODX 2.8.5 (PHP 7.4.x) and 3.0.3 (PHP 8.1.x)
- Testet with MODX 2.8.8 (PHP 8.2.27) and 3.1.0 (PHP 8.3.14)
- Two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload

Features:
- File name transliteration and customizing the picture size
- JPG and PDF Metadata will be removed
- Same file names are NOT overwritten, instead a uniq ID is appended to these files
- Two system events need to be enabled: Two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload
- Since MODX 3: Transliterate must be disabled in the settings for this (cleanUpload uses its own).
- Transliterate names of uploaded files: No


:-)
