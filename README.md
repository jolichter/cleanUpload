# cleanUpload
- need MODX Revolution >= 2.3.0
- cleanUpload is a Plugin-Snippet for the File Manager
- two system events must be activated: OnFileManagerBeforeUpload, OnFileManagerUpload

Features:
- Characters in file names that are not available in the target character set, are replaced by similar characters (transliteration)
- Zeichen im Dateinamen welche im Ziel-Zeichensatz nicht zur Verfügung stehen, werden durch ähnliche Zeichen ersetzt (Transliteration)
- Optimizes the maximum size of jpeg-images by GD2
- Optimiert die maximale Größe von jpeg-Bildern per GD2
- Same file names are NOT overwritten -> instead of this, a uniq ID is appended to these files
- Gleiche Dateinamen werden NICHT überschrieben -> stattdessen wird eine eindeutige ID angehangen



:-)
