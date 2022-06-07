<?php

/*
 * easyFiles PHP classes set
 * 
 * Copyright (c) 2022 easyFiles (https://github.com/foortec/easyfiles/)
 * MIT
 */

namespace foortec\easyFiles;
use finfo, Exception, XMLParser, GdImage, mysqli;

// MYSQLI database connection configuration
define("MYSQLI_HOSTNAME", "localhost");
define("MYSQLI_USERNAME", "root");
define("MYSQLI_PASSWORD", "");
define("MYSQLI_DATABASE", "easyfilesdb");
define("MYSQLI_PORT", ini_get("mysqli.default_port"));
define("MYSQLI_SOCKET", ini_get("mysqli.default_socket"));

trait checks
{
    private function fileIsImage(string $path) : bool
    {
        $info = new finfo();
        $type = explode("/", $info->file($path, FILEINFO_MIME_TYPE));
        if($type[0] == "image")
            return true;
        return false;
    }
}

class easyUpload
{
    private string $tmp;
    private string $filename;
    private string $extension;
    private string $basename;
    private int $size;
    private string $savePath;
    private bool $saved = false;

    public bool $error = false;
    public ?string $errorMessage = NULL;
    const invalidExtMsg = "The file extension is not allowed.";
    const toBigMsg = "The file is to big.";
    const toSmallMsg = "The file is to small.";
    const notDirectory = "The path does not lead to a directory.";
    const fileToUploadError = "Given file input name is incorrect.";

    const default_extensions = array("png", "jpg", "jpeg", "gif", "webp", "avif", "bmp", "wbmp", "xbm", "doc", "docx", "docm", "txt", "pdf", "htm", "html", "xml", "php", "ppt", "pptx", "json", "csv", "xls", "xlsx", "odt", "ods", "odp", "odg");
    private array $extensions;
    private int $minSize;
    private int $maxSize;

    public function __construct(string $fileToUpload, string $savePath, ?string $save_as = NULL, int $minSize = 0, int $maxSize = 100000000, array $allowedExtensions = self::default_extensions)
    {
        $save_as = preg_replace(";^\.;", "", htmlentities($save_as));
        $this->savePath = preg_replace(";\/$;", "", htmlentities($savePath));
        $this->tmp = htmlentities($_FILES[$fileToUpload]["tmp_name"]);
        $this->minSize = $minSize;
        $this->maxSize = $maxSize;
        $this->size = filesize($this->tmp);
        $this->extensions = $allowedExtensions;
        $this->extension = strtolower(pathinfo(basename($_FILES[$fileToUpload]["name"]), PATHINFO_EXTENSION));

        $this->checkRequirements();
        if($this->error)
            return;
        if($save_as!=NULL)
        {
            $this->filename = $this->randomFilename(null, $save_as);
            $this->basename = $this->filename . "." . $this->extension;
            return;
        }
        $this->filename = $this->randomFilename();
        $this->basename = $this->filename . "." . $this->extension;
    }

    public function save() : bool
    {
        if($this->saved || $this->error)
            return false;
        $this->saved = move_uploaded_file($this->tmp, $this->savePath . "/" . $this->basename);
        return $this->saved;
    }

    private function checkRequirements() : void
    {
        if($this->saved || $this->error)
            return;

        if($this->tmp == "")
        {
            $this->error = true;
            $this->errorMessage = self::fileToUploadError;
            return;
        }

        if(!is_dir($this->savePath))
        {
            $this->error = true;
            $this->errorMessage = self::notDirectory;
            return;
        }
        
        if($this->size < $this->minSize)
        {
            $this->error = true;
            $this->errorMessage = self::toSmallMsg;
            return;
        }
        if($this->size > $this->maxSize)
        {
            $this->error = true;
            $this->errorMessage = self::toBigMsg;
            return;
        }

        $extensionOK = false;
        for($i=0; $i<count($this->extensions); $i++)
        {
            if($this->extensions[$i]==$this->extension)
            {
                $extensionOK = true;
                break;
            }
        }
        if(!$extensionOK)
        {
            $this->error = true;
            $this->errorMessage = self::invalidExtMsg;
            return;
        }
        unset($extensionOK);
    }

    private function randomFilename(?string $prefix = "file", ?string $name = null) : string
    {
        if($prefix != null || $name == null)
            $filename = $prefix . time();
        else
            $filename = $name;

        $files = array_diff(scandir($this->savePath), array(".", ".."));
        do
        {
            $repetitionOK = true;
            foreach($files as $file)
            {
                if(pathinfo($this->savePath . "/" . $file, PATHINFO_FILENAME)==$filename)
                {
                    $repetitionOK = false;
                    $filename .= (string)rand(10, 999);
                }
            }
        }while(!$repetitionOK);
        
        return $filename;
    }

    public function getFullPath() : string|bool
    {
        if($this->saved)
            return $this->savePath . "/" . $this->basename;
        return false;
    }

    public function getIMG() : easyIMG
    {
        return new easyIMG($this->getFullPath());
    }

    public function getDoc() : easyDoc
    {
        return new easyDoc($this->getFullPath());
    }

    public function getType() : string
    {
        if(!$this->saved)
            return "";
        $info = new finfo();
        $type = explode("/", $info->file($this->getFullPath(), FILEINFO_MIME_TYPE));
        if(strtolower($type[0]) != "image")
            return "doc";
        return "img";
    }
}

class easyIMG
{
    use checks;

    private string $path;

    public bool $error = false;
    public ?string $errorMessage = NULL;
    const notImage = "The file is not an image.";
    const noFile = "No such file, path invalid, or permission denied.";

    public function __construct(string $path)
    {
        $this->path = htmlentities($path);
        
        if(!file_exists($this->path))
        {
            $this->error = true;
            $this->errorMessage = self::noFile;
            return;
        }

        if(!$this->fileIsImage($this->path))
        {
            $this->error = true;
            $this->errorMessage = self::notImage;
        }
    }

    public function display(?string $id=NULL, ?string $class=NULL, ?string $alt=NULL) : void
    {
        if($this->error)
            return;
        echo '<img id="' . $id . '" class="' . $class . '" src="' . $this->path . '" alt="' . $alt . '">';
    }

    public function getFullPath() : string
    {
        return $this->path;
    }

    public function getThumb(string $thumbnailsFolder, ?int $width = 100, ?int $height = null) : easyThumb
    {
        return new easyThumb($thumbnailsFolder, $this->path, $width, $height);
    }
}

class easyThumb
{
    use checks;

    private string $pathIMG;
    private string $pathThumb;
    private GdImage $handle;
    private string $extension;
    private string $filename;
    private ?int $width;
    private ?int $height;

    const prefix = "thumb-";
    private bool $saved = false;
    private bool $thumbMade = false;

    public bool $error = false;
    public ?string $errorMessage = NULL;
    const notImage = "The file is not an image.";
    const noFile = "No such file, path invalid, or permission denied.";
    const autoDimensions = "Maximally one dimension can be NULL.";
    const notDirectory = "The path does not lead to a directory.";

    public function __construct(string $pathThumb, string $pathIMG, ?int $width = 100, ?int $height = null)
    {
        $this->pathIMG = htmlentities($pathIMG);
        $this->pathThumb = preg_replace(";\/$;", "", htmlentities($pathThumb));

        if(!is_dir($this->pathThumb))
        {
            $this->error = true;
            $this->errorMessage = self::notDirectory;
            return;
        }

        $this->extension = strtolower(pathinfo($this->pathIMG, PATHINFO_EXTENSION));
        
        $this->filename = self::prefix . pathinfo($this->pathIMG, PATHINFO_FILENAME);
        
        $this->pathThumb = $this->pathThumb . "/" . $this->filename . "." . $this->extension;
        
        $this->width = $width;
        $this->height = $height;

        if($this->width == null && $this->height == null)
        {
            $this->error = true;
            $this->errorMessage = self::autoDimensions;
            return;
        }

        if(!file_exists($this->pathIMG))
        {
            $this->error = true;
            $this->errorMessage = self::noFile;
            return;
        }

        if(!$this->fileIsImage($this->pathIMG))
        {
            $this->error = true;
            $this->errorMessage = self::notImage;
            return;
        }

        $this->makeThumb();
    }

    private function calcDimensions(int $widthIMG, int $heightIMG) : void
    {
        if($this->width != null && $this->height != null)
            return;

        if($this->width != null)
        {
            $this->height = round(($this->width/$widthIMG) * $heightIMG);
            return;
        }
        if($this->height != null)
        {
            $this->width = round(($this->height/$heightIMG) * $widthIMG);
        }
    }

    private function makeThumb() : void
    {
        if($this->extension == "jpg")
            $imagecreate = "imagecreatefromjpeg";
        else
            $imagecreate = "imagecreatefrom" . $this->extension;

        list($widthIMG, $heightIMG) = getimagesize($this->pathIMG);
        $this->calcDimensions($widthIMG, $heightIMG);

        $image = $imagecreate($this->pathIMG);
        $this->handle = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($this->handle, $image, 0, 0, 0, 0, $this->width, $this->height, (int)$widthIMG, (int)$heightIMG);
        imagedestroy($image);

        $this->thumbMade = true;
    }

    public function getFullPath() : string
    {
        return $this->pathThumb;
    }

    public function display(?string $id=NULL, ?string $class=NULL, ?string $alt=NULL) : void
    {
        if(!$this->thumbMade)
            return;

        if($this->saved)
        {
            echo '<img id="' . $id . '" class="' . $class . '" src="' . $this->pathThumb . '" alt="' . $alt . '">';
            return;
        }

        ob_start();
		imagepng($this->handle, NULL);
		$rawImageStream = ob_get_clean();
		echo '<img id="' . $id . '" class="' . $class . '" src="data:image/png;base64,' . base64_encode($rawImageStream) . '" alt="' . $alt . '">';
    }

    public function save() : bool
    {
        if(!$this->thumbMade)
            return false;
        
        $imageExt = "image" . $this->extension;
        $returnValue = $imageExt($this->handle, $this->pathThumb);
        imagedestroy($this->handle);

        $this->saved = true;
        return $returnValue;
    }

    public function __destruct()
    {
        if($this->thumbMade && !$this->saved)
            imagedestroy($this->handle);
    }
}

class easyDoc
{
    use checks;

    private string $path;
    private string $extension;

    public bool $error = false;
    public ?string $errorMessage = NULL;
    const noFile = "No such file.";
    const fileIsImage = "The file is an image.";
    const isDirectory = "The file is a directory.";

    public function __construct(string $path)
    {
        $this->path = htmlentities($path);
        $this->extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        if(!file_exists($this->path))
        {
            $this->error = true;
            $this->errorMessage = self::noFile;
            return;
        }

        if(is_dir($this->path))
        {
            $this->error = true;
            $this->errorMessage = self::isDirectory;
            return;
        }

        if($this->fileIsImage($this->path))
        {
            $this->error = true;
            $this->errorMessage = self::fileIsImage;
            return;
        }
    }

    public function displayRaw() : void
    {
        if($this->error)
            return;

        echo "<pre>";
        if($this->extension == "xml")
        {
            $this->displayRawXML();
            return;
        }
        
        if($this->extension == "json")
        {
            $this->displayRawJSON();
            return;
        }
        
        if($this->extension == "csv")
        {
            $this->displayRawCSV();
            return;
        }
        
        $this->displayRawTXT();
        echo "</pre>";
    }

    private function displayrawCSV() : void
    {
        $csv = fopen($this->path, "r") or throw new Exception("Unable to open the file.");
        while($line = fgetcsv($csv))
        {
            foreach($line as $cell)
            {
                if($cell == "")
                    echo "-", "  ";
                else
                    echo $cell . "  ";
            }
            echo "<br/>";
        }
        fclose($csv);
    }

    private function displayRawJSON() : void
    {
        $file = fopen($this->path, "r") or throw new Exception("Unable to open the file.");
        $json = fread($file, filesize($this->path));
        fclose($file);
        $array = json_decode($json, true);
        foreach($array as $key => $array)
        {
            foreach($array as $key => $value)
                echo $key . " => " . $value . "<br/>";
            echo "<br/>";
        }
    }

    private function displayRawTXT() : void
    {
        $txtHandle = fopen($this->path, "r") or throw new Exception("Unable to open the file.");
        $txtArray = explode("/r/n", fread($txtHandle, filesize($this->path)));
        fclose($txtHandle);
        foreach($txtArray as $line)
            echo $line . "<br/>";
    }

    private function xmlStart(XMLParser $parser, string $elemName, array $elemAttrs) : void
    {
        echo $elemName;
        if(!empty($elemAttrs))
        {
            echo " ( ";
            foreach($elemAttrs as $attr => $value)
                echo $attr . "='" . $value . "' ";
            echo ")";
            return;
        }
        echo "<br/>";
    }

    private function xmlEnd(XMLParser $parser, string $elemName) : void
    {
        echo "<br/>";
    }

    private function displayRawXML() : void
    {
        $parser = xml_parser_create();
        xml_set_element_handler($parser, [$this, "xmlStart"], [$this, "xmlEnd"]);

        $file = fopen($this->path, "r") or throw new Exception("The file is missing or permission denied.");
        while(!feof($file))
        {
            $line = fgets($file);
            xml_parse($parser, $line) or throw new Exception("XML parser error string: " . xml_error_string(xml_get_error_code($parser)) . " at line " . xml_get_current_line_number($parser));
        }
        fclose($file);
        xml_parser_free($parser);
    }

    public function getMigrate(string $tableName, string $hostname = MYSQLI_HOSTNAME, string $username = MYSQLI_USERNAME, string $password = MYSQLI_PASSWORD, string $database = MYSQLI_DATABASE, string $port = MYSQLI_PORT, string $socket = MYSQLI_SOCKET) : easyMigrate
    {
        return new easyMigrate($this->path, $tableName, $hostname, $username, $password, $database, $port, $socket);
    }

    public function getFullPath() : string
    {
        return $this->path;
    }
}

class easyMigrate
{
    use checks;

    private string $path;
    private string $tableName;
    private string $extension;

    private array $xmlTableColumns;
    private int $xmlTableColumnsIterator = 0;
    private array $xmlTableRows;
    private int $xmlTableRowsIterator = 0;

    // mysqli conn
    private string $host;
    private string $user;
    private string $pass;
    private string $db;
    private string $port;
    private string $socket;

    private string $operation;
    private bool $tableExists;

    public bool $error = false;
    public ?string $errorMessage = NULL;
    const tableExists = "The table already exists.";
    const fileIsImage = "The file is an image.";
    const wrongPath = "The file does not exist.";
    const noFileAndNoTable = "There is no file and no database table.";
    const everythingExists = "Both database table and file exist. Conflict."; // make file appending in the future!
    const importImpossible = "Import operation is impossible with this set of input data.";
    const exportImpossible = "Export operation is impossible with this set of input data.";

    public function __construct(string $path, string $tableName, string $hostname = MYSQLI_HOSTNAME, string $username = MYSQLI_USERNAME, string $password = MYSQLI_PASSWORD, string $database = MYSQLI_DATABASE, string $port = MYSQLI_PORT, string $socket = MYSQLI_SOCKET)
    {
        $this->path = htmlentities($path);
        $this->tableName = htmlentities($tableName);
        
        $this->host = htmlentities($hostname);
        $this->user = htmlentities($username);
        $this->pass = htmlentities($password);
        $this->db = htmlentities($database);
        $this->port = htmlentities($port);
        $this->socket = htmlentities($socket);

        if(!file_exists($this->path))
        {
            $this->error = true;
            $this->errorMessage = self::wrongPath;
            return;
        }

        $this->tableExists = $this->tableExists();

        if(is_dir($this->path))
        {
            if($this->tableExists)
            {
                $this->operation = "export";
                return;
            }

            $this->error = true;
            $this->errorMessage = self::noFileAndNoTable;
            return;
        }

        if($this->tableExists)
        {
            $this->error = true;
            $this->errorMessage = self::everythingExists;
            return;
        }

        $this->operation = "import";
        $this->extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    private function tableExists() : bool
    {
        $checkQuery = 'SHOW TABLES LIKE "' . $this->tableName . '";';
        
        $mysqli = new mysqli(MYSQLI_HOSTNAME, MYSQLI_USERNAME, MYSQLI_PASSWORD, MYSQLI_DATABASE, MYSQLI_PORT, MYSQLI_SOCKET);
        $result = $mysqli->query($checkQuery);
        $mysqli->close();

        if($result->num_rows !== 0)
            return true;
        return false;
    }

    public function import(string $delimiter = ",") : void
    {
        if($this->error)
            return;

        if($this->operation != "import")
        {
            $this->error = true;
            $this->errorMessage = self::importImpossible;
            return;
        }

        if($this->extension == "xml")
        {
            $this->importXML();
            return;
        }
        
        if($this->extension == "json")
        {
            $this->importJSON();
            return;
        }
        
        if($this->extension == "csv")
        {
            $this->importCSV($delimiter);
            return;
        }
        
        $this->importTXT($delimiter);
    }

    private function importXML() : void
    {
        $parser = xml_parser_create();
        xml_set_element_handler($parser, [$this, "xmlStart"], [$this, "xmlEnd"]);

        $file = fopen($this->path, "r") or throw new Exception("The file is missing or permission denied.");
        while(!feof($file))
        {
            $line = fgets($file);
            xml_parse($parser, $line) or throw new Exception("XML parser error string: " . xml_error_string(xml_get_error_code($parser)) . " at line " . xml_get_current_line_number($parser));
        }
        fclose($file);
        xml_parser_free($parser);

        $queryTable = $this->makeCreateTableQuery($this->xmlTableColumns);
        $insertQueries = $this->makeInsertQueries($this->xmlTableColumns, $this->xmlTableRows);

        $this->mysqliImport($queryTable, $insertQueries);
    }

    private function xmlStart(XMLParser $parser, string $elemName, array $elemAttrs) : void
    {
        if($elemName == "COL")
        {
            $this->xmlTableColumns[$this->xmlTableColumnsIterator] = $elemAttrs['NAME'];
            $this->xmlTableColumnsIterator++;
            return;
        }

        if($elemName == "ROW")
        {
            for($i=0; $i<count($elemAttrs); $i++)
                $this->xmlTableRows[$this->xmlTableRowsIterator][$i] = $elemAttrs[strtoupper($this->xmlTableColumns[$i])];
            $this->xmlTableRowsIterator++;
        }
    }

    private function xmlEnd(XMLParser $parser, string $elemName) : void
    {
        return;
    }

    private function importJSON() : void
    {
        $jsonHandle = fopen($this->path, "r") or throw new Exception("Unable to open the file.");
        $json = fread($jsonHandle, filesize($this->path));
        fclose($jsonHandle);

        $json = json_decode($json, true);
        $tableColumns = array_keys($json[0]);
        for($i=0; $i<count($json); $i++)
        {
            for($j=0; $j<count($json[$i]); $j++)
            {
                $tableRows[$i][$j] = $json[$i][$tableColumns[$j]];
            }
        }

        $queryTable = $this->makeCreateTableQuery($tableColumns);
        $insertQueries = $this->makeInsertQueries($tableColumns, $tableRows);

        $this->mysqliImport($queryTable, $insertQueries);
    }

    private function importCSV(string $delimiter) : void
    {
        $csvHandle = fopen($this->path, "r") or throw new Exception("Unable to open the file.");
        $rowIter = 0;
        $tableColumn = array();
        while($row = fgetcsv($csvHandle, null, $delimiter))
        {
            if($rowIter == 0)
            {
                $tableColumn = $row;
                $rowIter++;
                continue;
            }

            for($i=0; $i<count($row); $i++)
            {
                $tableRow[$rowIter][$i] = $row[$i];
            }
            $rowIter++;
        }
        fclose($csvHandle);
        
        $queryTable = $this->makeCreateTableQuery($tableColumn);
        $insertQueries = $this->makeInsertQueries($tableColumn, $tableRow);

        $this->mysqliImport($queryTable, $insertQueries);
    }

    private function importTXT(string $delimiter) : void
    {
        $txtHandle = fopen($this->path, "r") or throw new Exception("Unable to open the file.");
        $txt = fread($txtHandle, filesize($this->path));
        $txt = str_replace("\n\r", "\n", $txt);
        $txtArray = explode("\n", $txt);
        fclose($txtHandle);

        $tableColumn = explode($delimiter, $txtArray[0]);
        for($i=1; $i<count($txtArray); $i++)
            $tableRow[$i-1] = explode($delimiter, $txtArray[$i]);

        $queryTable = $this->makeCreateTableQuery($tableColumn);
        $insertQueries = $this->makeInsertQueries($tableColumn, $tableRow);

        $this->mysqliImport($queryTable, $insertQueries);
    }

    private function makeCreateTableQuery(array $tableColumns) : string
    {
        $query = "CREATE TABLE `" . $this->tableName . "` (";
        for($i=0; $i<count($tableColumns); $i++)
        {
            if(strtolower($tableColumns[$i]) == "id")
                $columnDatatype[$i] = "INT PRIMARY KEY";
            else
                $columnDatatype[$i] = "TEXT";

            $query .= "`" . $tableColumns[$i] . "` " . $columnDatatype[$i];

            if($i < count($tableColumns)-1)
                $query .= ", ";
        }
        $query .= ");";
        return $query;
    }

    private function makeInsertQueries(array $tableColumns, array $tableRows) : string
    {
        $tableColumns = array_values($tableColumns);
        $tableRows = array_values($tableRows);

        $queryFirstPart = "INSERT INTO `" . $this->tableName . "`(";
        for($i=0; $i<count($tableColumns); $i++)
        {
            $queryFirstPart .= "`" . $tableColumns[$i] . "`";
            if($i < count($tableColumns)-1)
                $queryFirstPart .= ", ";
        }
        $queryFirstPart .= ") VALUES (";
        for($i=0; $i<count($tableRows); $i++)
        {
            $queryInsert[$i] = $queryFirstPart;
            for($j=0; $j<count($tableRows[$i]); $j++)
            {
                $queryInsert[$i] .= "'" . $tableRows[$i][$j] . "'";
                if($j < count($tableRows[$i])-1)
                    $queryInsert[$i] .= ", ";
            }
            $queryInsert[$i] .= ");";
        }

        $multiQuery = "";
        for($i=0; $i<count($queryInsert); $i++)
            $multiQuery .= $queryInsert[$i];
        
        return $multiQuery;
    }

    private function mysqliImport(string $createTableQuery, string $insertQueries) : void
    {
        $mysqli = new mysqli(MYSQLI_HOSTNAME, MYSQLI_USERNAME, MYSQLI_PASSWORD, MYSQLI_DATABASE, MYSQLI_PORT, MYSQLI_SOCKET);
        $mysqli->query($createTableQuery);
        $mysqli->multi_query($insertQueries);
        $mysqli->close();
    }

    public function export(string $extension = "txt", string $delimiter = ",") : easyDoc
    {
        if($this->operation != "export")
        {
            $this->error = true;
            $this->errorMessage = self::exportImpossible;
            return false;
        }

        if($extension == "xml")
            return $this->exportXML();
        
        if($extension == "json")
            return $this->exportJSON();
        
        if($extension == "csv")
            return $this->exportCSV($delimiter);
        
        return $this->exportTXT($delimiter);
    }

    private function exportXML() : easyDoc
    {
        $tableColumns = $this->getTableColumns();
        $tableRows = $this->getTableRows();

        $this->path = $this->path . "/" . $this->tableName . ".xml";
        $xmlHandle = fopen($this->path, "w") or throw new Exception("Unable to create the file.");
        fwrite($xmlHandle, '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL);
        fwrite($xmlHandle, '<table>'.PHP_EOL);

        fwrite($xmlHandle, '<columns>'.PHP_EOL);
        for($i=0; $i<count($tableColumns); $i++)
        {
            fwrite($xmlHandle, '<col name="' . $tableColumns[$i] . '"/>');
        }
        fwrite($xmlHandle, '</columns>'.PHP_EOL);

        fwrite($xmlHandle, '<rows>'.PHP_EOL);
        for($i=0; $i<count($tableRows); $i++)
        {
            fwrite($xmlHandle, '<row ');
            for($j=0; $j<count($tableRows[$i]); $j++)
            {
                fwrite($xmlHandle, $tableColumns[$j] . '="' . $tableRows[$i][$j] . '" ');
            }
            fwrite($xmlHandle, '/>'.PHP_EOL);
        }
        fwrite($xmlHandle, '</rows>'.PHP_EOL);

        fwrite($xmlHandle, '</table>');
        fclose($xmlHandle);
        return new easyDoc($this->path);
    }

    private function exportJSON() : easyDoc
    {
        $tableColumns = $this->getTableColumns();
        $tableRows = $this->getTableRows();
        $this->path = $this->path . "/" . $this->tableName . ".json";

        for($i=0; $i<count($tableRows); $i++)
            for($j=0; $j<count($tableColumns); $j++)
                $array[$i][$tableColumns[$j]] = $tableRows[$i][$j];

        $json = json_encode($array);
        $jsonHandle = fopen($this->path, "w") or throw new Exception("Unable to create the file.");
        fwrite($jsonHandle, $json);
        fclose($jsonHandle);
        return new easyDoc($this->path);
    }

    private function exportCSV(string $delimiter) : easyDoc
    {
        $tableColumns = $this->getTableColumns();
        $tableRows = $this->getTableRows();
        $this->path = $this->path . "/" . $this->tableName . ".csv";

        $csvHandle = fopen($this->path, "w") or throw new Exception("Unable to create the file.");
        fputcsv($csvHandle, $tableColumns, $delimiter);
        foreach($tableRows as $row)
            fputcsv($csvHandle, $row, $delimiter);
        fclose($csvHandle);
        return new easyDoc($this->path);
    }

    private function exportTXT(string $delimiter) : easyDoc
    {
        $tableColumns = $this->getTableColumns();
        $tableRows = $this->getTableRows();

        $this->path = $this->path . "/" . $this->tableName . ".txt";
        $txtHandle = fopen($this->path, "w") or throw new Exception("Unable to create the file.");

        for($i=0; $i<count($tableColumns); $i++)
        {
            fwrite($txtHandle, $tableColumns[$i]);
            if($i < (count($tableColumns)-1))
                fwrite($txtHandle, $delimiter);
        }
        fwrite($txtHandle, PHP_EOL);

        for($i=0; $i<count($tableRows); $i++)
        {
            for($j=0; $j<count($tableRows[$i]); $j++)
            {
                fwrite($txtHandle, $tableRows[$i][$j]);
                if($j < (count($tableRows[$i])-1))
                    fwrite($txtHandle, $delimiter);
            }
            if($i < (count($tableRows)-1))
                fwrite($txtHandle, PHP_EOL);
        }

        fclose($txtHandle);
        return new easyDoc($this->path);
    }

    private function getTableColumns() : array
    {
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $this->db . "' AND TABLE_NAME = '" . $this->tableName . "';";
        
        $conn = new mysqli(MYSQLI_HOSTNAME, MYSQLI_USERNAME, MYSQLI_PASSWORD, MYSQLI_DATABASE, MYSQLI_PORT, MYSQLI_SOCKET);
        $result = $conn->query($query);
        $conn->close();
        $array = $result->fetch_all();
        for($i=0; $i<count($array); $i++)
        {
            $columns[$i] = $array[$i][0];
        }
        return $columns;
    }

    private function getTableRows() : array
    {
        $query = "SELECT * FROM `" . $this->tableName . "`;";

        $conn = new mysqli(MYSQLI_HOSTNAME, MYSQLI_USERNAME, MYSQLI_PASSWORD, MYSQLI_DATABASE, MYSQLI_PORT, MYSQLI_SOCKET);
        $result = $conn->query($query);
        $conn->close();
        return $result->fetch_all();
    }
}
