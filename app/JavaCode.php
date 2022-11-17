<?php
namespace App;

class JavaCode
{

    public static function deal($java_dir, $cmd_map)
    {
        /*
          package proto

          import (
          "proto/account__login"
          "proto/lp_common__login_with_passwd"
          )

          type LpCommonLoginWithPasswdIn lp_common__login_with_passwd.In
          type LpCommonLoginWithPasswdOut lp_common__login_with_passwd.Out
          type AccountLoginIn account__login.In
          type AccountLoginOut account__login.Out
        */

        /*

          {
          Path:        "/account/login",
          Ctrl:        "account",
          Action:      "login",
          NewCtrlFunc: func() interface{} { return new(controllers.Account) },
          NewInFunc:   func() interface{} { return new(proto.AccountLoginIn) },
          NewOutFunc:  func() interface{} { return new(proto.AccountLoginOut) },
          },

        */

        $import_str="";
        $type_str="";
        $cmd_info_str="";
        $ctrl_list=[];
        //$version_package_str="";
        //$version_package_="";
        $version_map=[];

        foreach ($cmd_map as $proto_name => $cmd_info) {
            /*
            (
                [0] => v1.test__abc_x
                [1] => v1.
                [2] => v1
                [3] => test
                [4] => abc_x
            )
            */

            preg_match("/((.*)\.)?(.*)__(.*)/", $proto_name, $arr);
            $version=$arr[2];

            $ctrl=$arr[3];
            $action=$arr[4];

            $version_str="";
            $call_url="/$ctrl/$action";
            $cname=utils::toCamelCase("{$ctrl}_$action");

            if ($version) {
                $cname="$version.$cname";
                $version_map[]=$version;
                $version_str="$version.";
                $call_url="/$version/$ctrl/$action";
            }
            $c_ctrl=utils::toCamelCase($ctrl) ;
            $c_version=utils::toCamelCase($version) ;
            $d_action=lcfirst(utils::toCamelCase($action)) ;

            $ctrl_list[ "$version_str$c_ctrl"]=true;

            $auth=$cmd_info["AUTH"];
            $auth_type="NULL";
            if ($auth=="public") {
                $auth_type="PUBLIC";
            } elseif ($auth=="private") {
                $auth_type="PRIVATE";
            } elseif ($auth=="session") {
                $auth_type="SESSION";
            }

            $proto_package="com.vipthink.gen.proto";
            $ctrl_package="com.vipthink.demo.controllers";


            $cmd_info_str.="
      new CmdInfo(\"$call_url\", \"$version\",\"$ctrl\", \"$action\", Auth.$auth_type,
          callParams->  {callParams.in= $proto_package.$cname.in.newBuilder(); callParams.out=  $proto_package.$cname.out.newBuilder() ; return null; } ,
          callParams -> {return SpringUtil.getBean( $ctrl_package.$version_str$c_ctrl.class).$d_action(( $proto_package.$cname.in.Builder) callParams.in, ( $proto_package.$cname.out.Builder) callParams.out   ) ;}),

";

            if ($version) {
                $package_name="{$version}__{$ctrl}__{$action}";
                $import_str.="     $package_name  \"server/gen/proto/$version/{$ctrl}__{$action}\" \n";

                $type_str.="// {$cname}In x\n";
                $type_str.="type {$cname}In $package_name.In\n";
                $type_str.="// {$cname}Out x\n";
                $type_str.="type {$cname}Out $package_name.Out\n";
            } else {
                $import_str.="    \"server/gen/proto/$proto_name\"\n";
                $type_str.="// {$cname}In x\n";
                $type_str.="type {$cname}In $proto_name.In\n";
                $type_str.="// {$cname}Out x\n";
                $type_str.="type {$cname}Out $proto_name.Out\n";
            }
        }

        $ctrl_str="";
        $version_map=[];
        foreach (array_keys($ctrl_list) as $c_ctrl) {
            $arr=preg_split("/\./", $c_ctrl);
            $version="";
            if (count($arr)>1) {
                $version=$arr[0];
                $version_map[$version]=true;
                $c_ctrl=$arr[1];
            }
            $c_version=Utils::toCamelCase($version);
            if (!$version) {
                $version="controllers";
            }

            $ctrl_str.="
// $c_version$c_ctrl xx
var $c_version$c_ctrl = $version.$c_ctrl{Controller}
";
        }


        $cmd_info_def="
package com.vipthink.gen.proto.common;
import com.vipthink.core.proto.CmdInfo;
import com.vipthink.core.proto.CmdInfo.Auth;
import com.vipthink.core.proto.CmdInfo.CallParams ;
import com.vipthink.core.utils.SpringUtil;

public class CmdList {
   public static CmdInfo[] list={
$cmd_info_str
   };

}

";


        Utils::exec_cmd("mkdir -p $java_dir/com/vipthink/gen/proto/common/");
        Utils::save_file("$java_dir/com/vipthink/gen/proto/common/CmdList.java", $cmd_info_def);
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
            $version_fix=  $version.".";
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
        $typeMap=[
            "uint64" => "long",
            "uint32" => "int",
            "uint16" => "int",
            "uint8" => "int",
            "int64" => "long",
            "int32" => "int",
            "int16" => "int",
            "int8" => "int",
            "double" => "double",
            "float" => "float",
            "string" => "String",

        ];


        foreach ($in_obj as $item) {
            $name=$item["2"];
            $type =$item[1];
            $name_len=strlen($name);
            $fix_space="";
            $type_str=@$typeMap[ $type];
            for ($i=0; $i< $max_field_len-$name_len; $i++) {
                $fix_space.=" ";
            }

            $type_fix_space="";
            for ($i=0; $i<7 - strlen($type_str); $i++) {
                $type_fix_space.=" ";
            }


            $set_str.="        $type_str$type_fix_space $name".  $fix_space ." = in.". static::gen_get_function_field_name($name). "();\n" ;
        }
        $d_action=lcfirst(utils::toCamelCase($action)) ;


        $c_ctrl_action= utils::toCamelCase($ctrl_action);

        return "
    /**
     * $desc
     *
     */
    public Object $d_action($c_ctrl_action.in.Builder in, $c_ctrl_action.out.Builder out ) {
$set_str

        return this.outputErr(\" 自动生成代码, [$version_fix$ctrl_action]还未实现!\");
    }
";
    }

    public static function gen_control_action_map($action_map, $controller_dir)
    {
        $json_out="/tmp/ctrl.json";
        $jar=App::$work_dir."/../../bin/javaparser-class.jar";
        $ret=Utils::exec_cmd("java -jar  $jar $controller_dir  $json_out ");
        echo "java deal  ctrl ret: $ret \n ";

        $tmp_data= @json_decode(file_get_contents($json_out), true);
        foreach ($tmp_data as $key => $item) {
            if ($item===false) {
                $func_map[$key]=false;
            } else {
                foreach ($item as $action) {
                    $func_map[$key][ Utils::str_to_under_score($action)]=true;
                }
            }
        }
        //生成;
        foreach ($action_map as $name => $func_str) {
            preg_match("/((.*)\\.)?((.*)__(.*))/", $name, $arr) ;
            $version=$arr[2];
            $ctrl=$arr[4];
            $action=$arr[5];
            $namespace_version="";
            $version_fix="";
            $ctrl_class=$ctrl;
            $ctrl_class=Utils::toCamelCase($ctrl);

            $ctrl_key=$ctrl;
            if ($version) {
                $version_fix= $version."/";
                $namespace_version=".". $version;
                $ctrl_key=  $version.".". $ctrl_key;
            }

            echo "check $ctrl_key \n ";
            if (@$func_map[$ctrl_key]===false) { //解析异常，不处理
            } else {
                $existed_flag= @$func_map[$ctrl_key][$action];
                if (!$existed_flag) { // 需要生成
                    Utils::exec_cmd("mkdir -p $controller_dir/$version_fix");

                    $src_file="$controller_dir/$version_fix$ctrl_class.java";
                    $file_content=@file_get_contents("$src_file");
                    $file_content=rtrim($file_content);
                    if (!$file_content) {
                        $file_content=<<<END
package com.vipthink.demo.controllers$namespace_version;
import com.vipthink.demo.controllers.Controller;
import org.springframework.stereotype.Component;

import com.vipthink.gen.proto$namespace_version.*;

@Component("ctrl.$ctrl_key") // 生成 bean, 不可删除该行
public class  $ctrl_class extends Controller {



}
END;
                    }
                    $data_len=strlen($file_content);
                    if ($file_content[$data_len-1]=="}") { //正常。
                        //添加
                        $file_content=substr($file_content, 0, $data_len-2)."\n". $func_str ."\n}";
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
}
