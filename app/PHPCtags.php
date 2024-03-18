<?php
namespace App;

class PHPCtags
{
    const VERSION = '0.6.0';

    private $mFile;

    private $mFiles;
    private $mFileLines ;

    private static $mKinds = array(
        't' => 'trait',
        'c' => 'class',
        'm' => 'method',
        'f' => 'function',
        'p' => 'property',
        'd' => 'constant',
        'v' => 'variable',
        'i' => 'interface',
        'n' => 'namespace',
        'T' => 'usetrait',
    );

    private $mParser;
    private $mLines;
    private $mOptions;
    private $mUseConfig=array();
    private $tagdata;
    private $cachefile;
    private $filecount;
    public $out_type="field";// field,  method

    public function __construct($options)
    {
        $this->mParser =  new \PhpParser\Parser\Php7(new \PhpParser\Lexer\Emulative);
        $this->mLines = array();
        $this->mOptions = $options;
        $this->filecount = 0;
    }

    public function setMFile($file)
    {
        if (empty($file)) {
            throw new PHPCtagsException('No File specified.');
        }

        if (!file_exists($file)) {
            throw new PHPCtagsException('Warning: cannot open source file "' . $file . '" : No such file');
        }

        if (!is_readable($file)) {
            throw new PHPCtagsException('Warning: cannot open source file "' . $file . '" : File is not readable');
        }

        //$this->mFile = realpath($file);
        $this->mFile=$file;
        $this->mFileLines = $this->mFiles[$this->mFile]  ;
    }

    public static function getMKinds()
    {
        return self::$mKinds;
    }
    public function cleanFiles()
    {
        $this->mFiles=array();
        $this->mLines=array();
        $this->mUseConfig=array();
    }
    public function addFile($file)
    {
        //$f=realpath($file);
        $f=$file;
        $this->mFiles[$f] = file($f) ;
    }

    public function setCacheFile($file)
    {
        $this->cachefile = $file;
    }

    public function addFiles($files)
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    private function getNodeAccess($node)
    {
        if ($node->isPrivate()) {
            return 'private';
        }
        if ($node->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    /**
     * stringSortByLine
     *
     * Sort a string based on its line delimiter
     *
     * @author Techlive Zheng
     *
     * @access public
     * @static
     *
     * @param string  $str     string to be sorted
     * @param boolean $foldcse case-insensitive sorting
     *
     * @return string sorted string
     **/
    public static function stringSortByLine($str, $foldcase = false)
    {
        $arr = explode("\n", $str);
        if (!$foldcase) {
            sort($arr, SORT_STRING);
        } else {
            sort($arr, SORT_STRING | SORT_FLAG_CASE);
        }
        $str = implode("\n", $arr);
        return $str;
    }

    private static function helperSortByLine($a, $b)
    {
        return $a['line'] > $b['line'] ? 1 : 0;
    }

    private function getRealClassName($className, $scope = array())
    {
        if ($className=="\$this" ||  $className == "static") {
            $c_scope = array_pop($scope);
            list($c_type, $c_name) = each($c_scope);
            $n_scope = array_pop($scope);
            if (!empty($n_scope)) {
                list($n_type, $n_name) = each($n_scope);
                $s_str =  $n_name . '\\' . $c_name ;
            } else {
                $s_str = $c_name;
            }
            return $s_str;
        }

        if ($className[0] != "\\") {
            $ret_arr=explode("\\", $className, 2);
            if (count($ret_arr)==2) {
                $pack_name=$ret_arr[0];
                if (isset($this->mUseConfig[ $pack_name])) {
                    return  $this->mUseConfig[$pack_name]."\\".$ret_arr[1] ;
                } else {
                    return $className;
                }
            } else {
                if (isset($this->mUseConfig[$className])) {
                    return  $this->mUseConfig[$className];
                } else {
                    return $className;
                }
            }
        } else {
            return $className;
        }
    }

    private function func_get_return_type($node, $scope)
    {
        $return_type="". $node->returnType;

        if (!$return_type) {
            if (preg_match("/@return[ \t]+([\$a-zA-Z0-9_\\\\|]+)/", $node->getDocComment(), $matches)) {
                $return_type= $matches[1];
            }
        }
        if ($return_type) {
            $return_type=$this->getRealClassName($return_type, $scope);
        }
        return $return_type;
    }
    private function struct($node, $reset = false, $parent = array())
    {
        static $scope = array();
        static $structs = array();

        if ($reset) {
            $structs = array();
        }




        $kind = $name = $line = $access = $extends = '';
        $return_type="";
        $implements = array();


        if (!empty($parent)) {
            array_push($scope, $parent);
        }
        if (!is_array($node)) {
            /*
            try {
            echo   @$node->getDocComment()."\n";
            }catch(\Exception $e){
            }
            */
        }

        if (is_array($node)) {
            foreach ($node as $subNode) {
                $this->struct($subNode);
            }
        } elseif ($node instanceof \PHPParser\Node\Stmt\Class_) {
            $name = $node->name;
            $filed_scope=$scope;
            array_push($filed_scope, array('class' => $name ));
            foreach ($node as $key => $subNode) {
                $this->struct($subNode, false, array('class' => $name));
            }
        } elseif ($node instanceof \PHPParser\Node\Stmt\ClassMethod) {
            if ($this->out_type =="method") {
                $name = $node->name;

                $tmp_scope=$scope;
                $c_scope = array_pop($tmp_scope);
                if ($c_scope) {
                    list($c_type, $c_name) = [key($c_scope), current($c_scope)];

                    //$c_name=strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $c_name));
                    $structs []  = [$c_name,$name];
                }
            }
        } elseif ($node instanceof \PHPParser\Node\Stmt\ClassConst) {
            $cons = $node->consts[0];
            $name = $cons->name;
            if ($this->out_type =="field") {
                $structs []  = [ $name,  strval($node->getDocComment())  ] ;
            }
        } elseif ($node instanceof \PHPParser\Node\Stmt\Property) {
            $kind = 'p';
            $prop = $node->props[0];
            $name = $prop->name;
            if ($this->out_type =="field") {
                $structs []  = [ $name,  strval($node->getDocComment())  ] ;
            }
        } elseif ($node instanceof \PHPParser\Node\Stmt\Namespace_) {
            $kind = 'n';
            $name = $node->name;
            $line = $node->getLine();
            foreach ($node as $subNode) {
                $this->struct($subNode, false, array('namespace' => $name));
            }
        }


        return $structs;
    }

    private function render($structure)
    {
        $str = '';
        foreach ($structure as $struct) {
            $file = $struct['file'];

            if (!in_array($struct['kind'], $this->mOptions['kinds'])) {
                continue;
            }

            if (!isset($files[$file])) {
                $files[$file] = file($file);
            }

            $lines = $files[$file];

            if (empty($struct['name']) || empty($struct['line']) || empty($struct['kind'])) {
                return;
            }

            $kind= $struct['kind'];
            $str .= '(';
            if ($struct['name'] instanceof \PHPParser\Node\Expr\Variable) {
                $str .= '"'. addslashes($struct['name']->name) . '" ' ;
            } else {
                $str .= '"'. addslashes($struct['name']) . '" ' ;
            }

            $str .= ' "'. addslashes($file.":".$struct['line'])  . '" ' ;


            if ($this->mOptions['excmd'] == 'number') {
                $str .= "\t" . $struct['line'];
            } else { //excmd == 'mixed' or 'pattern', default behavior
                #$str .= "\t" . "/^" . rtrim($lines[$struct['line'] - 1], "\n") . "$/";
                if ($kind=="f" || $kind=="m") {
                    $str .= ' "'. addslashes(rtrim($lines[$struct['line'] - 1], "\n")) . '" ' ;
                } else {
                    $str .= ' nil ' ;
                }
            }

            if ($this->mOptions['format'] == 1) {
                $str .= "\n";
                continue;
            }

            //$str .= ";\"";

            #field=k, kind of tag as single letter
            if (in_array('k', $this->mOptions['fields'])) {
                //in_array('z', $this->mOptions['fields']) && $str .= "kind:";
                //$str .= "\t" . $struct['kind'];

                $str .= ' "'. addslashes($kind) . '" ' ;
            } elseif (in_array('K', $this->mOptions['fields'])) {
                #field=K, kind of tag as fullname
                //in_array('z', $this->mOptions['fields']) && $str .= "kind:";
                //$str .= "\t" . self::$mKinds[$struct['kind']];
                $str .= ' "'. addslashes(self::$mKinds[$kind]) . '" ' ;
            }

            #field=n
            if (in_array('n', $this->mOptions['fields'])) {
                //$str .= "\t" . "line:" . $struct['line'];
                ;//$str .= ' "'. addslashes( $struct['line'] ) . '" ' ;
            }


            #field=s
            if (in_array('s', $this->mOptions['fields']) && !empty($struct['scope'])) {
                // $scope, $type, $name are current scope variables
                $scope = array_pop($struct['scope']);
                list($type, $name) = each($scope);
                switch ($type) {
                    case 'class':
                    case 'interface':
                        // n_* stuffs are namespace related scope variables
                        // current > class > namespace
                        $n_scope = array_pop($struct['scope']);
                        if (!empty($n_scope)) {
                            list($n_type, $n_name) = each($n_scope);
                            $s_str =  $n_name . '\\' . $name;
                        } else {
                            $s_str =   $name;
                        }

                        $s_str = "(\"" .  addslashes($type) .  "\".\"".    addslashes($s_str). "\")";
                        break;
                    case 'method':
                        // c_* stuffs are class related scope variables
                        // current > method > class > namespace
                        $c_scope = array_pop($struct['scope']);
                        list($c_type, $c_name) = each($c_scope);
                        $n_scope = array_pop($struct['scope']);
                        if (!empty($n_scope)) {
                            list($n_type, $n_name) = each($n_scope);
                            $s_str =  $n_name . '\\' . $c_name . '::' . $name;
                        } else {
                            $s_str = $c_name . '::' . $name;
                        }

                        $s_str = "(\"" .  addslashes($type) .  "\".\"".    addslashes($s_str). "\")";
                        break;
                    default:
                        $s_str = "(\"" .  addslashes($type) .  "\".\"".    addslashes($name). "\")";
                        break;
                }
                $str .= $s_str ;
            } else {
                //scope
                if ($kind == "f" || $kind == "d" || $kind == "c" || $kind == "i" || $kind == "v") {
                    $str .= ' () ' ;
                }
            }


            #field=i
            if (in_array('i', $this->mOptions['fields'])) {
                $inherits = array();
                if (!empty($struct['extends'])) {
                    $inherits[] =  $this->getRealClassName($struct['extends']->toString());
                }
                if (!empty($struct['implements'])) {
                    foreach ($struct['implements'] as $interface) {
                        $inherits[] = $this->getRealClassName($interface->toString());
                    }
                }
                if (!empty($inherits)) {
                    //$str .= "\t" . 'inherits:' . implode(',', $inherits);
                    $str .= ' "'. addslashes(implode(',', $inherits)) . '" ' ;
                } else {
                    //scope
                    if ($kind == "c" || $kind == "i") {
                        $str .= ' nil ' ;
                    }
                }
            } else {
                //scope
                if ($kind == "c" || $kind == "i") {
                    $str .= ' nil ' ;
                }
            }

            #field=a
            if (in_array('a', $this->mOptions['fields']) && !empty($struct['access'])) {
                //$str .= "\t" . "access:" . $struct['access'];
                $str .= ' "'. addslashes($struct['access']) . '" ' ;
            } else {
            }

            #type
            if ($kind == "f" || $kind == "p"  || $kind == "m"  || $kind == "d"  || $kind == "v"  || $kind == "T") {
                //$str .= "\t" . "type:" . $struct['type'] ;
                if ($struct['type']) {
                    $str .= ' "'. addslashes($struct['type']) . '" ' ;
                } else {
                    $str .= ' nil ' ;
                }
            }



            $str .= ")\n";
        }

        $str = str_replace("\x0D", "", $str);

        return $str;
    }

    private function full_render()
    {
        // Files will have been rendered already, just join and export.

        $str = '';
        foreach ($this->mLines as $file => $data) {
            $str .= $data.PHP_EOL;
        }

        /*
        // sort the result as instructed
        if (isset($this->mOptions['sort']) && ($this->mOptions['sort'] == 'yes' || $this->mOptions['sort'] == 'foldcase')) {
        $str = self::stringSortByLine($str, $this->mOptions['sort'] == 'foldcase');
        }

        */
        // Save all tag information to a file for faster updates if a cache file was specified.
        if (isset($this->cachefile)) {
            file_put_contents($this->cachefile, serialize($this->tagdata));
            if ($this->mOptions['V']) {
                echo "Saved cache file.".PHP_EOL;
            }
        }

        $str = trim($str);

        return $str;
    }

    public function export()
    {
        $start = microtime(true);

        if (empty($this->mFiles)) {
            throw new PHPCtagsException('No File specified.');
        }


        foreach (array_keys($this->mFiles) as $file) {
            $ret=$this->process($file);
            return $ret;
        }


        return $content;
    }

    private function process($file)
    {
        // Load the tag md5 data to skip unchanged files.
        if (!isset($this->tagdata) && isset($this->cachefile) && file_exists(realpath($this->cachefile))) {
            if ($this->mOptions['V']) {
                echo "Loaded cache file.".PHP_EOL;
            }
            $this->tagdata = unserialize(file_get_contents(realpath($this->cachefile)));
        }

        if (is_dir($file) && isset($this->mOptions['R'])) {
            $iterator = new \RecursiveIteratorIterator(
                new \ReadableRecursiveDirectoryIterator(
                    $file,
                    \FilesystemIterator::SKIP_DOTS |
                    \FilesystemIterator::FOLLOW_SYMLINKS
                )
            );

            $extensions = array('.php', '.php3', '.php4', '.php5', '.phps');

            foreach ($iterator as $filename) {
                if (!in_array(substr($filename, strrpos($filename, '.')), $extensions)) {
                    continue;
                }

                if (isset($this->mOptions['exclude']) && false !== strpos($filename, $this->mOptions['exclude'])) {
                    continue;
                }

                try {
                    return $this->process_single_file($filename);
                } catch (\Exception $e) {
                    echo "\PHPParser: {$e->getMessage()} - {$filename}".PHP_EOL;
                    return false;
                }
            }
        } else {
            try {
                return $this->process_single_file($file);
            } catch (\Exception $e) {
                echo "\PHPParser: {$e->getMessage()} - {$file}".PHP_EOL;
                return false;
            }
        }
        return true;
    }

    public function process_single_file($filename)
    {
        $file = file_get_contents($filename);
        return $this->struct($this->mParser->parse($file), true);
    }
}

class PHPCtagsException extends \Exception
{
    public function __toString()
    {
        return "\nPHPCtags: {$this->message}\n";
    }
}

class ReadableRecursiveDirectoryIterator extends \RecursiveDirectoryIterator
{
    #[\ReturnTypeWillChange]
    function getChildren()
    {
        try {
            return new \ReadableRecursiveDirectoryIterator($this->getPathname());
        } catch (\UnexpectedValueException $e) {
            file_put_contents('php://stderr', "\nPHPPCtags: {$e->getMessage()} - {$this->getPathname()}\n");
            return new \RecursiveArrayIterator(array());
        }
    }
}
