<?php
namespace App;

class GoCode
{

    public static function deal_go($go_dir, $cmd_map)
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
        $version_map=[];

        foreach ($cmd_map as $proto_name => $cmd_info) {
            $cname=utils::toCamelCase(str_replace(".", "_", $proto_name));
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

            if ($version) {
                $version_map[]=$version;
                $version_str="$version.";
                $call_url="/$version/$ctrl/$action";
            }
            $c_ctrl=utils::toCamelCase($ctrl) ;
            $c_version=utils::toCamelCase($version) ;
            $ctrl_list[ "$version_str$c_ctrl"]=true;

            $auth=$cmd_info["AUTH"];
            $auth_type="AuthNull";
            if ($auth=="public") {
                $auth_type="AuthPublic";
            } elseif ($auth=="private") {
                $auth_type="AuthPrivate";
            } elseif ($auth=="session") {
                $auth_type="AuthSession";
            }


            $cmd_info_str.="
      {
          Path:        \"$call_url\",
          Version:        \"$version\",
          Ctrl:        \"$ctrl\",
          Action:      \"$action\",
          Auth:        p.$auth_type,
          NewCtrlFunc: func() interface{} { return & $c_version$c_ctrl },
          NewInFunc:   func() interface{} { return new(proto.{$cname}In) },
          NewOutFunc:  func() interface{} { return new(proto.{$cname}Out) },
      },

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
        foreach ($ctrl_list as $c_ctrl => $v) {
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
        $import_version_str="";

        foreach ($version_map as $version => $v) {
            $import_version_str.="    \"server/app/controllers/$version\" ";
        }


        $in_out_def="
package proto
//自动生成
import (
$import_str
)
$type_str
";
        $cmd_info_def="
package cmdlist
//自动生成
import (
    \"server/app/controllers\"
    p \"server/app/core/proto\"
    \"server/gen/proto\"
    \"server/gen/zmodels/pkg\"

$import_version_str
)

// CmdList xx命令列表
var CmdList = []p.CmdInfo{
$cmd_info_str
}
//Controller
var Controller=controllers.Controller{pkg.ModelPkg}

$ctrl_str

";

        Utils::save_file("$go_dir/proto/proto.go", $in_out_def);

        Utils::exec_cmd("mkdir -p $go_dir/proto/cmdlist/");
        Utils::save_file("$go_dir/proto/cmdlist/cmdlist.go", $cmd_info_def);
    }
}
