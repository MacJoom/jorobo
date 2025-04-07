<?php

/**
 * @package    JoRobo
 *
 * @copyright  Copyright (C) 2005 - 2025 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks;

use Joomla\Github\Github;
use Joomla\Registry\Registry;
use Robo\Result;

/**
 * Generate the joomla.asset.json file for the webasset manager
 *
 * @package  Joomla\Jorobo\Tasks
 *
 * @since    1.0
 */
class AssetJSON extends JTask
{
    use \Robo\Task\Development\Tasks;
    use Generate\Tasks;

    /**
     * Generate joomla.asset.json file
     *
     * @return  Result
     *
     * @since   1.0
     */
    public function run()
    {
        $this->printTaskInfo("Generating joomla.asset.json for webasset manager");

        $folders = glob($this->getSourceFolder() . '/media/*', GLOB_ONLYDIR);

        foreach ($folders as $folder) {
            $extension = basename($folder);

            if (file_exists($folder . '/joomla.asset.json')) {
                continue;
            }

            $this->printTaskInfo('Generating joomla.asset.json for ' . $extension);

            $assets = [];

            if (is_dir($folder . '/js')) {
                $jsFiles = glob($folder . '/js/*.js');

                foreach ($jsFiles as $jsFile) {
                    if (str_ends_with($jsFile, '.min.js')) {
                        $entry = new \stdClass();
                        $entry->name = $extension . '.' . substr(basename($jsFile), 0, -7);
                        $entry->type = 'script';
                        $entry->uri = $extension . '/' . basename($jsFile);
                        $entry->dependencies = [];
                        $entry->attributes = (object) ['type' => 'module'];
                        $assets[] = $entry;
                    }
                }
            }

            if (is_dir($folder . '/css')) {
                $cssFiles = glob($folder . '/css/*.css');

                foreach ($cssFiles as $cssFile) {
                    if (str_ends_with($cssFile, '.nin.css')) {
                        $entry = new \stdClass();
                        $entry->name = $extension . '.' . substr(basename($cssFile), 0, -8);
                        $entry->type = 'style';
                        $entry->uri = $extension . '/' . basename($cssFile);
                        $assets[] = $entry;
                    }
                }
            }

            $assetFile = new \stdClass();
            $assetFile->{'$schema'} = 'https://developer.joomla.org/schemas/json-schema/web_assets.json';
            $assetFile->name = $extension;
            $assetFile->version = $this->getJConfig()->version;
            $assetFile->description = '';
            $assetFile->license = 'GPL-2.0-or-later';
            $assetFile->assets = $assets;

            file_put_contents($folder . '/joomla.asset.json', json_encode($assetFile, JSON_PRETTY_PRINT));
        }

        return Result::success($this);
    }
}
