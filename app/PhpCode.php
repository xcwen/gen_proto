<?php
namespace App;

class PhpCode
{
    public static function gen_control_action_map($action_map, $controller_dir)
    {
        $ctags = new PHPCtags([]);
        $ctags->out_type="method";
        $func_map=[];
        foreach (App::$proto_dir_list as $proto_dir_fix) {
            $proto_dir_fix_str="";
            if ($proto_dir_fix) {
                $proto_dir_fix_str="$proto_dir_fix.";
            }
            $proto_dir= $controller_dir."/". Utils::toCamelCase($proto_dir_fix) ;
            $file_list=scandir($proto_dir);
            foreach ($file_list as $file) {
                if (preg_match("/^([a-zA-Z].*)\.php\$/", $file, $matches)) {
                    try {
                        $field_list = $ctags->process_single_file("$proto_dir/$file");
                        foreach ($field_list as $item) {
                            $ctrl_key= $proto_dir_fix_str.Utils::toCamelCase($item[0]->name);
                            if (!isset($func_map[$ctrl_key ])) {
                                $func_map[$ctrl_key ] =[];
                            }
                            $func_map[ $ctrl_key][$item[1]->name]=true;
                        }
                    } catch (\Exception $e) {
                        echo "解析异常:". $matches[1].".php\n";
                        $func_map[ $proto_dir_fix_str. Utils::toCamelCase($matches[1])]=false;
                    }
                }
            }
        }
        //print_r( $func_map );

        $tmp_func_str="";
        foreach ($action_map as $name => $func_str) {
            $tmp_func_str.="\n    //$name\n".$func_str ;
        }
        file_put_contents(App::$controller_dir. "/../../../log/proto_func.txt", $tmp_func_str);

        //生成
        foreach ($action_map as $name => $func_str) {
            preg_match("/((.*)\\.)?((.*)__(.*))/", $name, $arr) ;
            $version=$arr[2];
            $ctrl=$arr[4];
            $action=$arr[5];
            $namespace_version="";
            $version_fix="";
            $ctrl_class=static::get_ctrl_class($ctrl);

            $ctrl_key=$ctrl_class;
            if ($version) {
                $version_fix= Utils::toCamelCase($version)."/";
                $namespace_version="\\". Utils::toCamelCase($version) ;
                $ctrl_key=  $version.".". $ctrl_key;
            }

            //echo "check $ctrl_key \n ";
            if (@$func_map[$ctrl_key]===false) { //解析异常，不处理
            } else {
                $existed_flag= @$func_map[$ctrl_key][$action];
                if (!$existed_flag) { // 需要生成
                    Utils::exec_cmd("mkdir -p $controller_dir/$version_fix");

                    $src_file="$controller_dir/$version_fix$ctrl_class.php";
                    $file_content=@file_get_contents("$src_file");
                    $file_content=rtrim($file_content);
                    if (!$file_content) {
                        $file_content=<<<END
<?php
namespace App\\Controllers$namespace_version;

use App\\Controllers\\Controller;


use \\Proto\\Project as P;
use \\Proto\\Project\\error\\enum_error as ERR;


use App\\Enums as E;
use App\\Helper\\Utils;

class $ctrl_class extends Controller
{





}

END;
                    }
                    $file_content=trim($file_content);
                    $data_len=strlen($file_content);
                    if ($file_content[$data_len-1]=="}") { //正常。
                        //添加
                        $file_content=substr($file_content, 0, $data_len-2)."\n". $func_str ."\n}\n";
                        //echo $file_content ;
                        file_put_contents($src_file, $file_content);
                    } else {
                        echo "生成：action:[$action] 模板代码失败： $src_file 没有以'}'结尾 \n";
                    }
                }
            }
        }
        return $func_map;
    }

    public static function gen_get_function_field_name($field_name)
    {
        $str = preg_replace_callback('/([_]+([a-z0-9A-Z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $field_name);
        return "get". strtoupper($str[0]).substr($str, 1) ;
    }



    public static function gen_cmd_controller($version, $ctrl_action, $action, $in_obj, $desc)
    {
        $version_fix="";
        if ($version) {
            $version_fix= Utils::toCamelCase($version)."\\";
        }



        $set_str="";
        $max_field_len=0;
        foreach ($in_obj as $item) {
            $name=$item["2"];
            $name_len=strlen($name);
            if ($name_len>$max_field_len) {
                $max_field_len=$name_len;
            }
        }

        foreach ($in_obj as $item) {
            $name=$item["2"];
            $name_len=strlen($name);
            $fix_space="";
            for ($i=0; $i< $max_field_len-$name_len; $i++) {
                $fix_space.=" ";
            }

            $set_str.="        \$$name".  $fix_space ." = \$in->". static::gen_get_function_field_name($name). "();\n" ;
        }

        if (preg_match('/_opt$/', $action)) {
            return "
    /**
     * $desc
     *
     */
    public function $action(
        P\\$version_fix$ctrl_action\in  \$in,
        P\\$version_fix$ctrl_action\out &\$out
    ) {
$set_str

        if (\$opt_type==\"add\") {

        } elseif (\$opt_type==\"set\") {

        } else {
            return \$this->output_opt_type_err(\$opt_type);
        }

        return \$this->output_err(\" 自动生成代码, [$version_fix$ctrl_action]还未实现!\");
    }";
        } else {
            return "
    /**
     * $desc
     *
     */
    public function $action(
        P\\$version_fix$ctrl_action\in  \$in,
        P\\$version_fix$ctrl_action\out &\$out
    ) {
$set_str

        return \$this->output_err(\" 自动生成代码, [$version_fix$ctrl_action]还未实现!\");
    }";
        }
    }

    public static function gen_cmd_bind_php_str($cmd_list, $struct_map)
    {
        $cmd_php_str="<?php\n"
        ."return [\n";

        $action_map=[];
        $tag_map=[];
        $maintainer_map=[];
        foreach ($cmd_list as $item) {
            foreach ($item["TAGS"] as $tag) {
                if ($tag) {
                    $tag_map[$tag]=true;
                }
            }
            foreach ($item["MAINTAINER"] as $maintainer) {
                if ($maintainer) {
                    $maintainer_map[$maintainer]=true;
                }
            }

            $name=$item["NAME"];
            $config=$item["CONFIG"];
            if (preg_match("/((.*)\\.)?((.*)__(.*))/", $name, $matches)) {
                //$cmdid=$item["CMD"];
                $ctrl=$matches[4];
                $action=$matches[5];
                $ctrl_action=$matches[3];
                $version=$matches[2];
                $version_fix="";
                $call_path="/$ctrl/$action";
                if ($version) {
                    $version_fix= Utils::toCamelCase($version)."\\";
                    $call_path="/$version$call_path";
                }

                $ctrl_class=static::get_ctrl_class($ctrl);

                $desc=addslashes($item["TITLE"]);
                $in_struct= "{$name}.in";
                $out_struct= "{$name}.out";
                $in_class="null";
                $out_class="null";
                $in_obj=null;
                // $out_obj=null;
                $auth=trim($item["AUTH"]);
                // $return_check=trim(@$item["RETURN_CHECK"]);
                if ($auth==="") {
                    $auth="session";
                }

                if (isset($struct_map["$in_struct"])) {
                    $in_class="\\Proto\\Project\\$version_fix{$ctrl_action}\\in::class";
                    $in_obj= $struct_map["$in_struct"];
                }

                if (isset($struct_map["$out_struct"])) {
                    $out_class="\\Proto\\Project\\$version_fix{$ctrl_action}\\out::class";
                    // $out_obj= $struct_map["$out_struct"];
                }

                if (App::$core_server_type =="java") {
                    $action_map[$name]=JavaCode::gen_cmd_controller($version, $ctrl_action, $action, $in_obj, $desc) ;
                } else {
                    $action_map[$name]=static::gen_cmd_controller($version, $ctrl_action, $action, $in_obj, $desc) ;
                }
                if (!in_array($auth, ["session", "public","private"])) {
                    echo "ERROR： $name : __AUTH: [$auth] need from:( session,public,private) \n";
                    exit(1);
                }
                $lines= preg_split("/\n/", var_export($config, true)) ;
                $config_str= "";
                foreach ($lines as $line) {
                    $config_str.=trim($line);
                }
                $cmd_php_str.="\t[ \App\Controllers\\$version_fix$ctrl_class::class, \"$action\", $in_class , $out_class   ,\"$desc\" , \"$auth\" , \"$call_path\", $config_str],\n";
            }
        }
        $cmd_php_str.="];\n";


        return  [$cmd_php_str , $tag_map ,  $action_map, $maintainer_map];
    }
    public static function get_ctrl_class($ctrl)
    {
        $ctrl_class=$ctrl;
        if (App::$new_control_flag) {
            $ctrl_class=Utils::toCamelCase($ctrl);
        }
        return $ctrl_class;
    }
}
