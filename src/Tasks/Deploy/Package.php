<?php

/**
 * @package    JoRobo
 *
 * @copyright  Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks\Deploy;

use Robo\Result;

/**
 * Deploy project as Package file
 *
 * @package  Joomla\Jorobo\Tasks\Deploy
 *
 * @since    1.0
 */
class Package extends Base
{
    /**
     * The target Zip file of the package
     *
     * @var    string
     *
     * @since  1.0
     */
    protected $target = null;

    private $hasComponents = true;

    private $hasModules = true;

    private $hasTemplates = true;

    private $hasPlugins = true;

    private $hasLibraries = true;

    protected $current;
    /**
     * Initialize Build Task
     *
     * @since   1.0
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        $this->target  = $this->params['base'] . "/dist/pkg-" . $this->getExtensionName() . "-" . $this->getJConfig()->version . ".zip";
        $this->current = $this->params['base'] . "/dist/current";
    }

    /**
     * Build the package
     *
     * @return  Result
     *
     * @since   1.0
     */
    public function run()
    {
        // TODO improve DRY!
        $this->printTaskInfo('Creating package ' . $this->getJConfig()->extension . " " . $this->getJConfig()->version);

        // Start getting single archives
        if (file_exists($this->params['base'] . '/dist/zips')) {
            $this->_deleteDir($this->params['base'] . '/dist/zips');
        }

        $this->_mkdir($this->params['base'] . '/dist/zips');
        $this->analyze();

        if ($this->hasComponents) {
            $this->createComponentZips();
        }

        if ($this->hasModules) {
            $this->createModuleZips();
        }

        if ($this->hasPlugins) {
            $this->createPluginZips();
        }

        if ($this->hasTemplates) {
            $this->createTemplateZips();
        }

        if ($this->hasLibraries) {
            $this->createLibraryZips();
        }

        $this->createPackageZip();

        // Create symlink to current folder
        if ($this->isWindows()) {
            if (is_file($this->params['base'] . "\dist\pkg-" . $this->getExtensionName() . "-current.zip")) {
                unlink($this->params['base'] . "\dist\pkg-" . $this->getExtensionName() . "-current.zip");
            }
            $this->taskExec('mklink /H "' . $this->params['base'] . "\dist\pkg-" . $this->getExtensionName() . "-current.zip" . '" "' . $this->getWindowsPath($this->target) . '"')
                ->run();
        } else {
            if (is_dir($this->params['base'] . "\dist\pkg-" . $this->getExtensionName() . "-current.zip")) {
                unlink($this->params['base'] . "/dist/pkg-" . $this->getExtensionName() . "-current.zip");
            }
            $this->taskFilesystemStack()
                ->symlink($this->target, $this->params['base'] . "/dist/pkg-" . $this->getExtensionName() . "-current.zip")
                ->run();
        }

        return Result::success($this);
    }

    /**
     * Check if local OS is Windows
     *
     * @return  boolean
     *
     * @since   3.7.3
     */
    private function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Return the correct path for Windows (needed by CMD)
     *
     * @param   string  $path  Linux path
     *
     * @return  string
     *
     * @since   3.7.3
     */
    private function getWindowsPath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Analyze the extension structure
     *
     * @return  void
     *
     * @since   1.0
     */
    private function analyze()
    {
        // Check if we have component, module, plugin etc.
        $folders = glob($this->getSourceFolder() . "/administrator/components/com_*", GLOB_ONLYDIR);

        if (count($folders) === 0) {
            $this->hasComponents = false;
        }

        if (!file_exists($this->current . "/modules")) {
            $this->hasModules = false;
        }

        if (!file_exists($this->current . "/plugins")) {
            $this->hasPlugins = false;
        }

        if (!file_exists($this->current . "/templates")) {
            $this->hasTemplates = false;
        }

        if (!file_exists($this->current . "/libraries")) {
            $this->hasLibraries = false;
        }
    }

    /**
     * Add files
     *
     * @param   \ZipArchive  $zip   The zip object
     * @param   string       $path  Optional path
     *
     * @return  void
     *
     * @since   1.0
     */
    private function addFiles($zip, $path = null)
    {
        if (!$path) {
            $path = $this->current;
        }

        $source = str_replace('\\', '/', realpath($path));

        if (is_dir($source) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                if (substr($file, 0, 1) == ".") {
                    continue;
                }

                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..'])) {
                    continue;
                }

                $file = str_replace('\\', '/', $file);

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } elseif (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } elseif (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
    }

    /**
     * Create an installable zip file for a component
     *
     * @return  void
     *
     * @since   1.0
     */
    public function createComponentZips()
    {
        $folders = glob($this->getSourceFolder() . "/administrator/components/com_*", GLOB_ONLYDIR);

        foreach ($folders as $folder) {
            $cname = basename($folder);
            $this->printTaskInfo("Packaging Component " . $cname);
            $comZip              = new \ZipArchive();
            $tmp_path            = '/dist/tmp/cbuild';
            $componentScriptPath = $this->current . "/administrator/components/" . $cname . "/script.php";

            // Delete old build directory and create new one
            if (file_exists($this->params['base'] . $tmp_path)) {
                $this->taskFilesystemStack()
                    ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                    ->remove($this->params['base'] . $tmp_path)
                    ->run();
            }

            $this->taskFilesystemStack()
                ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                ->mkdir($this->params['base'] . $tmp_path)
                ->run();

            // Copy code parts of component to build directory
            $this->taskFilesystemStack()
                ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                ->mirror($this->current . '/administrator/components/' . $cname, $this->params['base'] . $tmp_path . '/administrator/components/' . $cname)
                ->run();

            if (is_dir($this->current . '/components/' . $cname)) {
                $this->taskFilesystemStack()
                    ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                    ->mirror($this->current . '/components/' . $cname, $this->params['base'] . $tmp_path . '/components/' . $cname)
                    ->run();
            }

            if (file_exists($this->current . '/api/components/' . $cname)) {
                $this->taskFilesystemStack()
                    ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                    ->mirror($this->current . '/api/components/' . $cname, $this->params['base'] . $tmp_path . '/api/components/' . $cname)
                    ->run();
            }

            // Copy language files from front- and backend
            $backendLanguage = glob($this->getSourceFolder() . '/administrator/language/*/' . $cname . '.ini');

            if (count($backendLanguage) > 0) {
                foreach ($backendLanguage as $language) {
                    $lng = basename(dirname($language));

                    if (file_exists($this->getSourceFolder() . '/administrator/language/' . $lng . '/' . $cname . '.ini')) {
                        $this->taskFilesystemStack()
                            ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                            ->copy(
                                $this->getSourceFolder() . '/administrator/language/' . $lng . '/' . $cname . '.ini',
                                $this->params['base'] . $tmp_path . '/administrator/language/' . $lng . '/' . $cname . '.ini'
                            )
                            ->run();
                    }

                    if (file_exists($this->getSourceFolder() . '/administrator/language/' . $lng . '/' . $cname . '.sys.ini')) {
                        $this->taskFilesystemStack()
                            ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                            ->copy(
                                $this->getSourceFolder() . '/administrator/language/' . $lng . '/' . $cname . '.sys.ini',
                                $this->params['base'] . $tmp_path . '/administrator/language/' . $lng . '/' . $cname . '.sys.ini'
                            )
                            ->run();
                    }
                }
            }

            $frontendLanguage = glob($this->getSourceFolder() . '/language/*/' . $cname . '.ini');

            if (count($frontendLanguage) > 0) {
                foreach ($frontendLanguage as $language) {
                    $lng = basename(dirname($language));

                    if (file_exists($this->getSourceFolder() . '/language/' . $lng . '/' . $cname . '.ini')) {
                        $this->taskFilesystemStack()
                            ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                            ->copy(
                                $this->getSourceFolder() . '/language/' . $lng . '/' . $cname . '.ini',
                                $this->params['base'] . $tmp_path . '/language/' . $lng . '/' . $cname . '.ini'
                            )
                            ->run();
                    }
                }
            }

            // Copy media files
            if (file_exists($this->current . '/media/' . $cname)) {
                $this->taskFilesystemStack()
                    ->setVerbosityThreshold(self::VERBOSITY_VERY_VERBOSE)
                    ->mirror($this->current . '/media/' . $cname, $this->params['base'] . $tmp_path . '/media/' . $cname)
                    ->run();
            }

            $comZip->open($this->params['base'] . '/dist/zips/' . $cname . '.zip', \ZipArchive::CREATE);

            // Process the files to zip
            $this->addFiles($comZip, $this->params['base'] . $tmp_path);

            $comZip->addFile(
                $this->current . "/" . substr($cname, 4) . ".xml",
                substr($cname, 4) . ".xml"
            );

            if (file_exists($componentScriptPath)) {
                $comZip->addFile($componentScriptPath, "script.php");
            }

            // Close the zip archive
            $comZip->close();
        }
    }

    /**
     * Create zips for libraries
     *
     * @return  void
     *
     * @since   1.0
     */
    public function createLibraryZips()
    {
        $path = $this->current . "/libraries";

        // Get every module
        $hdl = opendir($path);

        while ($lib = readdir($hdl)) {
            // Only folders
            $p = $path . "/" . $lib;

            if (substr($lib, 0, 1) == '.') {
                continue;
            }

            // Workaround for libraries without lib_
            if (substr($lib, 0, 3) != "lib") {
                $lib = 'lib_' . $lib;
            }

            if (!is_file($p)) {
                $this->printTaskInfo("Packaging Library " . $lib);

                // Package file
                $zip = new \ZipArchive();

                $zip->open($this->params['base'] . '/dist/zips/' . $lib . '.zip', \ZipArchive::CREATE);

                $this->printTaskInfo("Library " . $p);

                // Process the files to zip
                $this->addFiles($zip, $p);

                // Close the zip archive
                $zip->close();
            }
        }

        closedir($hdl);
    }

    /**
     * Create zips for modules
     *
     * @return  void
     *
     * @since   1.0
     */
    public function createModuleZips()
    {
        $path = $this->current . "/modules";

        // Get every module
        $hdl = opendir($path);

        while ($entry = readdir($hdl)) {
            // Only folders
            $p = $path . "/" . $entry;

            if (substr($entry, 0, 1) == '.') {
                continue;
            }

            if (!is_file($p)) {
                $this->printTaskInfo("Packaging Module " . $entry);

                // Package file
                $zip = new \ZipArchive();

                $zip->open($this->params['base'] . '/dist/zips/' . $entry . '.zip', \ZipArchive::CREATE);

                $this->printTaskInfo("Module " . $p);

                // Process the files to zip
                $this->addFiles($zip, $p);

                // Close the zip archive
                $zip->close();
            }
        }

        closedir($hdl);
    }

    /**
     * Create zips for plugins
     *
     * @return  void
     *
     * @since   1.0
     */
    public function createPluginZips()
    {
        $path = $this->current . "/plugins";

        // Get every plugin
        $hdl = opendir($path);

        while ($entry = readdir($hdl)) {
            // Only folders
            $p = $path . "/" . $entry;

            if (substr($entry, 0, 1) == '.') {
                continue;
            }

            if (!is_file($p)) {
                // Plugin type folder
                $type = $entry;

                $hdl2 = opendir($p);

                while ($plugin = readdir($hdl2)) {
                    if (substr($plugin, 0, 1) == '.') {
                        continue;
                    }

                    // Only folders
                    $p2 = $path . "/" . $type . "/" . $plugin;

                    if (!is_file($p2)) {
                        $plg = "plg_" . $type . "_" . $plugin;

                        $this->printTaskInfo("Packaging Plugin " . $plg);

                        // Package file
                        $zip = new \ZipArchive();

                        $zip->open($this->params['base'] . '/dist/zips/' . $plg . '.zip', \ZipArchive::CREATE);

                        // Process the files to zip
                        $this->addFiles($zip, $p2);

                        // Close the zip archive
                        $zip->close();
                    }
                }

                closedir($hdl2);
            }
        }

        closedir($hdl);
    }

    /**
     * Create zips for templates
     *
     * @return  void
     *
     * @since   1.0
     */
    public function createTemplateZips()
    {
        $path = $this->current . "/templates";

        // Get every module
        $hdl = opendir($path);

        while ($entry = readdir($hdl)) {
            // Only folders
            $p = $path . "/" . $entry;

            if (substr($entry, 0, 1) == '.') {
                continue;
            }

            if (!is_file($p)) {
                $this->printTaskInfo("Packaging Template " . $entry);

                // Package file
                $zip = new \ZipArchive();

                $zip->open($this->params['base'] . '/dist/zips/tpl_' . $entry . '.zip', \ZipArchive::CREATE);

                $this->printTaskInfo("Template " . $p);

                // Process the files to zip
                $this->addFiles($zip, $p);

                // Close the zip archive
                $zip->close();
            }
        }

        closedir($hdl);
    }

    /**
     * Create package zip (called latest)
     *
     * @return  void
     *
     * @since   1.0
     */
    public function createPackageZip()
    {
        $zip = new \ZipArchive();

        // Instantiate the zip archive
        $zip->open($this->target, \ZipArchive::CREATE);

        // Process the files to zip
        $this->addFiles($zip, $this->params['base'] . '/dist/zips/');

        $pkg_path = $this->current . "/administrator/manifests/packages/pkg_" . $this->getExtensionName();

        $zip->addFile($pkg_path . ".xml", "pkg_" . $this->getExtensionName() . ".xml");

        if (is_file($this->current . "/administrator/manifests/packages/" . $this->getExtensionName() . "/script.php")) {
            $zip->addFile(
                $this->current . "/administrator/manifests/packages/" . $this->getExtensionName() . "/script.php",
                "script.php"
            );
        }

        // If the package has language files, add those
        $pkg_languages_path = $pkg_path . "/language";
        $languages          = glob($pkg_languages_path . "/*/*pkg_" . $this->getExtensionName() . "*.ini");

        // Add all package language files
        foreach ($languages as $lang_path) {
            $path_in_zip = substr($lang_path, strlen($pkg_path) + 1);
            $zip->addFile($lang_path, $path_in_zip);
        }

        // Close the zip archive
        $zip->close();
    }
}
