<?php
/**
 * Created by PhpStorm.
 * User: henz
 * Date: 21/01/16
 * Time: 11:40
 */

namespace Optimouv\FfbbBundle\Services;

use PDO;

class Poules{

    private $database_name;
    private $database_user;
    private $database_password;
    private $app_id;
    private $app_code;
    private $error_log_path;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
        $this->error_log_path = $error_log_path;
    }

    public function sauvegarderParamsEnDB(){
        $params = $_POST;
        
        error_log("\n params: ".print_r($params , true), 3, "error_log_optimouv.txt");

        $idParams = 1;

        return $idParams;

    }
}