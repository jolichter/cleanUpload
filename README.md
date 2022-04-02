# cleanUpload is a MODX Revolution FileManager Plugin
- testet with MODX 2.3.1, 2.5.6, 2.8.3 (PHP 7.4.x) and 3.0.0 (PHP 8.1.x)
- two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload

Features:
- File name transliteration and customizing the JPG image size (JPG file info will be removed)
- Same file names are NOT overwritten! Instead, a uniq ID is appended to these files.
- Two system events need to be enabled: Two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload
- Since MODX 3: Transliterate must be disabled in the settings for this (cleanUpload uses its own).
- Transliterate names of uploaded files: No


:-)
