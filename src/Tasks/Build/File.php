<?php

/**
 * @package    JoRobo
 *
 * @copyright  Copyright (C) 2005 - 2023 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks\Build;

use Robo\Result;

/**
 * Class File
 *
 * @package  Joomla\Jorobo\Tasks\Build
 *
 * @since    1.0
 */
class File extends Base
{
    use Tasks;

    protected $adminPath = null;

    protected $apiPath = null;

    protected $frontPath = null;

    protected $hasAdmin = true;

    protected $hasApi = true;

    protected $hasFront = true;

    protected $hasMedia = false;

    /**
     * Initialize Build Task
     *
     * @param   array  $params  The target directory
     *
     * @since   1.0
     */
    public function __construct($params)
    {
        parent::__construct($params);

        // Reset files - > new component
        $this->resetFiles();

        $this->adminPath = $this->getSourceFolder() . "/administrator/components/com_" . $this->getExtensionName();
        $this->apiPath   = $this->getSourceFolder() . "/api/components/com_" . $this->getExtensionName();
        $this->frontPath = $this->getSourceFolder() . "/components/com_" . $this->getExtensionName();
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
        $this->say('Building component');

        // Analyze extension structure
        $this->analyze();

        // Prepare directories
        $this->prepareDirectories();

        if ($this->hasAdmin) {
            $adminFiles = $this->copyTarget($this->adminPath, $this->getBuildFolder() . "/administrator/components/com_" . $this->getExtensionName());

            $this->addFiles('backend', $adminFiles);
        }

        if ($this->hasApi) {
            $apiFiles = $this->copyTarget($this->apiPath, $this->getBuildFolder() . "/api/components/com_" . $this->getExtensionName());

            $this->addFiles('api', $apiFiles);
        }

        if ($this->hasFront) {
            $frontendFiles = $this->copyTarget($this->frontPath, $this->getBuildFolder() . "/components/com_" . $this->getExtensionName());

            $this->addFiles('frontend', $frontendFiles);
        }

        // Build media (relative path)
        $media = $this->buildMedia("media/com_" . $this->getExtensionName(), 'com_' . $this->getExtensionName());
        $media->run();

        $this->addFiles('media', $media->getResultFiles());

        // Build language files for the component
        $language = $this->buildLanguage("com_" . $this->getExtensionName());
        $language->run();

        // Update XML and script.php
        $this->createInstaller();

        // Copy XML and script.php to root
        $adminFolder = $this->getBuildFolder() . "/administrator/components/com_" . $this->getExtensionName();
        $xmlFile     = $adminFolder . "/" . $this->getExtensionName() . ".xml";
        $scriptFile  = $adminFolder . "/script.php";

        $this->_copy($xmlFile, $this->getBuildFolder() . "/" . $this->getExtensionName() . ".xml");

        if (file_exists($scriptFile)) {
            $this->_copy($scriptFile, $this->getBuildFolder() . "/script.php");
        }

        // Copy Readme
        if (is_file($this->params['base'] . "/docs/README.md")) {
            $this->_copy($this->params['base'] . "/docs/README.md", $this->getBuildFolder() . "/README");
        }

        return Result::success($this, "Component build");
    }

    /**
     * Analyze the component structure
     *
     * @return  void
     *
     * @since   1.0
     */
    private function analyze()
    {
        if (!file_exists($this->adminPath)) {
            $this->hasAdmin = false;
        }

        if (!file_exists($this->apiPath)) {
            $this->hasApi = false;
        }

        if (!file_exists($this->frontPath)) {
            $this->hasFront = false;
        }

        if (file_exists($this->sourceFolder . "/media")) {
            $this->hasMedia = true;
        }
    }

    /**
     * Prepare the directory structure
     *
     * @return  void
     *
     * @since   1.0
     */
    private function prepareDirectories()
    {
        if ($this->hasAdmin) {
            $this->_mkdir($this->getBuildFolder() . "/administrator/components/com_" . $this->getExtensionName());
        }

        if ($this->hasApi) {
            $this->_mkdir($this->getBuildFolder() . "/api/components/com_" . $this->getExtensionName());
        }

        if ($this->hasFront) {
            $this->_mkdir($this->getBuildFolder() . "/components/com_" . $this->getExtensionName());
        }
    }

    /**
     * Generate the installer xml file for the component
     *
     * @return  void
     *
     * @since   1.0
     */
    private function createInstaller()
    {
        $this->printTaskInfo("Creating file installer");

        $adminFolder = $this->getBuildFolder() . "/administrator/components/com_" . $this->getExtensionName();
        $xmlFile     = $adminFolder . "/" . $this->getExtensionName() . ".xml";
        $configFile  = $adminFolder . "/config.xml";
        $scriptFile  = $adminFolder . "/script.php";
        $helperFile  = $adminFolder . "/helpers/defines.php";

        // Version & Date Replace
        $this->replaceInFile($xmlFile);
        $this->replaceInFile($scriptFile);
        $this->replaceInFile($configFile);
        $this->replaceInFile($helperFile);

        // Files and folders
        if ($this->hasAdmin) {
            $f = $this->generateFileList($this->getFiles('backend'));

            $this->taskReplaceInFile($xmlFile)
                ->from('##BACKEND_COMPONENT_FILES##')
                ->to($f)
                ->run();

            // Language files
            $f = $this->generateLanguageFileList($this->getFiles('backendLanguage'));

            $this->taskReplaceInFile($xmlFile)
                ->from('##BACKEND_LANGUAGE_FILES##')
                ->to($f)
                ->run();
        }

        if ($this->hasApi) {
            $f = $this->generateFileList($this->getFiles('api'));

            $this->taskReplaceInFile($xmlFile)
                ->from('##API_COMPONENT_FILES##')
                ->to($f)
                ->run();
        }

        if ($this->hasFront) {
            $f = $this->generateFileList($this->getFiles('frontend'));

            $this->taskReplaceInFile($xmlFile)
                ->from('##FRONTEND_COMPONENT_FILES##')
                ->to($f)
                ->run();

            // Language files
            $f = $this->generateLanguageFileList($this->getFiles('frontendLanguage'));

            $this->taskReplaceInFile($xmlFile)
                ->from('##FRONTEND_LANGUAGE_FILES##')
                ->to($f)
                ->run();
        }

        // Media files
        if ($this->hasMedia) {
            $f = $this->generateFileList($this->getFiles('media'));

            $this->taskReplaceInFile($xmlFile)
                ->from('##MEDIA_FILES##')
                ->to($f)
                ->run();
        }
    }
}
