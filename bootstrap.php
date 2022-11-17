<?php
error_reporting(E_ALL);

if (file_exists($autoload = __DIR__ . '/vendor/autoload.php')) {
    require($autoload);
} elseif (file_exists($autoload = __DIR__ . '/../../autoload.php')) {
    require($autoload);
} else {
    die(
        'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -s http://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL
    );
}




$copyright = <<<EOF
Exuberant Ctags compatiable PHP enhancement, Copyright (C) 2012 Techlive Zheng
Addresses: <techlivezheng@gmail.com>, https://github.com/techlivezheng/phpctags
EOF;

$options = getopt('aC:f:Nno:RuV', array(
    'help',
    'controller_dir::',
    'protoc::',
    'type::',
    'version',
));

$options_info = <<<EOF
phpctags currently only supports a subset of the original ctags options.

Usage: gen_proto [options]

  --controller_dir=<path>
       Force output of specified tag file format [2].
  --type=<go|php|java>
       Force output of specified tag file format [2].

  --help
       Print this option summary.
  --version
       Print version identifier to standard output.
EOF
;





// prune options and its value from the $argv array
$argv_ = array();

foreach ($options as $option => $value) {
  foreach ($argv as $key => $chunk) {
    $regex = '/^'. (isset($option[1]) ? '--' : '-') . $option . '/';
    if ($chunk == $value && $argv[$key-1][0] == '-' || preg_match($regex, $chunk)) {
      array_push($argv_, $key);
    }
  }
}
if (isset($options['help'])) {
    echo "Version: ".$version."\n\n".$copyright;
    echo PHP_EOL;
    echo PHP_EOL;
    echo $options_info;
    echo PHP_EOL;
    exit;
}

if (isset($options['version'])) {
    echo "Version: 1.0.1\n\n".$copyright;
    echo PHP_EOL;
    exit;
}

while ($key = array_pop($argv_)) unset($argv[$key]);
array_shift($argv);


\App\App::$controller_dir=@$options["controller_dir"];
if(@$options["protoc"]) {
    \App\App::$protoc=  $options["protoc"];
}

\App\App::$core_server_type=@$options["type"]; //php | go
if (! \App\App::$core_server_type) {
    \App\App::$core_server_type="php";
}


array_shift($argv);

\App\App::run();
