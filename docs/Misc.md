## Additional Tools
### Map into Joomla installation
With the command `vendor/bin/robo map <target>` you can map the extension folders into an existing Joomla installation. Each main extension folder is mapped individually with a symlink. Please note that for this to work in Windows, the command has to be run in an elevated command prompt.

### Add Copyright header
The command `vendor/bin/robo headers` removes the file docblock from each php file in the source folder and adds in the header which has been set up in the `jorobo.ini`.

### Bump version in docblocks
The command `vendor/bin/robo bump` replaces the string `__DEPLOY_VERSION__` in all php files in the source folder with the version set in the `jorobo.ini`.

### Generate joomla.asset.json files
The command `vendor/bin/robo asset:json` will generate a `joomla.asset.json` file for every folder in the `/media` folder inside the source folder. If the file already exists, it will try to add new entries to it. It scans the `css` and `js` folders for css and script files and will try to add the minified version over the normal file, denoted with a `.min.css` or `.min.js` at the end. The `joomla.asset.json` is not necessarily complete, since you still have to add for example dependencies for your assets.
