<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Bob Ray
 * Date: 4/19/12
 * Time: 11:50 PM
 * To change this template use File | Settings | File Templates.
 */




class Bootstrap {
    /* @var $modx modX - MODX object */
    var $modx;
    /* @var $props array  - $scriptProperties array */
    var $props;
    /* @var $helpers Helpers  - class of helper functions */
    var $helpers;
    var $packageName;
    var $packageNameLower;
    var $source;
    var $sourceCore;
    var $targetBase;
    var $targetCore;
    var $targetAssets;
    var $corePath;
    var $assetsPath;
    var $tplPath; /* path to element Tpl files */
    var $replaceFields; /* replacements for placeholders in element tpl files */
    var $categoryId;
    var $makeStatic; /* array of objects to make static (comma,separated list in config) */
    var $dirPermission;
    var $filePermission;



    function  __construct(&$modx, &$props = array()) {
                $this->modx =& $modx;
                $this->props =& $props;
    }


    public function init() {
        clearstatcache(); /*  make sure is_dir() is current */
        $config = dirname(dirname(__FILE__)) . '/build.config.php';
        if (file_exists($config)) {
            $configFile = include $config;
        } else {
            die('Could not find config file at ' . $config);
        }
        $configProps = include $configFile;
        if (empty($configProps)) {
            die('Could not find config file at ' . $configFile);
        }
        $this->props = array_merge($configProps, $this->props);
        unset($config, $configFile, $configProps);

        $this->source = $this->props['source'];
        /* add trailing slash if missing */
        if (substr($this->source, -1) != "/") {
            $this->source .= "/";
        }
        require_once $this->source . '_build/utilities/helpers.class.php';
        $this->helpers = new Helpers($this->modx, $this->props);
        $this->helpers->init();

        $this->packageName = $this->props['packageName'];
        $this->packageNameLower = $this->props['packageNameLower'];

        if (isset($this->props['offerAbort']) && $this->props['offerAbort']) {
            echo 'Processing ' . $this->packageName . 'Continue? (y/n - Enter) ';
            $input = fgetc(STDIN);
            if ($input != 'y' && $input != 'Y') {
                die ('Operation aborted');
            }
        }

        $this->targetBase = MODX_BASE_PATH . 'assets/mycomponents/' . $this->packageNameLower . '/';
        $this->targetCore = $this->targetBase . 'core/components/' . $this->packageNameLower . '/';
        $this->sourceCore = $this->source . 'core/components/mycomponent/';
        $this->targetAssets = $this->targetBase . 'assets/components/'. $this->packageNameLower . '/';

        $this->dirPermission = $this->props['dirPermission'];
        $this->filePermission = $this->props['filePermission'];

        $this->makeStatic = explode(',', $this->props['makeStatic']);

        /* show basic info */
        $this->modx->log(MODX::LOG_LEVEL_INFO, 'Component: ' . $this->props['packageName']);
        $this->modx->log(MODX::LOG_LEVEL_INFO, 'Target Base: ' . $this->targetBase);
        $this->modx->log(MODX::LOG_LEVEL_INFO, 'Target Core: ' . $this->targetCore);
        $this->modx->log(MODX::LOG_LEVEL_INFO, 'Source: ' . $this->source);
        $this->modx->log(MODX::LOG_LEVEL_INFO, 'SourceCore: ' . $this->sourceCore);
        $this->modx->log(MODX::LOG_LEVEL_INFO, '--------------------------------------------------');



    }
    public function createCategory() {

        /* @var $category modCategory */
        $category = $this->modx->getObject('modCategory', array('category' => $this->packageName));
        if (! $category) {
            $category = $this->modx->newObject('modCategory', array('category' => $this->packageName));
            $category->save();
            $this->categoryId = $category->get('id');
        } else {
            $this->categoryId = $category->get('id');
        }
        unset($category);
    }

    public function createElements() {
        //$this->modx->log(MODX::LOG_LEVEL_INFO, 'Category ID: ' . $this->categoryId);



        foreach ($this->props['elements'] as $elementName => $elements) {
            $elements = explode(',', $elements);
            foreach ($elements as $name) {
                if (! empty ($name)) {
                    $this->createElement($name, $elementName);
                }



            }
        }
    }

    /**
     * Creates an element (code file and or MODX object) based on config file
     *
     * @param $name string - Name of Element
     * @param $type - Plural element type (e.g. 'plugins')
     */
    public function createElement ($name, $type) {

        // echo "\nNAME: " . $name .  "\nTYPE: " . $type;
        $lName = strToLower($name);
        /* fileNameType is type without the final s */
        $fileNameType = substr(strtolower($type),0,-1);
        $suffix = $this->props['suffixes'][$fileNameType];

        //echo "\nDIRNAME: " . $fileNameType;

        $this->modx->log(MODX::LOG_LEVEL_INFO,'Creating ' . $name . ' ' . $fileNameType);

        if ($this->props['createElementFiles']) {
            $codeDir = $this->targetCore . 'elements/' . $type;
            // echo "\nCODE DIR: " . $codeDir;
            if (! is_dir($codeDir)) {
                mkdir($codeDir, $this->dirPermission, true);
            }
            $codePath = $codeDir . '/' . $lName . '.' . $fileNameType . $suffix;
            $this->createCodeFile($name, $codePath, $fileNameType);
            // echo "\nCODE_PATH: " . $codePath . "\n";
        }
        if ($this->props['createElementObjects']) {
            $this->createElementObject($name, $fileNameType, $suffix);
        }

    }

    /**
     * Creates a code file for an element
     *
     * @param $name string - filename (without extension or type - usually $this->packageNameLower)
     * @param $codePath string - full path to file including filename
     * @param $type string - plugin, snippet, css, js, etc.
     */
    public function createCodeFile($name, $codePath, $type) {

        $tpl = $this->helpers->getTpl($type);

        /* use 'phpfile.tpl' as default for .php files */
        if ( empty($tpl) && strstr($codePath, 'php')) {
            $tpl = $this->helpers->getTpl('phpfile');
        }

        $fp = null;
        if (! file_exists($codePath)) {
            $fp = fopen($codePath, 'w');
            if ($fp) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    Creating ' . $name . ' ' . $type . ' file');
                $replace = $this->replaceFields;
                $replace['[[+elementType]]'] = ucfirst($type);
                $replace['[[+elementName]]'] = $name;
                $fileContent = $tpl;
                if (!empty ($tpl)) {
                    $fileContent = $this->helpers->replaceTags($fileContent, $replace);
                }
                fwrite($fp,$fileContent);
                fclose($fp);
                chmod($codePath, $this->filePermission);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    Could not write code file ' . $codePath);
            }
        } else {
            $this->modx->log(MODX::LOG_LEVEL_INFO, '    ' . $codePath . ' already exists');
        }

    }

    /**
     * Creates a MODX element object in the DB
     *
     * @param $name
     * @param $type
     * @param $suffix
     */
    public function createElementObject($name, $type, $suffix) {
        /* @var $object modElement */
        $lName =strtolower($name);
        /* $objectType is 'modPlugin', 'modChunk', etc. */
        $objectType = $type == 'tv' ? 'modTemplateVar' : 'mod' . ucfirst($type);
        $alias = $type == 'templatename'? 'template' : 'name';
        $object = $this->modx->getObject($objectType, array($alias => $name));
        if (!$object) {
            $this->modx->log(MODX::LOG_LEVEL_INFO, '    Creating ' . $name . ' ' . $type . ' object in DB');
            $fields = array(
                $alias => $name,
                'category' => $this->categoryId,
            );
            /* Make it static and connect to file if requested */
            if  ($this->props['allStatic'] || in_array($name, $this->makeStatic)) {

                $fields['static'] = 1;
                $fields['source'] = 1;
                $fields['static_file'] = 'assets/mycomponents/' . $this->packageNameLower  . '/core/components/' . $this->packageNameLower .  '/elements/'  . $type . 's/' . $lName . "." . $type . $suffix;
            }
            $object = $this->modx->newObject($objectType, $fields);
            if ($object) {
                $object->save();
            }
        } else {
            $this->modx->log(MODX::LOG_LEVEL_INFO, '    ' . $name . ' ' . $type . ' object already exists');
        }
    }

    public function createBasics() {
        $defaults = $this->props['defaultStuff'];
        $source = $this->source;
        $target = $this->targetBase;
        $core = $this->targetCore;
        $assets = $this->targetAssets;

        if (isset ($defaults['_build']) && $defaults['_build']) {
            $this->modx->log(MODX::LOG_LEVEL_INFO, 'Creating directory: ' . $this->targetBase);
            if (! is_dir($this->targetBase . '_build/data')) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Creating directory: ' . $this->targetBase);
                mkdir($this->targetBase . '_build/data', $this->dirPermission, true);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Directory already exists: ' . $this->targetBase);
            }
            $fromDir = $this->source . '_build/';
            $toDir = $this->targetBase . '_build/';
            $files = array(
                'build.config.sample.php',
                'build.config.php',
                'build.transport.php',
            );
            foreach ($files as $file) {
                if (! file_exists($toDir . $file)) {
                    copy ($fromDir . $file, $toDir . $file );
                } else {
                    $this->modx->log(MODX::LOG_LEVEL_INFO, '    File already exists: ' . $file);
                }
            }
        }
        if (isset ($defaults['utilities']) && $defaults['utilities']) {
            $fromDir = $this->source . '_build/utilities/';
            $toDir = $this->targetBase . '_build/utilities/';
            if (! is_dir($toDir)) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Copying Utilities directory');
                $this->helpers->copyDir($fromDir, $toDir);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    Utilities directory already exists');
            }
        }


        if (isset ($defaults['lexicon']) && $defaults['lexicon']) {
            $this->modx->log(MODX::LOG_LEVEL_INFO,'Creating Lexicon files');
            $toDir = $this->targetCore . 'lexicon';
            //echo  "\n" . 'TODIR: ' . $toDir . "\n";
            if (! is_dir($toDir)) {
                $this->modx->log(MODX::LOG_LEVEL_INFO,'    Creating lexicon directory');
                mkdir($toDir, $this->dirPermission, true);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO,'    Lexicon directory already exists');
            }
            if (!empty($defaults['languages'])) {
                $languages = explode(',', $defaults['languages']);
                foreach($languages as $lang) {
                    if (!is_dir($toDir . '/' . $lang)) {
                        $this->modx->log(MODX::LOG_LEVEL_INFO,'        creating ' . $lang . ' directory');
                        mkdir($toDir . '/' . $lang, $this->dirPermission, true);
                    } else {
                        $this->modx->log(MODX::LOG_LEVEL_INFO,'        ' . $lang . ' directory already exists');
                    }
                }
            }
        }
        if (isset ($defaults['docs']) && $defaults['docs']) {
            $this->modx->log(MODX::LOG_LEVEL_INFO,'Creating doc files');
            $fromDir = $this->sourceCore . 'docs';
            $toDir = $this->targetCore . 'docs';
            if (! is_dir($toDir)) {
                mkdir($toDir, $this->dirPermission, true);
                $this->modx->log(MODX::LOG_LEVEL_INFO,'    copying doc files');
                $this->helpers->copyDir($fromDir,$toDir);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO,'    docs directory already exists -- no files copied');
            }

        }

        return true;
    }
    public function createAssetsDirs() {
        $optionalDirs = $this->props['assetsDirs'];
        $this->modx->log(MODX::LOG_LEVEL_INFO,'Creating Assets directories');
        foreach($optionalDirs as $dir => $val) {
            $targetDir = $this->targetAssets . $dir;
            if ($val && (! is_dir($targetDir)) ) {
                if (mkdir($targetDir, $this->dirPermission, true)) {
                    $this->modx->log(MODX::LOG_LEVEL_INFO,'    Created ' . $targetDir . ' directory');
                }
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO,'    ' . $targetDir . ' directory already exists');
            }
            if ($dir == 'css' || $dir == 'js') {
                $file = $this->packageNameLower . '.' . $dir;
                $this->createCodeFile($file , $targetDir . '/' . $file, $dir);
            }
        }


}
    /** creates resolver for attaching events to plugins */
    public function createPluginResolver() {
        $pluginEvents = $this->props['pluginEvents'];
        if (! empty($pluginEvents)) {
            $tpl = $this->helpers->getTpl(('pluginresolver.php'));
            $this->helpers->replaceTags($tpl);
            if (empty($tpl)) {
                $this->modx->log(MODX::LOG_LEVEL_ERROR, 'pluginresolver tpl is empty');
            }
            $dir = $this->targetBase . '_build/resolvers';
            if (! is_dir($dir)) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Creating resolvers directory');
                mkdir($dir, $this->filePermission, true);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Resolvers directory already exists');
            }
            $filePath = $this->targetBase . '_build/resolvers/plugin.resolver.php';
            $code = '';
            if (! file_exists($filePath)) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    Creating file ' . $filePath);
                $fp = fopen($filePath, 'w');
                if ($fp) {
                    $codeTpl = $this->helpers->getTpl('pluginresolvercode.php');
                    if (empty($codeTpl)) {
                        $this->modx->log(MODX::LOG_LEVEL_ERROR, 'pluginresolvercode tpl is empty');
                    }
                    $codeTpl = str_replace('<?php', '', $codeTpl);

                    foreach($pluginEvents as $plugin => $events) {

                        $tempCodeTpl = str_replace('[[+plugin]]', $plugin, $codeTpl);
                        $tempCodeTpl = str_replace('[[+events]]', $events, $tempCodeTpl);
                        $code .= "\n" . $tempCodeTpl;
                    }
                } else {
                    $this->modx->log(MODX::LOG_LEVEL_ERROR, 'Could not open code file ' . $filePath);
                }
                $tpl = str_replace('/* [[+code]] */', $code, $tpl);
                fwrite($fp, $tpl);
                fclose($fp);


            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    ' . $filePath . ' file already exists');
            }
        }
    }

    /** creates resolver for attaching TVs to Templates */
    public function createTvResolver()
    {
        $templateVarTemplates = $this->props['templateVarTemplates'];
        if (!empty($templateVarTemplates)) {
            $tpl = $this->helpers->getTpl('tvresolver.php');
            $tpl = $this->helpers->replaceTags($tpl);
            if (empty($tpl)) {
                $this->modx->log(MODX::LOG_LEVEL_ERROR, 'tvresolver tpl is empty');
            }
            $dir = $this->targetBase . '_build/resolvers';
            if (!is_dir($dir)) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Creating resolvers directory');
                mkdir($dir, $this->filePermission, true);
            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, 'Resolvers directory already exists');
            }
            $filePath = $this->targetBase . '_build/resolvers/tv.resolver.php';
            $code = '';
            if (!file_exists($filePath)) {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    Creating file ' . $filePath);
                $fp = fopen($filePath, 'w');
                if ($fp) {

                    $codeTpl = $this->helpers->getTpl('tvresolvercode.php');
                    if (empty($codeTpl)) {
                        $this->modx->log(MODX::LOG_LEVEL_ERROR, 'tvresolvercode tpl is empty');
                    }
                    $codeTpl = str_replace('<?php', '', $codeTpl);

                    foreach ($templateVarTemplates as $template => $tvs) {

                        $tempCodeTpl = str_replace('[[+template]]', $template, $codeTpl);
                        $tempCodeTpl = str_replace('[[+tvs]]', $tvs, $tempCodeTpl);
                        $code .= "\n" . $tempCodeTpl;
                    }
                } else {
                    $this->modx->log(MODX::LOG_LEVEL_ERROR, 'Could not open code file ' . $filePath);
                }
                $tpl = str_replace('/* [[+code]] */', $code, $tpl);
                fwrite($fp, $tpl);
                fclose($fp);


            } else {
                $this->modx->log(MODX::LOG_LEVEL_INFO, '    ' . $filePath . ' file already exists');
            }
        }
    }
    public function createValidators() {
        $validators = $this->props['validators'];
        if (!empty($validators)) {
            $path = $this->targetBase . '_build/validators/';
            if (!is_dir($path)) {
                mkdir($path, $this->dirPermission, true);
            }
            $validators = explode(',', $validators);
            foreach ($validators as $validator) {
                if ($validator == 'default') {
                    $fileName = $this->packageNameLower . '.' . 'validator.php';
                } else {
                    $fileName = $validator . '.' . 'validator.php';
                }
                $tpl = $this->helpers->getTpl('genericvalidator.php');
                $tpl = $this->helpers->replaceTags($tpl);
                $file = $path . $fileName;
                if (!file_exists($file)) {
                    $fp = fopen($file, 'w');
                    if ($fp) {
                        $this->modx->log(MODX::LOG_LEVEL_INFO, '    Creating ' . $file);
                        fwrite($fp, $tpl);
                        fclose($fp);
                    } else {
                        $this->modx->log(MODX::LOG_LEVEL_INFO, '    Could not write file ' . $file);
                    }
                } else {
                    $this->modx->log(MODX::LOG_LEVEL_INFO, '    ' . $file . ' file already exists');
                }
            }


        }

    }
    public function createExtraResolvers() {
        $resolvers = $this->props['resolvers'];
        if (!empty($resolvers)) {
            $path = $this->targetBase . '_build/resolvers/';
            if (!is_dir($path)) {
                mkdir($path, $this->dirPermission, true);
            }
            $resolvers = explode(',', $resolvers);
            foreach ($resolvers as $resolver) {
                if ($resolver == 'default') {
                    $fileName = $this->packageNameLower . '.' . 'resolver.php';
                } else {
                    $fileName = $resolver . '.' . 'resolver.php';
                }
                $tpl = $this->helpers->getTpl('genericresolver.php');
                $tpl = $this->helpers->replaceTags($tpl);
                $file = $path . $fileName;
                if (!file_exists($file)) {
                    $fp = fopen($file, 'w');
                    if ($fp) {
                        $this->modx->log(MODX::LOG_LEVEL_INFO, '    Creating ' . $file);
                        fwrite($fp, $tpl);
                        fclose($fp);
                    } else {
                        $this->modx->log(MODX::LOG_LEVEL_INFO, '    Could not write file ' . $file);
                    }
                } else {
                    $this->modx->log(MODX::LOG_LEVEL_INFO, '    ' . $file . ' file already exists');
                }
            }
        }
    }


    /* The next three function are not used, but can replace placeholders in files after the fact */
    public function doSearchReplace() {
        $this->_doSearchReplace($this->dest);
    }

    protected function _doSearchReplace($path, &$name = array() ) {

        $names = array();
    
        $path = $path == ''? dirname(__FILE__) : $path;
        $lists = @scandir($path);
    
        if(!empty($lists)){
          foreach($lists as $f) {
              if(is_dir($path. '/' . $f)) {
                if ($f == ".." || $f == "." || strstr ($f,'.git' )) {
                    continue;
                }
                $this->_doSearchReplace($path. '/'. $f, $name);
              } else {
                  if (! $this->ignore($f) ) {
                      $names[] = $path. '/' . $f;
                      $this->modx->log(MODX::LOG_LEVEL_INFO,'Processing: ' . $path . '/' . $f);
                  } else {
                      $this->modx->log(MODX::LOG_LEVEL_INFO,'----Ignoring: ' . $path . '/' . $f);
                  }
    
              }
          }
        }
        return $names;
    }
    protected function ignore($f) {
           /* $noProcess = array_merge(array(
                '.gitignore',
                '.zip',
                '.html',
                '.js',
                '.css',
                '.tpl',
                '.gif',
                '.jpg',
                '.wav',
                '.mov',
                '.mpg',
            ),$this->noProcess);*/
    
            foreach ($this->noProcess as $s) {
                if (stristr($f,$s)) {
                    return true;
                }
            }
            return false;
    }

} /* end of class */
