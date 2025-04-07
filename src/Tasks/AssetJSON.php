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
                $this->printTaskInfo('Updating joomla.asset.json for ' . $extension);
                $assetFile = json_decode(file_get_contents($folder . '/joomla.asset.json'));

                if (is_dir($folder . '/js')) {
                    $jsFiles = glob($folder . '/js/*.js');

                    foreach ($jsFiles as $jsFile) {
                        if (str_ends_with($jsFile, '.min.js') && file_exists(str_replace('.min.js', '.js', $jsFile))) {
                            continue;
                        }

                        $name = str_replace(['.min.js', '.js'], '', basename($jsFile));

                        $found = false;
                        foreach ($assetFile->assets as $asset) {
                            if ($asset->type == 'script' && $asset->name == $extension . '.' . $name) {
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            $entry               = new \stdClass();
                            $entry->name         = $extension . '.' . $name;
                            $entry->type         = 'script';
                            $uri = $extension . '/' . basename($jsFile);
                            if (!str_ends_with($entry->name, '.min.js') && file_exists(substr($jsFile, 0, -3) . '.min.js')) {
                                $uri = $extension . '/' . substr(basename($jsFile), 0, -3) . '.min.js';
                            }
                            $entry->uri          = $uri;
                            $entry->dependencies = [];
                            $entry->attributes   = (object)['type' => 'module'];
                            $assetFile->assets[] = $entry;
                        }
                    }
                }

                if (is_dir($folder . '/css')) {
                    $cssFiles = glob($folder . '/css/*.css');

                    foreach ($cssFiles as $cssFile) {
                        if (str_ends_with($cssFile, '.min.css') && file_exists(str_replace('.min.css', '.css', $cssFile))) {
                            continue;
                        }

                        $name = str_replace(['.min.css', '.css'], '', basename($cssFile));

                        $found = false;
                        foreach ($assetFile->assets as $asset) {
                            if ($asset->type == 'style' && $asset->name == $extension . '.' . $name) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $entry       = new \stdClass();
                            $entry->name = $extension . '.' . $name;
                            $entry->type = 'style';
                            $uri = $extension . '/' . basename($cssFile);
                            if (!str_ends_with($entry->name, '.min.css') && file_exists(substr($cssFile, 0, -4) . '.min.css')) {
                                $uri = $extension . '/' . substr(basename($cssFile), 0, -4) . '.min.css';
                            }
                            $entry->uri          = $uri;
                            $assetFile->assets[] = $entry;
                        }
                    }
                }

            } else {
                $this->printTaskInfo('Generating joomla.asset.json for ' . $extension);

                $assets = [];

                if (is_dir($folder . '/js')) {
                    $jsFiles = glob($folder . '/js/*.js');

                    foreach ($jsFiles as $jsFile) {
                        if (str_ends_with($jsFile, '.min.js')) {
                            $entry               = new \stdClass();
                            $entry->name         = $extension . '.' . substr(basename($jsFile), 0, -7);
                            $entry->type         = 'script';
                            $entry->uri          = $extension . '/' . basename($jsFile);
                            $entry->dependencies = [];
                            $entry->attributes   = (object)['type' => 'module'];
                            $assets[]            = $entry;
                        }
                    }
                }

                if (is_dir($folder . '/css')) {
                    $cssFiles = glob($folder . '/css/*.css');

                    foreach ($cssFiles as $cssFile) {
                        if (str_ends_with($cssFile, '.min.css')) {
                            $entry       = new \stdClass();
                            $entry->name = $extension . '.' . substr(basename($cssFile), 0, -8);
                            $entry->type = 'style';
                            $entry->uri  = $extension . '/' . basename($cssFile);
                            $assets[]    = $entry;
                        }
                    }
                }

                $assetFile              = new \stdClass();
                $assetFile->{'$schema'} = 'https://developer.joomla.org/schemas/json-schema/web_assets.json';
                $assetFile->name        = $extension;
                $assetFile->version     = $this->getJConfig()->version;
                $assetFile->description = '';
                $assetFile->license     = 'GPL-2.0-or-later';
                $assetFile->assets      = $assets;
            }

            file_put_contents($folder . '/joomla.asset.json', json_encode($assetFile, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        $this->printTaskSuccess('Finished!!');

        return Result::success($this);
    }
}
