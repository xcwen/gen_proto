<?php

namespace App;

class GitBookCode
{
    public static function get_gitbook_key_info()
    {
        $check_list=[
            "title",
            "desc",
            "header",
            "in",
            "out",
            "foot",
            "err",
        ];
        $list=[];
        $check_str_map=[];
        foreach ($check_list as $item) {
            $up_str=strtoupper($item);
            $begin_str= "__PROTO_{$up_str}_BEGIN__";
            $end_str= "__PROTO_{$up_str}_END__";
            $check_str_map[$begin_str]=[ "type"=>"begin", "name"=>$item,  "begin"=> $begin_str, "end"=>$end_str, ];
            $check_str_map[$end_str]=[ "type"=>"end", "name"=>$item,  "begin"=> $begin_str, "end"=>$end_str, ];
            $list[]=$begin_str;
            $list[]=$end_str;
        }
        $regex_str= "(". join("|", $list) .")";
        return array( $regex_str, $check_str_map    );
    }


    public static function reset_file_data($file, $data_map, $check_str_map, $regex_str)
    {

        $file_data=file_get_contents($file);
        $line_arr=preg_split("/\n/", $file_data);
        $next_check_key="";
        $output_flag=true;
        $out_line_arr=[];

        foreach ($line_arr as $index => $line) {
            $index=$index+1;

            if (preg_match($regex_str, $line, $matches)) {
                $str=$matches[0];
                if ($next_check_key) { //要目标
                    if ($next_check_key== $str) {
                        $out_line_arr[]= $data_map[$check_str_map[$next_check_key]["name"]];
                        $out_line_arr[]=$line ;
                        $next_check_key="";
                        $output_flag=true;
                    } else {
                        echo "$file:$index 期待 $next_check_key ,  却找到  $str \n; ";
                        exit(255) ;
                    }
                } else {
                    $info= $check_str_map[$str];
                    if ($info["type"]=="begin") {
                        $out_line_arr[]=$line ;
                        $next_check_key= $info["end"];
                        $output_flag=false;
                    } else { //end
                        echo "$file:$index 期待 {$info["begin"]},  却找到  $str \n; ";
                        exit(255) ;
                    }
                }
            } else {
                if ($output_flag) {
                    $out_line_arr[]=$line ;
                }
            }
        }
        $new_file_data= join("\n", $out_line_arr);

        if ($new_file_data==$file_data) {
            //echo "no need  update  $file  \n";
        } else {
            file_put_contents($file, $new_file_data);
            echo " update  $file  \n";
        }
    }

    public static $proto_validator_map=[];

    public static function init_proto_validator_map()
    {
        $proto_validator_config=@json_decode(@file_get_contents("./gen/proto_validator.json"), true);
        $proto_validator_config=$proto_validator_config??[];
        foreach ($proto_validator_config as $v_item) {
            static::$proto_validator_map[$v_item["type"] ]=$v_item;
        }
    }

    public static function export_git_book($config, &$cmd_map, &$struct_map, &$cmd_return_map, &$error_value_map, &$menu_tree, &$route_fix_config)
    {

        static::init_proto_validator_map();


        $export_git_book_config=$config["export_git_book"];
        $menu_config=@$config["menu"];
        $project_name = trim(@$export_git_book_config["project_name"]);

        //"default_route_fix" =>  "route__go_cc_defalut", //默认的路由前缀, .proto 里没有配置时使用



        $auth_config=[
            ""=> "未定义",
            "session"=>' <span   class="cmd-desc" style="color:green;"  > `session`| 需要 token/session 才能访问 </span>',
            "private"=>' <span   class="cmd-desc" style="color:blue;"  > `private` | 服务器内网才可以访问  </span>',
            "public"=>' <span   class="cmd-desc"  style="color:red;"  > `public`| 公网无需鉴权就能访问,请注意是否有安全问题  </span>',
        ];

        list(  $regex_str ,$check_str_map )=static::get_gitbook_key_info();

        $menu_tree=[];
        foreach ($export_git_book_config["route_fix"] as $default_route_fix => $route_config) {
            $md_cmd_list=[];
            $export_all_cmd= @$route_config["export_all_cmd"];

            //有yapi配置
            if (@$route_config["yapi_project_token"]) {
                $route_fix_config [$default_route_fix]=[
                    "yapi_openapi_url" =>@$route_config["yapi_openapi_url"],
                    "yapi_project_name" =>@$route_config["yapi_project_name"],
                    "yapi_project_token" =>@$route_config["yapi_project_token"],
                ];
            }

            $gitbook_dir=@$route_config["gitbook_dir"];
            if ($gitbook_dir) {
                $gitbook_dir= str_replace("~", getenv("HOME"), $gitbook_dir);
                $gitbook_dir= rtrim($gitbook_dir, "/");

                if (!file_exists($gitbook_dir)) {
                    echo "指定的 gitbook_dir: $gitbook_dir, 不存在\n  ";
                    mkdir($gitbook_dir, 0755, true);
                }

                $git_project_name = basename(dirname($gitbook_dir));

                $md_fix="./$git_project_name/project/$project_name";
                $gitbook_dir=  $gitbook_dir."/".$project_name  ;

                @mkdir($gitbook_dir);
            }


            $cmd_list=@$route_config["cmd_list"]??[];
            $tag_list=@$route_config["tag_list"]??[];
            if (count($tag_list)>0) { //通过标签
                foreach ($cmd_map as $cmd => $cmd_info) {
                    $tags= $cmd_info["TAGS"]??[];
                    //echo "11111111111\n";
                    //print_r($tags);
                    if (count(array_intersect($tags, $tag_list))>0) {
                        $cmd_list[]=$cmd;
                    }
                }
                $cmd_list= array_unique($cmd_list);
            }


            if ($gitbook_dir) {
                $url_env_config=$route_config["url_env_config"];
                $url_env_json = json_encode($url_env_config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            }

            if ($export_all_cmd) { //输出所有
                $cmd_list=[];


                $exclude_cmd_list= @$route_config["exclude_cmd_list"];
                if (!$exclude_cmd_list) {
                    $exclude_cmd_list=[];
                }

                $exclude_cmd_map=[];


                foreach ($exclude_cmd_list as $cmd_name) {
                    $exclude_cmd_map[$cmd_name]=true  ;
                }


                foreach (array_keys($cmd_map) as $cmd) {
                    //过滤不包含
                    if (!isset($exclude_cmd_map[$cmd ])) {
                        $cmd_list[]=$cmd;
                    }
                }
            }


            foreach ($cmd_list as $cmd) {
                //echo "do $cmd \n";
                //生成单一协议
                if ($gitbook_dir) {
                    static::gen_gitbook_one_proto($default_route_fix, $cmd, $cmd_map, $regex_str, $check_str_map, $struct_map, @$cmd_return_map[$cmd], $url_env_json, $auth_config, $gitbook_dir);
                }


                $cmd_info= $cmd_map["$cmd"];

                if ($gitbook_dir) {
                    $cmd_info["md"]= "$md_fix/$cmd.md";
                }
                $cmd_map["$cmd"]["route_fix_list"][]= $default_route_fix;
                $md_cmd_list[]=$cmd_info;
            }

            $md_menu_tree= static::gen_menu_tree($md_cmd_list, $menu_config);

            if ($gitbook_dir) {
                if (count($md_menu_tree)) {
                    //生成summary文件内容
                    $summary_str = static::gen_summary_data($md_menu_tree, "");
                    if ($default_route_fix=="") {
                        $summary_file="$gitbook_dir/../../SUMMARY_{$project_name}.md";
                    } else {
                        $summary_file="$gitbook_dir/../../SUMMARY_{$project_name}_$default_route_fix.md";
                    }
                    file_put_contents($summary_file, $summary_str);
                    $summary_file= realpath($summary_file) ;
                    echo "  SUMMARY 模板保存 :  $summary_file \n";
                }
            }
        }

        $menu_tree= static::gen_menu_tree($cmd_map, $menu_config);
    }

    public static function gen_summary_data($menu_tree, $pix)
    {
        $str="";
        foreach ($menu_tree as $v) {
            if (isset($v['list'])) {
                $str .= $pix."* [{$v['title']}]()\n";
                $str.=static::gen_summary_data($v["list"], $pix."    ");
            } else {
                $str .= $pix."* [{$v['title']}]({$v['md']})\n";
            }
        }
        return   $str;
    }

    public static function gen_menu_tree($cmd_list, $menu_config)
    {
        $menu_tree=[];
        foreach ($cmd_list as $item) {
            $tree= trim(trim($item["TREE"]), "/");
            $project=trim($item["PROJECT"]);
            if ($project) {
                $tree= trim("$project/$tree", "/");
            }
            if (!$tree) {
                $tree="未分类";
            }
            $tmp_tree_items= preg_split("/\//", $tree);

            //往树里写入数据
            $tmp_menu_tree=&$menu_tree;
            while (count($tmp_tree_items)) {
                $title= array_shift($tmp_tree_items);
                if (!isset($tmp_menu_tree[$title])) {
                    $tmp_menu_tree[$title] =["title" => $title, "list" => [] ];
                }
                $tmp_menu_tree=&$tmp_menu_tree[$title]["list"];
            }
            $node_item=[];
            $node_item["title"] =$item["TITLE"];
            $node_item["cmd"] =$item["NAME"];
            $node_item["md"] =@$item["md"];
            $tmp_menu_tree[]= $node_item;
        }
        static::sort_menu_tree($menu_tree);
        $ret_menu_tree=[];
        $project_map=[];

        //配置的项目放前面
        if ($menu_config) {
            foreach ($menu_config as $project_item) {
                $project_name=$project_item["project"];
                $project_map[$project_name]=true;
                foreach ($menu_tree as $key => $node_item) {
                    if ($node_item["title"]=="$project_name") {
                        $node_item["title"]= $project_item ["title"]. "|".$node_item["title"];
                        $ret_menu_tree[]=$node_item;
                        unset($menu_tree[$key]);
                    }
                }
            }
        }

        return array_merge($ret_menu_tree, $menu_tree);
    }
    public static function sort_menu_tree(&$menu_tree)
    {
        //自己排序
        usort($menu_tree, function ($a, $b) {
            //目录优先
            if ((@$a["list"] && @$b["list"])
                || (!@$a["list"] && !@$b["list"])
            ) {
                return $a["title"] < $b["title"] ? -1 : 1;
            } elseif (@$a["list"]) {
                return -1;
            } else {
                return 1;
            }
        });

        //自己子节点
        foreach ($menu_tree as &$sub_tree) {
            if (isset($sub_tree["list"])) {
                static::sort_menu_tree($sub_tree["list"]);
            }
        }
    }

    public static function gen_summary_str(&$str, $data, $pix = "    ")
    {

        $ret_data=[];
        foreach ($data as $k => $v) {
            if (isset($v['title'])) {
                $ret_data[]=$v;
            } else {
                $ret_data[]=["title" =>$k, "list"=>$v ];
            }
        }

        usort($ret_data, function ($a, $b) {
            //目录优先
            if ((@$a["list"] && @$b["list"])
                || (!@$a["list"] && !@$b["list"])
            ) {
                return $a["title"] < $b["title"] ? -1 : 1;
            } elseif (@$a["list"]) {
                return -1;
            } else {
                return 1;
            }
        });

        foreach ($ret_data as $k => $v) {
            if (isset($v['list'])) {
                $str .= $pix."* [{$v['title']}]()\n";
                gen_summary_str($str, $v["list"], $pix."    ");
            } else {
                $str .= $pix."* [{$v['title']}]({$v['md']})\n";
            }
        }

        return $str;
    }

    public static function gen_gitbook_one_proto($default_route_fix, $cmd, &$cmd_map, $regex_str, &$check_str_map, &$struct_map, $cmd_error_list, $url_env_json, $auth_config, $gitbook_dir)
    {

        $cmd_info = @$cmd_map["$cmd"];
        if (!$cmd_info) {
            print_r($cmd);
            echo "$cmd 不存在\n";

            exit(255);
        }

        $proto_data_map=[
            "title"=>"# ". $cmd_info["TITLE"] ,
            "header"=>"",
            "desc"=> $cmd_info["DESC"],
            "in"=>"",
            "out"=> "",
            "foot"=>"",
            "err"=> "",
        ];

        $call_url= str_replace(".", "/", "/". str_replace("__", "/", $cmd));

        $maintainer_str="" ;
        foreach ($cmd_info["MAINTAINER"] as $maintainer) {
            $maintainer_str.="`". $maintainer ."` ";
        }

        $auth=$cmd_info["AUTH"];
        $method=$cmd_info["METHOD"];
        if (!$method) {
            $method="GET|POST";
        }

        $auth_str = @$auth_config[$auth];
        if (!$auth_str) {
            echo " $cmd.proto: 未定义 选项 __AUTH: [$auth] \n";
            exit(255);
        }


        $proto_data_map["header"]=<<<END

<div style="margin-top:30px;" ></div>
<span class="title">请求方式:</span>  $method

<span class="title">请求地址:</span> <select class="select" id="id_env_header" >
  <option value ="dev">dev环境</option>
  <option value="test" >sht环境</option>
  <option value="prev" >sho环境</option>
  <option value="release">正式环境</option>
</select>  <span id="id_call_url">$call_url</span>

<span class="title">维护人员:</span>   $maintainer_str

<span class="title">权限说明:</span>  $auth_str

-----------------------------------------

END;

        //foot
        $proto_data_map["foot"]=<<<END

<script>

    var env_url_config=$url_env_json;


    var call_url="$call_url";
    var route_fix="$default_route_fix" ;
</script>

<script src="../../../js/website.js"></script>


END;


        $struct_in_name="$cmd.in";
        $struct_out_name="$cmd.out";
        $struct_in=@$struct_map[$struct_in_name];
        $struct_out=@$struct_map[$struct_out_name];
        if (!$struct_in) {
            $struct_in=[];
        }
        if (!$struct_out) {
            $struct_out=[];
        }





        //生成 in 数据
        $in_str="";
        $in_str.=<<<END
<span class="title" >请求实例:</span>


```json
{$cmd_info["IN_EXAMPLE"]}
```

END;


        $in_str.=<<<END
<span class="title" >参数说明:  <a  href="javascript:;" id="id_do_test"   >开启测试 </a> </span>
        <span  style="margin-left:30px; display:none; " id= "id_test_bar"  > <a   href="javascript:;" id="id_test"  style=" margin-right:30px; font-weight:bolder;" >发送</a>
        <i style="margin-left:20px;font-style: normal; font-weight: bold; ">sessionid: </i> <input id="id_sessionid" > </input>
        <i style="margin-left:20px;font-style: normal; font-weight: bold; "> 环境</i> <select class="select" id="id_env" >
        <option value ="dev">dev环境</option>
        <option value="test" >sht环境</option>
        <option value="prev" >sho环境</option>
        <option value="release">正式环境</option>
        </select></span>



END;


        $in_str.=static::gen_struct_git_table($struct_in, $struct_map, true);
        $proto_data_map["in"]=$in_str;



        //out
        $out_str="";
        $out_str.=<<<END
<br/>
<br/>
<span class="title"   >返回说明:</span>


```json
{$cmd_info["OUT_EXAMPLE"]}
```


<span class="title" >返回data说明:</span>


END;
        $out_str.=static::gen_struct_git_table($struct_out, $struct_map, false);
        $proto_data_map["out"]=$out_str;


        $proto_data_map["err"]= static::gen_error_list($cmd_error_list);


        $filename="$gitbook_dir/$cmd.md";


        if (!file_exists($filename)) {
            copy("./utils/gitbook_exmaple.md", $filename);
        }

        static::reset_file_data($filename, $proto_data_map, $check_str_map, $regex_str);
    }


    public static function gen_struct_git_table_ex($field_fix_str, &$struct, &$struct_map, $input_flag, $struct_find_list)
    {

        $ret_str="";
        foreach ($struct as $item) {
            $field_type = $item[1];
            $desc = $item[4];
            $rule_list = $item[6];
            $enum_type = "";
            if (preg_match("/.*ENUM[ \t]*=[ \t]*([a-z0-9_A-Z]*)/", $desc, $matches)) {
                $enum_type = $matches[1];
            }
            $required =false;
            if (preg_match("/(.*)(\\[required\\])(.*)/", $desc, $matches)) {
                $required =true;
                $desc= $matches[1].$matches[3];
            }


            $desc=trim($desc);
            //处理 |
            $desc=str_replace("|", "&#124;", $desc);
            $desc=str_replace("\n", ";", $desc);
            $desc=str_replace("{{", "\\{\\{", $desc);
            $desc=str_replace("}}", "\\}\\}", $desc);
            if ($desc=="") {
                $desc="-";
            }

            $name = $item[2];
            $map_key_type = @$item[5];
            $array_flag = $item[0]=="repeated";
            $input_str="";
            if ($input_flag) {
                $input_str="<div class=\"test_mode_open\" style=\"display:none;\"  > <input data-name=\"$name\"/> </div> |";
            }

            $field_type_str =  $field_type;
            if ($map_key_type) {
                $field_type_str = "map:$map_key_type -> $field_type";
            }
            $required_str="";
            if ($required) {
                $required_str="是";
            }

            $rule_str="";
            if (count($rule_list)>0) {
                foreach ($rule_list as $rule_item) {
                    $type=@$rule_item["type"];
                    $type_desc=@static::$proto_validator_map[$type]["desc"];
                    if ($type=="required") {
                        $required_str="是";
                    } else {
                        if (isset($rule_item["config"])) {
                            $config_str=json_encode($rule_item["config"]);
                            $rule_str="`$type_desc:$type($config_str)`";
                        } else {
                            $rule_str="`$type_desc:$type`";
                        }
                    }
                }
            }

            if ($array_flag) {
                $field_type_str .="[数组]";
            }

            if ($enum_type) {
                $field_type_str .= "<br/><a href=\"javascript:;\" class=\"opt_enum_type\" data-enum_type=\"$enum_type\" >枚举</a>";
            }


            if ($input_flag) {
                $ret_str.="| $field_fix_str $name |  $input_str   $field_type_str |   $required_str |  $rule_str | $desc |\n";
            } else {
                $ret_str.="| $field_fix_str $name |  $input_str   $field_type_str |    $desc |\n";
            }


            $type_strcut_info = @$struct_map[$field_type];

            if ($type_strcut_info) {
                if (! in_array($field_type, $struct_find_list)) {
                    $next_struct_find_list= $struct_find_list;
                    $next_struct_find_list[]= $field_type;
                    $ret_str.= static:: gen_struct_git_table_ex($field_fix_str . "&emsp;&emsp;", $type_strcut_info, $struct_map, $input_flag, $next_struct_find_list);
                }
            }
        }

        return $ret_str;
    }
    public static function gen_error_list($cmd_error_list)
    {

        $str=<<<END

<br/>
<br/>
<span class="title" >错误码列表:</span>
<br/>

| 错误码值 |  错误码 |   说明 |
| :--- | :--- | :--- |

END;

        if (!$cmd_error_list) {
            $str.= "| 无 |  - | - |\n";
        } else {
            foreach ($cmd_error_list as $item) {
                $desc=trim($item["desc"]);
                $str.= "| {$item["value"]} |  {$item["name"]}  |  {$desc}  |\n";
            }
        }



        return $str;
    }


    public static function gen_struct_git_table(&$struct, &$struct_map, $input_flag)
    {

        $fix_header="";
        $fix="";
        $fix_td="-";
        if ($input_flag) {
            $fix_header="<div class=\"test_mode_open\" style=\"display:none;\"  > 值</div> |";
            $fix=":--- |";
            $fix_td="<div class=\"test_mode_open\" style=\"display:none;\"   >-</div>|";
        }
        $str="";


        if ($input_flag) {
            $str.= "| 字段名 |  $fix_header   类型 | 必须 | 参数校验 |  说明 |\n";
            $str.= "| :--- | $fix :--- | :--- | :--- |\n";
        } else {
            $str.= "| 字段名 |  $fix_header   类型 |   说明 |\n";
            $str.= "| :--- | $fix :--- | :---  |\n";
        }
        $list_str= static:: gen_struct_git_table_ex("", $struct, $struct_map, $input_flag, []) ;
        if ($list_str) {
            $str.=$list_str;
        } else {
            $str.= "| 无 | $fix_td - | - | - |\n";
        }

        return $str;
    }
}
