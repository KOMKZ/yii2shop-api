<?php
/**
 * Created by PhpStorm.
 * User: lartik
 * Date: 18-9-9
 * Time: 下午11:13
 */
namespace lgoods\controllers;

use lbase\helpers\ArrayHelper;
use lgoods\models\cart\CItemModel;
use lgoods\models\goods\GoodsModel;
use lgoods\models\order\Order;
use lgoods\models\order\OrderModel;
use lgoods\models\trans\TransModel;
use Yii;
use lbase\Controller;
use yii\data\ActiveDataProvider;


class LorderController extends Controller{

    /**
     * @api post,/lorder/check,Order,商品结算接口
     * - type required,string,in_body,结算方式:填入cart即可
     * - ids required,array#integer,in_body,购物车条目id列表
     * - og_list optional,array#og_list_param,in_body,购买商品列表
     *
     * @return #global_res
     * - data object#check_result,结算结果
     *
     */
    public function actionCheck(){
        $result = [
            'total_price' => 0,
            'discount_des' => []
        ];
        $postData = Yii::$app->request->getBodyParams();
        if('cart' == $postData['type']){
            $ogList = CItemModel::findFull()->andWhere(['in', 'ci_id', $postData['ids']])->asArray()->all();
        }elseif('order' == $postData['type']){
            $ogList = $postData['og_list'];
            $skuIds = ArrayHelper::getColumn($ogList, 'ci_sku_id', []);
            $skus = GoodsModel::findSku()->andWhere(['in', 'sku_id', $skuIds])->asArray()->indexBy("sku_id")->all();
            foreach($ogList as $k => &$item){
                $sku = ArrayHelper::getValue($skus, $item['ci_sku_id']);
                if(!$sku){
                    unset($ogList[$k]);
                    continue;
                }
                $item['sku_price'] = $sku['sku_price'];
                $item['g_id'] = $sku['sku_g_id'];
            }
        }
        $priceItems = OrderModel::checkOrderFromOgList($ogList, $postData);
        unset($priceItems['has_error']);
        unset($priceItems['error_des']);
        unset($priceItems['discount_items']);
        return $this->succ($priceItems);
    }
    /**
     * @api get,/lorder,Order,获取订单详情
     * - id required,string,in_query,订单编号od_num，支持模糊查询
     * - fields_level optional,string,in_query,返回字段层级设定
     *
     * @return #global_res
     * - data object#order_item_list,返回创建订单详情列表对象
     *
     */
    public function actionList(){
        $getData = Yii::$app->request->get();
        $getData = array_merge(['fields_level' => 'list'], $getData);
        $query = OrderModel::findOrderFull($getData)->asArray();
        $provider = new ActiveDataProvider([
            'query' => $query
        ]);
        return $this->succItems(OrderModel::formatOrders($provider->getModels(), $getData), $provider->totalCount);
    }


    /**
     * @api get,/lorder/{id},Order,获取订单详情
     * - id required,string,in_path,订单编号od_num
     * - fields_level optional,string,in_query,返回字段层级设定
     *
     * @return #global_res
     * - data object#order_item,返回创建订单详情对象
     *
     */
    public function actionView($index){
        $getData = Yii::$app->request->get();
        $getData = array_merge(['fields_level' => 'all'], $getData);
        $order = OrderModel::findOrderFull($getData)->andWhere(['=', 'o.od_num', $index])->asArray()->one();
        if(!$order){
            return $this->notfound();
        }
        return $this->succ(OrderModel::formatOneOrder($order, $getData));
    }

    /**
     * @api post,/lorder,Order,创建一条订单
     * - order_goods_list required,array#og_item_param,in_body,订单购买商品列表
     *
     * @return #global_res
     * - data object#order_item,返回创建订单对象
     *
     * <<<doc
     * __订单支付流程__:
     * 1. 创建订单 post /lorder
     * 2. 创建交易 post /lorder/{id}/trans
     * 3. 创建支付 post /trans/{id}/pay-order，必须选择某种支付方式
     * 
     * 注： 详细参考各自接口的开发流程
     *
     * __细节说明__::
     * 1. 每条订单都有一条交易记录与之对应，必须使用某种支付方式取支付交易才会触发订单触发支付成功。
     * 2. 可以优先使用npay的支付方式跑完流程，该支付方式能够让无条件让交易支付成功
     * >>>
     *
     */
    public function actionCreate(){
        $t = $this->beginTransaction();
        try{
            $postData = Yii::$app->request->getBodyParams();
            $orderModel = new OrderModel();
            $order = $orderModel->createOrderFromSkus($postData);
            if(!$order){
                return $this->error(1, $orderModel->getErrors());
            }
            $orderData = OrderModel::findOrderFull()->andWhere(['=', 'o.od_id', $order['od_id']])->asArray()->one();
            $t->commit();
            return $this->succ(OrderModel::formatOneOrder($orderData));
        }catch(\Exception $e){
            throw $e;
            $t->rollback();
        }
    }

    /**
     * @api post,/lorder/{id}/trans,Order,创建一条订单的交易
     * - id required,string,in_path,订单编号
     *
     * @return #global_res
     * - data object#trans_item,返回交易对象
     *
     *
     *
     */
    public function actionCreateTrans($index){
        $t = $this->beginTransaction();
        try{
            $order = OrderModel::findOrder()->andWhere(['=', 'od_num', $index])->one();
            if(!$order){
                return $this->notfound();
            }
            $oModel = new OrderModel();
            $check = $oModel->ensureOrderCanPay($order);
            if(!$check){
                return $this->error(1, $oModel->getErrors());
            }
            $postData = Yii::$app->request->getBodyParams();
            $transModel = new TransModel();
            $trans = $transModel->createTransFromOrder($order, [
                'trs_timeout' => 500,
                'trs_content' => ''
            ]);
            if(!$trans){
                return $this->error(1, $transModel->getErrors());
            }
            $t->commit();
            return $this->succ($trans->toArray());
        }catch(\Exception $e){
            throw $e;
            $t->rollback();
        }
    }




}
/**
 * @def #check_result
 * - total_price integer,总价格
 * - discount_items_des array#string,折扣描述列表
 * - use_coupons array#integer,使用优惠券列表id
 * - use_coupons_des array#string,使用优惠券描述列表
 * - valid_coupons array#valid_coupon_item,当前可用的优惠券列表
 *
 * @def #valid_coupon_item
 * - ucou_id integer,用户优惠券id
 * - coup_name string,优惠券名称
 * - coup_start_at integer,优惠券生效时间
 * - coup_end_at integer,优惠券失效时间
 *
 * @def #order_item_list
 * - total_count integer,总数量
 * - order_item_list array#order_item,订单对象列表
 *
 * @def #og_item_param
 * - og_sku_id required,integer,商品sku的id
 * - og_total_num required,integer,购买的数量
 * - discount_params optional,string,用户折扣选择
 *
 * @def #order_item
 * - od_id integer,订单id
 * - od_num string,订单编号
 * - od_created_at integer,下单时间
 * - od_price integer,订单价格
 * - od_discount integer,订单折扣
 * - trs_id integer,交易id
 * - od_discount_des array#string,订单折扣描述列表
 * - order_goods_list array#order_goods_item,订单明细列表
 * - od_price_str string,订单价格展示值
 * - od_discount_str string,订单折扣展示值
 * - od_pay_status integer,订单支付状态
 *
 * @def #order_goods_item
 * - og_name string,明细名称，即商品名称
 * - g_m_img_url string,商品主图片url
 *
 * @def #trans_item
 * - trs_type integer,交易类型
 * - trs_num string,交易号码
 * - trs_timeout integer,交易最迟支付时间
 * - trs_id integer,交易id
 *
 * @def #og_list_param
 * - ci_sku_id required,integer,单品的sku的id
 * - ci_amount required,integer,购买的数量
 * - ci_g_id required,integer,商品的g_id
 *
 */
