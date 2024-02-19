<?php
namespace App;

class CommonCode
{
    // Go结构体标签解析函数
    public static function parse_go_struct_tags($str)
    {
        // $tagStart = strpos($struct, "`");
        // $tagEnd = strpos($struct, "`", $tagStart + 1);

        $tag_map=[];
        // if ($tagStart !== false && $tagEnd !== false) {

        // $tags = substr($struct, $tagStart + 1, $tagEnd - $tagStart - 1);
        preg_match_all('/([A-Za-z0-9*_]*)[ \t]*:[ \t]*(("[^"]*")|([^ ]+))/', $str, $matches);
        if ($matches) {
            $tag_count=count($matches[0]);
            for ($i=0; $i<$tag_count; $i++) {
                $tag_map[$matches[1][$i]] = $matches[2][$i] ;
            }
        }

        return $tag_map;
    }

    public static function output_proto_code($proto_dir_fix, $all_flag, $work_dir, $output_dir)
    {

        $php_dir="$work_dir/../php" ;
        $go_dir="$work_dir/../go" ;
        $java_dir="$work_dir/../java" ;


        $include_path="$work_dir/.protos/";
        Utils::exec_cmd("mkdir -p $output_dir/$proto_dir_fix");
        $file_list=scandir("$work_dir/$proto_dir_fix");
        $proto_list=[];
        foreach ($file_list as $file) {
            #echo $file."\n";
            if (preg_match("/(.*)\.proto\$/", $file, $matches)) {
                $base_name=$matches[1];
                $proto_list[]=$base_name;
            }
        }

        $need_update_list=[];
        foreach ($proto_list as $proto_name) {
            $from= "$work_dir/$proto_dir_fix/$proto_name.proto";
            $to= "$output_dir/$proto_dir_fix/$proto_name.proto";
            $ret=static::output_proto($proto_dir_fix, $proto_name, $from, $to);
            if ($ret || $all_flag) {
                //echo "do $to\n";
                $need_update_list[]= $to;
            }
        }
        $out_config="";
        if (App::$core_server_type=="go") {
            $out_config.= " --plugin=$work_dir/../../bin/protoc-gen-go --go_out=$go_dir";
        } elseif (App::$core_server_type=="java") {
            $out_config.= " --java_out=$java_dir";
        }

        $need_update_count=count($need_update_list);
        if ($need_update_count>0) {
            $batch_list=  array_chunk($need_update_list, 50);
            $cur_count= 0;
            foreach ($batch_list as $sub_list) {
                $cur_count+= count($sub_list);
                $to_str=join(" ", $sub_list);
                $cmd=  App::$protoc. "   --proto_path=$include_path --proto_path=$work_dir   $to_str --php_out=$php_dir $out_config ";
                echo "deal   $need_update_count  files :$cur_count \n";
                $err_str= system($cmd, $ret);
                if ($ret !=0) { //异常，看看那个
                    echo "出错:". $err_str."\n";
                    exit(100);
                    /*
                    foreach ($sub_list as $file) {
                        $cmd=  App::$protoc. "   --proto_path=$include_path --proto_path=$work_dir   $file   --php_out=$php_dir $out_config ";
                        echo $cmd."\n";
                        system($cmd, $ret);
                        if ($ret !=0) {
                            echo  "编译失败:$cmd\n";
                            exit(100);
                        }
                    }
                    */
                }
            }
        }
    }
    public static function get_cmd_return_cmd($cmd_list, $work_dir, $controller_dir, $error_name_map, $error_value_map, $struct_map)
    {
        //check controller return error
        $cmd_return_map =[];
        foreach (App::$proto_dir_list as $subdir) {
            $cmd_return_map= array_merge($cmd_return_map, static::get_cmd_return_error($controller_dir, $subdir, $error_name_map));
        }


        //读取 proto_validator.json
        $proto_validator=json_decode(file_get_contents($work_dir."/../gen/proto_validator.json"), true);

        $validator_err_map=[];
        foreach ($proto_validator as $v_item) {
            //;
            $err_list=[];
            foreach ($v_item["return_error_list"] as $errno) {
                $err_list[]= $error_value_map[$errno];
            }
            $validator_err_map[$v_item["type"]]=$err_list;
        }


        foreach ($cmd_list as $cmd_item) {
            $cur_cmd_name=$cmd_item["NAME"];
            $struct_in=$cur_cmd_name.".in";
            if (isset($struct_map[$struct_in])) {
                foreach ($struct_map[$struct_in] as $field_item) {
                    $cfg=$field_item[6];
                    if (isset( $cfg["rules"]) ) {
                        $rules=$cfg["rules"];
                        foreach ($rules as $rule) {
                            $rule_name=$rule["type"];
                            if (isset($validator_err_map[$rule_name])) {
                                foreach ($validator_err_map[$rule_name] as $err_item) {
                                    $cmd_return_map[$cur_cmd_name][$err_item["name"]]=$err_item;
                                }
                            }
                        }
                    }
                }
            }
        }
        foreach ($cmd_return_map as &$err_list) {
            $err_list = array_values($err_list);
        }
        return $cmd_return_map;
    }
    public static function deal_proto($all_flag, $work_dir, $controller_dir)
    {
        $work_dir=realpath($work_dir);
        echo "work_dir $work_dir\n";

        //得到配置信息
        $config=App::$config;

        if (!file_exists("$work_dir")) {
            echo"no file  $work_dir, exit;";
            return;
        }

        $php_dir="$work_dir/../php" ;
        $go_dir="$work_dir/../go" ;
        $java_dir="$work_dir/../java" ;

        Utils::exec_cmd("mkdir -p  $php_dir");
        $php_dir=realpath($php_dir);
        $output_dir="$work_dir/.protos/project";
        echo "php_dir:$php_dir \n";
        Utils::exec_cmd("mkdir -p  $php_dir");
        if ($all_flag && file_exists("$php_dir/GPBMetadata")) {
            Utils::exec_cmd("rm -rf $php_dir/Proto");
        }

        foreach (App::$proto_dir_list as $proto_dir_fix) {
            static::output_proto_code($proto_dir_fix, $all_flag, $work_dir, $output_dir);
        }



        $out_config="";
        if (App::$core_server_type=="go") {
            $out_config.= " --plugin=$work_dir/../../bin/protoc-gen-go --go_out=$go_dir";
        }

        if (App::$core_server_type=="java") {
            $out_config.= " --java_out=$java_dir";
        }

        //生成公共的结构体
        $file_list=scandir("$work_dir/common");
        foreach ($file_list as $file) {
            #echo $file."\n";
            if (preg_match("/(.*)\.proto\$/", $file)) {
                $cmd= App::$protoc. "  --proto_path=$work_dir/ $work_dir/common/$file  --php_out=$php_dir $out_config  " ;
                #echo $cmd."\n";
                Utils::exec_cmd($cmd);
            }
        }

        //结构体数据
        $struct_map=static::gen_struct_map($php_dir);


        //生成
        $cmd_map=static::get_cmd_list($work_dir);

        $cmd_list=$cmd_map;
        usort($cmd_list, function ($a, $b) {
            if ($a['CMD'] == $b['CMD']) {
                return 0;
            }
            return($a['CMD']<$b['CMD']) ? -1 : 1;
        });
        list($cmd_php_str , $tag_map, $action_map,$maintainer_map)=PhpCode::gen_cmd_bind_php_str($cmd_list, $struct_map) ;

        Utils::save_file("$php_dir/Proto/cmd_list.php", $cmd_php_str);


        $error_name_map=[];
        $error_value_map=[];
        foreach (($struct_map["error.enum_error"]) as $error_item) {
            /*
              [0] =>
              [1] =>
              [2] => ERR_USER_NOFIND
              [3] => 3002
              [4] => 用户不存在
            */
            $name=$error_item[2];
            $value=$error_item[3];
            $desc=$error_item[4];
            $item=[
                "name" => $name,
                "value" => $value,
                "desc" => $desc,
            ];
            $error_name_map[$name] = $item;
            $error_value_map[$value] = $item;
        }


        $cmd_return_map=  static::get_cmd_return_cmd($cmd_list, $work_dir, $controller_dir, $error_name_map, $error_value_map, $struct_map);






        //生成menu

        GitBookCode::export_git_book($config, $cmd_map, $struct_map, $cmd_return_map, $error_value_map, $menu_tree, $route_fix_config);

        $cmd_list=$cmd_map;
        usort($cmd_list, function ($a, $b) {
            if ($a['CMD'] == $b['CMD']) {
                return 0;
            }
            return($a['CMD']<$b['CMD']) ? -1 : 1;
        });

        $cmd_info=[
            "tag_list" => array_keys($tag_map),
            "maintainer_list" => array_keys($maintainer_map),
            "cmd_list" => $cmd_list,
        ];


        Utils::save_file($php_dir."/cmd.json", json_encode($cmd_info, JSON_PRETTY_PRINT| JSON_UNESCAPED_UNICODE));


        Utils::save_file($php_dir."/info.json", json_encode([
            "struct_map"     =>  Utils::reset_map($struct_map),
            "cmd_return_map" => Utils::reset_map($cmd_return_map),
            "error_list"     =>  Utils::reset_map($error_value_map),
            "menu_tree" =>$menu_tree,
            "route_fix_config"=>$route_fix_config ,
            "config"=> $config,
        ], JSON_PRETTY_PRINT|  JSON_UNESCAPED_UNICODE));


        $err_item_str="";
        foreach ($error_value_map as $err_item) {
            $err_item_str.="\t". $err_item["value"]. "=>\"" . addslashes(trim($err_item["desc"])) . "\",\n" ;
        }
        $error_php_str="<?php
return [
$err_item_str
];
";
        Utils::save_file($php_dir."/Proto/error.php", $error_php_str);

        //deal go
        if (App::$core_server_type=="go") {
            GoCode::deal_go($go_dir, $cmd_map);
        }

        //deal java
        if (App::$core_server_type=="java") {
            JavaCode::deal($java_dir, $cmd_map);
        }

        return  $action_map;
    }

    public static function get_cmd_list($work_dir)
    {
        $cmd_map=[];
        foreach (App::$proto_dir_list as $proto_dir_fix) {
            $dir_fix_str="";
            if ($proto_dir_fix) {
                $dir_fix_str="$proto_dir_fix.";
            }
            $file_list=scandir($work_dir."/$proto_dir_fix");
            foreach ($file_list as $filename) {
                if (preg_match("/^[a-zA-Z0-9].*__.*\.proto$/", $filename)) {
                    $proto_name= $dir_fix_str.substr(basename($filename), 0, -6);
                    $cmd_map[$proto_name]=static::gen_proto_info("$work_dir/$proto_dir_fix/$filename", $proto_name);
                }
            }
        }


        return $cmd_map;
    }


    public static function gen_proto_info($filename, $proto_name)
    {
        $lines=file($filename);
        $cmd_info=[
            "NAME" => $proto_name,
            "DESC" => "",
            "TITLE" => "",
            "MAINTAINER" => [],
            "TREE" => "",
            "TAGS" => [],
            "CMD" =>0,
            "OUT_EXAMPLE" =>"",
            "IN_EXAMPLE" =>"",
            "CONFIG" =>[],
            "AUTH" =>"",
            "METHOD" =>"", //GET|POST, WEBSOCKET
            "PROJECT" =>"",
        ];
        $block_name="";
        $block_info="";

        foreach ($lines as $line) {
            if (preg_match("/__(PROJECT|DESC|CMD|TAGS|TREE|TITLE|MAINTAINER|AUTH|METHOD)[ \t]*:(.*)/", $line, $matches)) {
                $title= $matches[1];
                $value= trim($matches[2]);
                if ($title=="TAGS" || $title=="MAINTAINER") {
                    $arr=preg_split("/,/", $value);
                    $tags_list=[];
                    foreach ($arr as $tag) {
                        $tag=trim($tag);
                        if ($tag) {
                            $tags_list[]=$tag;
                        }
                    }
                    $cmd_info[$title]=$tags_list;
                } else {
                    $cmd_info[$title]=trim($value);
                }
            } elseif (preg_match("/^[ \t]*```[ \t]*([a-z0-9A-Z]+)*/", $line, $matches)) {
                $tmp_block_name= @$matches[1];
                // echo  "tmp_block_name: $tmp_block_name\n";
                if (!$tmp_block_name) {
                    // echo " name= $block_name, block_info: $block_info\n; ";
                    if ($block_name=="out") {
                        $cmd_info["OUT_EXAMPLE"]=$block_info;
                    } elseif ($block_name=="in") {
                        $cmd_info["IN_EXAMPLE"]= $block_info;
                    } elseif ($block_name=="desc") {
                        $cmd_info["DESC"]= preg_replace("/>```/", "```", $block_info);
                    } elseif ($block_name=="config") {
                        $block_info=  trim($block_info);
                        $config=[];
                        if ($block_info) {
                            $config=json_decode($block_info, true);

                            if (json_last_error()!=JSON_ERROR_NONE) {
                                echo "$filename :config  解析 成json 出错:$block_info  出错 ";
                                exit(1);
                            }
                        }
                        $cmd_info["CONFIG"]= $config;
                    }
                    $block_name="";
                    $block_info="";
                } else {
                    $block_name=$tmp_block_name;
                }
            } elseif ($block_name) {
                // echo "add black line : $line  \n";
                $block_info.=$line;
            }
        }
        return $cmd_info;
    }

    public static function output_proto($proto_dir_fix, $proto_name, $from, $to)
    {
        $go_dir_str="";
        $php_dir_str="";
        $package_version_str="";
        $java_package_version_str="";
        if ($proto_dir_fix) {
            $php_dir_str=Utils::toCamelCase($proto_dir_fix)."\\\\";
            $go_dir_str="$proto_dir_fix/";
            $package_version_str="$proto_dir_fix.";
            $java_package_version_str=".$proto_dir_fix";
        }


        $option_str=
                   "option php_namespace = \"Proto\\\\Project\\\\$php_dir_str$proto_name\"; \n".
                   "option java_package = \"com.vipthink.gen.proto$java_package_version_str\"; \n".
                   "option go_package = \"proto/$go_dir_str$proto_name\"; \n" .
                   "package proto.project.$package_version_str$proto_name;\n" ;
        $data=file_get_contents($from)."\n$option_str";
        return Utils::save_file($to, $data);
    }


    //得到返回的错误码
    public static function get_cmd_return_error($controller_dir, $subdir, $error_name_map)
    {
        if ($subdir) {
            if (App::$core_server_type=="php") {
                $controller_dir=$controller_dir."/". Utils::toCamelCase($subdir);
            } else {
                $controller_dir=$controller_dir."/". $subdir;
            }
        }
        $file_list=scandir($controller_dir);
        $error_map=[];
        $version_fix="";
        if ($subdir) {
            $version_fix="$subdir.";
        }
        foreach ($file_list as $file) {
            if (preg_match("/^([a-zA-Z].*)\.php\$/", $file, $matches)) {
                $ctrl= $version_fix.Utils::str_to_under_score($matches[1]);
                $lines=file("$controller_dir/$file");
                $cur_cmd_name="";
                foreach ($lines as $line) {
                    if (preg_match("/^[ \t]+public[ \t]+function[ \t]+([a-zA-Z0-9_]*)[ \t]*\\(/i", $line, $matches)) {
                        $action= $matches[1];
                        $cur_cmd_name="{$ctrl}__{$action}";
                        $error_map[$cur_cmd_name]=[];
                    } elseif (preg_match("/return.+ERR::([a-zA-Z0-9_]*)/i", $line, $matches)) {
                        $error_name= $matches[1];
                        if ($cur_cmd_name && $error_name !="ERR_SUCC") {
                            $error_map[$cur_cmd_name] [$error_name]=  $error_name_map[$error_name] ;
                        }
                    }
                }
            }
        }

        foreach ($error_map as $k => &$v) {
            if (count($v)==0) {
                unset($error_map[$k]);
            } else {
                $v=array_values($v);
            }
        }
        return $error_map;
    }


    public static function gen_struct_map($php_dir)
    {
        $ret=Utils::exec_cmd("find  $php_dir/Proto  -name \"*.php\" ");
        $file_list=preg_split("/\n/", $ret);

        $ctags = new PHPCtags([]);
        $file_count=count($file_list);
        foreach ($file_list as $i => $src_file) {
            if ($i%100==0) {
                echo "do struct files $file_count :$i \n";
            }
            if ($src_file) {
                static::gen_struct($struct_map, $ctags, $src_file);
            }
        }
        return $struct_map;
    }


    public static function gen_struct(&$struct_map, $ctags, $src_file)
    {
        //得到 class_name
        if (preg_match(
            "/\/Project\/(([^\/]*)\/)?([^\/]*)\/([^\/]*)\.php$/",
            $src_file,
            $matches
        )) {
            $struct_name=   strtolower($matches[3]).".".$matches[4];
            if ($matches[2]) {
                $struct_name=Utils:: str_to_under_score($matches[2]).".". $struct_name;
            }
        }
        //echo $struct_name. "\n";
        $field_list = $ctags->process_single_file($src_file);
        $field_ret=[];
        foreach ($field_list as $item) {
            $field_name = $item[0];
            //echo "check :$field_name:". $item[1]."\n";

            $desc_list =  preg_split("/\n/", $item[1]);
            $desc_list_len=count($desc_list);
            $desc="" ;
            if ($desc_list_len<2) {
                continue;
            }

            for ($i=1; $i< $desc_list_len -3; $i++) {
                $desc.=substr($desc_list[$i], 6)."\n";
            }
            $desc=trim($desc);

            //参数验证
            $rules=[];
            if (strpos($desc, "CFG=")!==false) { //存在 RULE
                //处理desc
                $json_str="";
                $desc=preg_replace_callback("/CFG=(.*})/", function ($args) use (&$json_str) {
                    $json_str =trim($args[1]);
                        return "";
                }, $desc);

                $cfg=@json_decode($json_str, true);
                if (is_array($cfg)) {
                    $rules["cfg"]=$cfg;
                } else {
                    echo "$src_file :字段 $field_name 出现  配置 `CFG` ,解析 成json 出错:$json_str| desc:  $desc ";
                    exit(1);
                }
            }



            if (preg_match("/<code>map<(.+)[ ]*,[ ]*(.+)> (.+) = ([0-9]+);<\/code>/", $desc_list[$desc_list_len-2], $matches)) {
                //* Generated from protobuf field <code>map<string, .proto.project.flow__get_flow_branch_switch_value.arg_config_item> arg_config = 1;</code>
                $repeated="";
                $map_key_type= $matches[1];
                $type =trim($matches[2], ".");
                if (strpos($type, "proto.project")===0) {
                    //proto.project.flow__get_function_list.function_item  =>  flow__get_function_list.function_item
                    $type=substr($type, 14);// "proto.project.".length
                }
                $field_name =trim($matches[3]);
                $field_id =trim($matches[4]);
                $field_ret[]=[ $repeated,  $type , $field_name ,  $field_id , $desc , $map_key_type ,$rules ];
            } elseif (preg_match("/<code>(repeated |)(.+) (.+) = ([0-9]+);<\/code>/", $desc_list[$desc_list_len-2], $matches)) {
                $repeated=trim($matches[1]);
                $type =trim($matches[2], ".");
                if (strpos($type, "proto.project")===0) {
                    //proto.project.flow__get_function_list.function_item  =>  flow__get_function_list.function_item
                    $type=substr($type, 14);// "proto.project.".length
                }
                $field_name =trim($matches[3]);
                $field_id =trim($matches[4]);
                $field_ret[]=[ $repeated,  $type , $field_name ,  $field_id , $desc ,null ,$rules ];
            } elseif (preg_match("/<code>(.+) = ([0-9]+);<\/code>/", $desc_list[$desc_list_len-2], $matches)) {
                $field_name =trim($matches[1]);
                $field_value =trim($matches[2]);
                $field_ret[]=[ "",  "", $field_name ,  $field_value , $desc , null  ,$rules];
            }
        }

        $struct_map["$struct_name"] = $field_ret;
    }
}
