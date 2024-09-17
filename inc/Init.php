<?php
/*
*
* @package aristidesgp
*
*/

namespace WRA\Inc;

final class Init{

    public static function get_services(){

        return [
            Base\Schedule::class,
            Base\Settings::class,
            Base\Enqueue::class,
            Base\Ajax::class,
            Base\ShortCodes::class,
            Base\Registration::class
        ] ;
    }

    public static function register_services(){        
        foreach (self::get_services() as $class) {
            $service = self::instantiate($class);
            if(method_exists( $service , 'register')){
                $service->register();
            }
        }        
    }

    private static function instantiate($class){

        $service = new $class();
        return $service;
    }

}
