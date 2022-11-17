<?php
namespace App;

class App
{
    public static $work_dir="./src";
    public static $protoc="./src/../../bin/protoc";
    public static $core_server_type=""; //php,go
    public static $controller_dir="";//控制器目录
    public static $config=[];//配置
    public static $new_control_flag=[];//新版 生成代码
    public static $proto_dir_list=[];// ["","v1","v2"..]

    public static function set_proto_dir_list()
    {

        static::$proto_dir_list=[""];
        $file_list=scandir(static::$work_dir);
        foreach ($file_list as $file) {
            $file_name= realpath(static::$work_dir."/$file");
            if (is_dir($file_name)) {
                if (!($file[0]=="." || $file[0]==".."   || $file=="common")) {
                    static::$proto_dir_list[]=$file;
                }
            }
        }

        //echo "111111111111111\n";
        //print_r(static::$proto_dir_list);
        //echo "2222222222222\n;";
    }

    public static function run()
    {
        static::set_proto_dir_list();
        echo "protodir:" . json_encode(static::$proto_dir_list)."\n";

        static::$config=require("./config.php");
        static::$new_control_flag=static::$config["new_control_flag"]??false;

        $action_map = CommonCode::deal_proto(true, static::$work_dir, static::$controller_dir);

        //生成 php ctrl 代码
        if (static::$core_server_type=="php") {
            PhpCode::gen_control_action_map($action_map, static::$controller_dir);
        } elseif (static::$core_server_type=="java") {
            JavaCode::gen_control_action_map($action_map, static::$controller_dir);
        }
    }
}
