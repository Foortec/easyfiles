# easyFiles
A set of classes designed to help you upload images and documents, display them, create thumbnails (from images), import / export documents to / from database. More features soon (like watermarks and more customization options).

## Classes and their public methods

### easyUpload
<br/><br/>
#### __construct
<br/>**Description**

Creates a new instance of the **easyUpload** class.

```php
public easyUpload::__construct(
  string $fileToUpload,
  string $savePath,
  ?string $save_as = NULL,
  int $minSize = 0,
  int $maxSize = 100000000,
  array $allowedExtensions = self::default_extensions
)
```
<br/>**Parameters**

*fileToUpload*<br/>
A string containing name of the file input from the form.<br/><br/>

*savePath*<br/>
A string containing path to the directory where the file is supposed to be saved.<br/><br/>

*save_as*<br/>
A string containing the name (without the extension) of the file to be saved as. If NULL, the name will be "randomized".<br/><br/>

*minSize*<br/>
The minimum size of the file (in bytes).<br/><br/>

*maxSize*<br/>
The maximum size of the file (in bytes).<br/><br/>

*allowedExtensions*<br/>
An array of the allowed extension. By default it contains constant default_extensions array.

```php
// default allowed extensions
const default_extensions = array(
  "png",
  "jpg",
  "jpeg",
  "gif",
  "webp",
  "avif",
  "bmp",
  "wbmp",
  "xbm",
  "doc",
  "docx",
  "docm",
  "txt",
  "pdf",
  "htm",
  "html",
  "xml",
  "php",
  "ppt",
  "pptx",
  "json",
  "csv",
  "xls",
  "xlsx",
  "odt",
  "ods",
  "odp",
  "odg"
);
```

<br/>**Examples**<br/><br/>
Example #1: Creating instance of the easyUpload class (+ errors handling)<br/>
<br/>
*index.html*

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Example</title>
  </head>
  <body>
    <form action="upload.php" method="post" enctype="multipart/form-data">
      <input type="file" name="upload">
      <input type="submit" name="submit" value="Send">
    </form>
  </body>
</html>
```
<br/><br/>
*upload.php*

```php
<?php
if(!isset($_POST["submit"]));
  header("Location: index.html");

require "easyfiles.php";
use foortec\easyFiles\easyUpload, Throwable;

try
{
  $upload = new easyUpload("upload", "uploaded");
  
  echo $upload->error? $upload->errorMessage : "An instance of the object created successfully.";
}
catch(Throwable $t)
{
  echo $t->getCode() . ". " . $t->getMessage();
}
```
<br/><br/>
#### save
<br/>**Description**

Saves the file in the desired directory.

```php
public function save() : bool
```
<br/>**Return values**

Returns **true** on success and **false** on failure. **False** can also mean that an error occurred in the constructor, the file is already saved or permission denied.

<br/>**Examples**<br/><br/>
Example #1: Saving the file (+ errors handling)<br/>
<br/>
*index.html*

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Example</title>
  </head>
  <body>
    <form action="upload.php" method="post" enctype="multipart/form-data">
      <input type="file" name="upload">
      <input type="submit" name="submit" value="Send">
    </form>
  </body>
</html>
```
<br/><br/>
*upload.php*

```php
<?php
if(!isset($_POST["submit"]));
  header("Location: index.html");

require "easyfiles.php";
use foortec\easyFiles\easyUpload, Throwable;

try
{
  $upload = new easyUpload("upload", "uploaded");
  
  echo $upload->error? $upload->errorMessage : "An instance of the object created successfully.";
  echo '<br/>';
  echo $upload->save()? "Saved successfully." : "Failed to save.";
}
catch(Throwable $t)
{
  echo $t->getCode() . ". " . $t->getMessage();
}
```

<br/><br/>
#### getFullPath

<br/><br/>
#### getIMG

<br/><br/>
#### getDoc

<br/><br/>
#### getType

### easyIMG
#### __construct
#### display
#### getFullPath
#### getThumb

### easyThumb
#### __construct
#### getFullPath
#### display
#### save
#### __destruct

### easyDoc
#### __construct
#### displayRaw
#### getMigrate
#### getFullPath

### easyMigrate
#### __construct
#### import
#### export

## Errors
