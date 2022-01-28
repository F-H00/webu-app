<?php declare(strict_types=1);

namespace spawnCore\Custom\Gadgets;


use bin\spawn\IO;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\CompilerException;
use ScssPhp\ScssPhp\OutputStyle;
use spawnApp\Services\Commands\ListModulesCommand;
use spawnCore\CardinalSystem\ModuleNetwork\ModuleNamespacer;

class ScssHelper
{

    const SCSS_FILES_PATH = ROOT . '/vendor/scssphp/scssphp/scss.inc.php';
    public string $cacheFilePath = ROOT . '/public/cache';
    public string $baseFolder = ROOT . CACHE_DIR . '/resources/modules/scss';
    private bool $alwaysReload = false;
    private array $baseVariables = array();

    public function __construct()
    {
        $this->alwaysReload = (MODE == 'dev');
        require_once self::SCSS_FILES_PATH;
    }

    public function cacheExists(): bool
    {
        return file_exists($this->cacheFilePath);
    }

    public function createCss()
    {
        $moduleCollection = ListModulesCommand::getModuleList();
        $namespaces = NamespaceHelper::getNamespacesFromModuleCollection($moduleCollection);


        foreach($namespaces as $namespace => $moduleList) {
            $this->setBaseVariable("asset-path", '/cache/'.ModuleNamespacer::hashNamespace($namespace));
            $baseFile = $this->baseFolder . '/' . $namespace . '_index.scss';

            if(file_exists($baseFile)) {
                $css = $this->compile($baseFile);
                $cssMinified = $this->compile($baseFile, true);

                $hashedNamespace = ModuleNamespacer::hashNamespace($namespace);
                $targetFolder = $this->cacheFilePath . '/' . $hashedNamespace . '/css';

                //create output file
                /** @var FileEditor $fileWriter */
                $fileWriter = new FileEditor();
                $fileWriter->createFolder($targetFolder);
                $fileWriter->createFile($targetFolder.'/all.css', $css);
                $fileWriter->createFile($targetFolder.'/all.min.css', $cssMinified);
            }

            IO::printLine(IO::TAB . '- ' . $namespace, '', 1);

        }

    }

    private function compile(string $baseFile, bool $compressed = false)
    {
        $scss = new Compiler();

        //set the output style
        $outputStyle = $compressed ? OutputStyle::COMPRESSED : OutputStyle::EXPANDED;
        $scss->setOutputStyle($outputStyle);

        $this->registerFunctions($scss);

        $baseVariables = $this->compileBaseVariables();

        //set Base path for files
        $scss->setImportPaths([dirname($baseFile)]);

        try {
            $css = $scss->compile('
              ' . $baseVariables . '
              @import "' . basename($baseFile) . '";
            ');
        } catch (CompilerException $e) {
            $css = "";

            if (MODE == 'dev') {
                Debugger::ddump($e);
            }
        }


        return $css;
    }

    private function registerFunctions(Compiler &$scss)
    {
        //register custom scss functions
        $scss->registerFunction(
            'degToPadd',
            function ($args) {
                $deg = $args[0][1];
                $a = $args[1][1];


                $magicNumber = tan(deg2rad($deg) / 2);
                $contentWidth = $a;

                $erg = $magicNumber * $contentWidth;
                return $erg . "px";
            }
        );


        $scss->registerFunction(
            'assetURL',
            function ($args) {
                $path = $args[0][1];
                $fullpath = ROOT . 'src/Resources/public/assets/' . $path;

                $url = "url('" . $fullpath . "')";
                return $url;
            }
        );

    }

    private function compileBaseVariables()
    {
        $result = "";

        foreach ($this->baseVariables as $name => $value) {
            $result .= '$' . $name . ' : "' . $value . '";' . PHP_EOL;
        }

        return $result;
    }

    public function setBaseVariable(string $name, string $value)
    {
        $this->baseVariables[$name] = $value;
    }
}