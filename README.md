# concrete5 Fileset Migration
Migrate concrete 5.6 file sets and files to 5.8

The concrete5 migration tools (https://github.com/concrete5/addon_migration_tool) do not handle file sets. This code will transfer the file sets across to concrete5 version 8. It maintains the database ID used in concrete5.6, thus any blocks you have that may have a file set ID will continue to function and will be pointed at the correct file set.

In this repo, there are two files, a controller for concrete5 version 8 and a tool for concrete5 version 6.

Copy the files tools/fileset.php to the tools folder of your concrete5 version 6 install, clear the cache, and then go to /index.php/tools/required/fileset

This should download a json file with details of all your public file sets and their files.

On your concrete5 version 8 system, copy the application/controllers/fileset.php to the corresponding folder.

Update your application/config/app.php with the required route:

return array(
	'routes' => array(
		"/ccm/fileset" => array('\Application\Controller\Fileset::view'),
	),
);

Upload the json file to the root folder of your concrete5 install.

Backup your database and files.

Backup your database and files.

Backup your database and files.

On your concrete5 version 8 system, point your browser at /index.php/ccm/fileset. Grab a beer. Depending on your number of file sets, they should import along with the files they containt. If a file is in two sets, it will get loaded twice. Nobody's perfect.

In the file manager, folders are created that correspond to the file sets. This is purely for convenience.

I experienced a memory leak of quite severe proportions when running the import process, so to get around this I only import the first few file sets and files before redirecting the browser back to the original URL, but with an offset in the $_GET string. This  starts a new PHP process and frees the memory.
