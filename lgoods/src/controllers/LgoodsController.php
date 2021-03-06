<?php
/**
 * Created by PhpStorm.
 * User: lartik
 * Date: 18-9-9
 * Time: 下午11:13
 */
namespace lgoods\controllers;

use common\models\RefundModel;
use lgoods\models\goods\Goods;
use lgoods\models\order\OrderModel;
use lgoods\models\refund\RfModel;
use lgoods\models\trans\Trans;
use lgoods\models\trans\TransModel;
use Yii;
use lgoods\models\goods\GoodsModel;
use lbase\Controller;
use yii\base\Event;
use lgoods\models\goods\GoodsEvent;
use yii\data\ActiveDataProvider;


class LgoodsController extends Controller{

    public function actionHandle($type){
        $t = Yii::$app->db->beginTransaction();
        $notifyData = Yii::$app->request->getBodyParams();
        $notifyData ='<xml><appid><![CDATA[wxb8e63b3b3196d6a7]]></appid>
<bank_type><![CDATA[CFT]]></bank_type>
<cash_fee><![CDATA[4]]></cash_fee>
<fee_type><![CDATA[CNY]]></fee_type>
<is_subscribe><![CDATA[N]]></is_subscribe>
<mch_id><![CDATA[1489031722]]></mch_id>
<nonce_str><![CDATA[5qogjzc9roz4thcffib2soxqsx2nkbzm]]></nonce_str>
<openid><![CDATA[o82Odw-jLdQsZ1InClRz_3gly R30]]></openid>
<out_trade_no><![CDATA[TR112018535719105716]]></out_trade_no>
<result_code><![CDATA[SUCCESS]]></result_code>
<return_code><![CDATA[SUCCESS]]></return_code>
<sign><![CDATA[270736D96C21519427509AABE72E9952]]></sign>
<time_end><![CDATA[20181019115642]]></time_end>
<total_fee>4</total_fee>
<trade_type><![CDATA[NATIVE]]></trade_type>
<transaction_id><![CDATA[4200000169201810191219217090]]></transaction_id>
</xml>';
        $payment = TransModel::getPayment($type);
        try {
            $transData = $payment->handleNotify($notifyData, []);
            if($transData['code'] > 0){
                $payment->sayFail([]);
                exit();
            }
            $payOrder = TransModel::findPayTrace()->andWhere(['pt_belong_trans_number' => $transData['trans_number']])->one();
            $transModel = new TransModel();
            if(!$payOrder || !$transModel::updatePayOrderPayed($payOrder, ['notification' => $notifyData])){
                $payment->sayFail([]);
                exit();
            }
            TransModel::triggerPayed($payOrder);
            $payment->saySucc([]);
            $t->commit();
            exit();
        } catch (\Exception $e) {
            Yii::error($e);
            $t->rollBack();
            $payment->sayFail([]);
            exit();
        }
        
    }

    /**
     * @api get,/lgoods,Goods,查询商品接口
     * - fields_level optional,string,in_query,返回字段层级设定
     * - g_cls_id optional,integer,in_query,查询指定分类的商品
     *
     * @return #global_res
     * - data object#goods_items_list,返回课程列表
     *
     */
    public function actionList(){
        $getData = Yii::$app->request->get();
        $getData = array_merge($getData, [
            'field_level' => 'list'
        ]);
        $query = GoodsModel::findFull($getData);
        if(!empty($getData['g_cls_id'])){
            $query->andWhere(['=', 'g_cls_id', $getData['g_cls_id']]);
        }
        $provider = new ActiveDataProvider([
            'query' => $query->asArray(),
        ]);
        $items = GoodsModel::formatList($provider->getModels(), $getData);
        return $this->succItems($items, $provider->totalCount);
    }


    public function actionViewSku($index, $sub_index){
        $skuIndex = GoodsModel::ensureGoodsSkuIndexRight($sub_index);
        if(!$skuIndex){
            return $this->error(1, "参数错误");
        }
        $sku = GoodsModel::findSku()
                            ->andWhere(['=', 'sku_g_id', $index])
                            ->andWhere(['=', 'sku_index', $skuIndex])
                            ->asArray()
                            ->one();
        if(!$sku) {
            return $this->notfound();
        }
        return $this->succ($sku);
    }

    /**
     * @api get,/lgoods/{id},Goods,查看商品信信息
     * - id required,integer,in_path,商品g_id
     * - fields_level optional,string,in_query,返回字段层级设定
     *
     * @return #global_res
     * - data object#goods_item,返回商品具体信息
     * 
     */
    public function actionView($index){
        $getData = Yii::$app->request->get();
        $goodsData = GoodsModel::findFull($getData)
                    ->andWhere(['=', 'g.g_id', $index])
                    ->asArray()
                    ->one();
        if(!$goodsData){
            return $this->notfound();
        }
        $goodsData = GoodsModel::formatOne($goodsData, $getData);
        return $this->succ($goodsData);
    }

    public function actionListAttrs($index){
        $getData = Yii::$app->request->get();
        $attrs = GoodsModel::getGoodsListAttrs([$index], $getData);
        $data = [];
        if(isset($attrs[$index])){
            foreach($attrs[$index] as $attr){
                $data[] = $attr;
            }
        }
        return $this->succItems($data, count($data));
    }

    /**
     * @api put,/lgoods,Goods,修改一个商品
     * - g_name required,string,in_body,商品名称
     * - g_sid required,integer,in_body,商品关联对象id，未定义的时候传0
     * - g_stype required,integer,in_body,商品关联对象模块类型，未定义时传空字符
     * - g_options optional,array#option_update_param,in_body,商品属性值更新列表
     * - g_del_options optional,array#integer,in_body,需要删除的选项值id
     *
     * @return #global_res
     * - data object#goods_item,返回商品详情
     */
    public function actionUpdate($index){
        $t = $this->beginTransaction();
        try{
            $goods = Goods::find()->where(['g_id' => $index])->one();
            if(!$goods){
                return $this->notfound();
            }

            $postData = Yii::$app->request->getBodyParams();
            $model = new GoodsModel();
            $goods = $model->updateGoods($goods, $postData);
            if(!$goods){
                return $this->error(1, $model->getErrors());
            }
            GoodsModel::ensureGoodsSkusRight($goods);
            $goodsFullData = GoodsModel::formatOne($goods->toArray(), [
                'goods_attr_level' => 'all',
                'field_level' => 'all'
            ]);
            $t->commit();
            return $this->succ($goodsFullData);
        }catch(\Exception $e){
            $t->rollback();
            throw $e;
        }
    }

    /**
     * @api post,/lgoods,Goods,创建一个商品
     * - g_name required,string,in_body,商品名称
     * - g_sid required,integer,in_body,商品关联对象id，未定义的时候传0
     * - g_stype required,integer,in_body,商品关联对象模块类型，未定义时传空字符
     * - g_options optional,array#option_param,in_body,商品属性值设置列表
     * - price_items optional,array#price_param,in_body,商品价格设置列表
     *
     * @return #global_res
     * - data object#goods_item,返回商品详情
     *
     * <<<doc
     * __接口开发说明：__
     * 1. 商品的选项值设定需要关联属性id，属性id的获取开发者需要了解/lcollect接口
     *
     * >>>
     */
    public function actionCreate(){
        $t = $this->beginTransaction();
        try{
            $postData = Yii::$app->request->getBodyParams();
            $model = new GoodsModel();
            $goods = $model->createGoods($postData);
            if(!$goods){
                return $this->error(1, $model->getErrors());
            }
            $t->commit();
            $goodsData = GoodsModel::findFull()
                ->andWhere(['=', 'g.g_id', $goods['g_id']])
                ->asArray()
                ->one();
            return $this->succ(GoodsModel::formatOne($goodsData, [
                'goods_attr_level' => 'all',
                'field_level' => 'all'
            ]));
        }catch(\Exception $e){
            $t->rollback();
            throw $e;
        }
    }
}
/**
 * @def #option_param
 * - opt_name required,string,属性值名称
 * - opt_value required,string,属性值
 * - opt_attr_id required,integer,属性值对应属性id
 *
 * @def #option_update_param
 * - opt_id required,integer,选项值id
 * - opt_name optional,string,选项值名称
 *
 *
 * @def #price_param
 * - price required,integer,价格
 * - is_master optional,integer,是否是主价格
 *
 * @def #goods_items_list
 * - total_count integer,总数
 * - items array#goods_item,商品列表
 *
 * @def #goods_item
 * - g_id integer,商品id
 * - g_name string,商品名称
 * - g_price integer,商品价格
 * - g_cls_id integer,商品类别id
 * - g_discount integer,商品折扣
 * - g_created_at integer,创建时间
 * - g_updated_at integer,更新时间
 * - g_m_img_url string,图片url
 * - g_m_img_url1 string,图片url1
 * - g_m_img_url2 string,图片url2
 * - g_m_img_url3 string,图片url3
 * - g_m_img_url4 string,图片url4
 * - g_skus array#sku_item,商品sku条目列表
 * - g_attrs array#attr_item,商品sku属性列表
 * - categroy_path array#category_path_item,分类路径列表，父辈分类排在前面
 * - g_discount_items array#g_discount_item,商品折扣对象列表
 *
 * @def #g_discount_item
 * - sr_id integer,折扣id
 * - sr_name string,折扣名称
 * - sr_usage_intro string,折扣介绍
 * - discount integer,折扣数
 * - sr_start_at integer,生效时间
 * - sr_end_at integer,失效时间
 *
 * @def #category_path_item
 * - g_cls_id integer,分类id
 * - g_cls_show_name string,分类名称
 * - g_cls_pid integer,分类父级id
 *
 * @def #attr_item
 * - values array#value_item,属性值对象
 * - a_name string,属性值名称
 * - a_id integer,属性值id
 *
 * @def #value_item
 * - opt_id integer,选项值id
 * - opt_name string,选项值名称
 * - opt_value string,选项值
 *
 * @def #sku_item
 * - sku_id integer,sku的id
 * - sku_index string,sku索引名称
 * - sku_index_status integer,索引的名称的状态
 * - sku_is_master integer,是否是主要sku
 * - sku_name string,sku的名称
 * - sku_price integer,sku条目的价格
 * 
 */