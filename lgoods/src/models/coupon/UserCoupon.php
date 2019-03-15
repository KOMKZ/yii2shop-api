<?php
/**
 * Created by PhpStorm.
 * User: master
 * Date: 18-11-30
 * Time: 下午1:45
 */
namespace lgoods\models\coupon;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class UserCoupon extends  ActiveRecord{

    public static function tableName(){
        return "{{%user_coupon}}";
    }

    public function rules(){
        return [
            ['coup_id', 'required'],
            ['ucou_u_id', 'required'],
        ];
    }

}