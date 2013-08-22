<?php
/*
Plugin Name: WooCommerce Onecheckout Gateway 
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce with an Onecheckout gateway.
Version: 2.01
Author: Denys Kanunnikov
Author URI: http://freelancehunt.com/freelancer/dargentstore.html
*/
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('plugins_loaded', 'woocommerce_init', 0);

function woocommerce_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
    
	class WC_Gateway_Onecheckout extends WC_Payment_Gateway{

        public function __construct(){
			
			global $woocommerce;
			
            $this->id = 'onecheckout';
            $this->has_fields         = false;
            $this->method_title 	  = __( 'Единая Касса', 'woocommerce' );
            $this->method_description = __( 'Единая Касса', 'woocommerce' );
			$this->init_form_fields();
            $this->init_settings();
            $this->title 			  =  $this->get_option( 'title' );
            $this->description        =  $this->get_option('description');
            $this->merchant_id        =  $this->get_option('merchant_id');
            $this->secret             =  $this->get_option('secret');
            $this->language           =  $this->get_option('language');
            $this->paymenttime        =  $this->get_option('paymenttime');
            $this->payment_method     =  $this->get_option('payment_method'); 
            // Actions
            add_action( 'woocommerce_receipt_onecheckout', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			
			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_gateway_onecheckout', array( $this, 'check_ipn_response' ) );
            
            if (!$this->is_valid_for_use()){
                $this->enabled = false;
            }
        }
			
		public function admin_options() {

		?>
		<h3><?php _e( 'Единая Касса', 'woocommerce' ); ?></h3>
        
        <?php if ( $this->is_valid_for_use() ) : ?>
        
			<table class="form-table">
			<?php
    			
    			$this->generate_settings_html();
			?>
			<p><strong><?php _e( 'В личном кабинете укажите следующее значение для Адреса повещений: ')?></strong><?php echo  str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_gateway_onecheckout', home_url( '/' ) ) );?></p>
			</table>
            
		<?php else : ?>
		<div class="inline error"><p><strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php _e('Единая Касса не поддерживает валюты Вашего магазина.', 'woocommerce' ); ?></p></div>
		<?php
			endif;
		}
		
        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Включить/Отключить', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Включить', 'woocommerce' ),
                    'default' => 'yes'
                                ),
                'title' => array(
                    'title' => __( 'Заголовок', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Заголовок, который отображается на странице оформления заказа', 'woocommerce' ),
                    'default' => 'Единная Касса',
                    'desc_tip' => true,
                                ),
                'description' => array(
                    'title' => __( 'Описание', 'woocommerce' ),
                    'type' => 'textarea',
                    'description' => __( 'Описание, которое отображается в процессе выбора формы оплаты', 'woocommerce' ),
                    'default' => __( 'Оплатить через электронную платежную систему Единная Касса', 'woocommerce' ),
                ),
                'merchant_id' => array(
                    'title' => __( 'Merchant ID', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Уникальный идентификатор магазина в системе Единная Касса.', 'woocommerce' ),
                ),
                'secret' => array(
                    'title' => __( 'Секретный ключ', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Секретный ключ', 'woocommerce' ),
                ),
                'language' => array(
                    'title' => __( 'Язык интерфейса', 'woocommerce' ),
                    'type' => 'select',
                    'options' => $this -> get_language('Выберите язык интерфейса'),
                    'description' => __( 'Язык интерфейса', 'woocommerce' ),
                ),
                'paymenttime' => array(
                    'title' => __( 'Срок истечения оплаты', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Время необходимо указать в часах', 'woocommerce' ),
                ),
                'payment_method' => array(
                    'title' => __( 'Выбор способов оплаты', 'woocommerce' ),
                    'type' => 'multiselect',
                    'options' => $this -> get_methods(),
                    'description' => __( 'Выберите способы оплаты в системе Единой Кассы', 'woocommerce' ),
                ),
                
            );
        }
        
        function get_language($title = false) {
            $language_list[get_locale()] = __('Автоматический', 'woocommerce');
            $language_list['en-US']      = __('Английский', 'woocommerce');
            $language_list['ru-RU']      = __('Русский', 'woocommerce'); 
            
            return $language_list;
        }
        
        function get_methods() {
            $methods_list['WalletOne']           = __('Единый кошелек', 'woocommerce');
            $methods_list['YandexMoneyRUB']      = __('Яндекс.Деньги', 'woocommerce');
            $methods_list['WebMoney']            = __('WebMoney', 'woocommerce'); 
            $methods_list['QiwiWalletRUB']       = __('QIWI Кошелек', 'woocommerce'); 
            $methods_list['UkashEUR']            = __('Ukash', 'woocommerce'); 
            $methods_list['RBK Money']           = __('RBK Money', 'woocommerce'); 
            $methods_list['ZPaymentRUB']         = __('Z-Payment', 'woocommerce'); 
            $methods_list['BPayMDL']             = __('B-pay', 'woocommerce'); 
            $methods_list['CashUUSD']            = __('CashU', 'woocommerce'); 
            $methods_list['WebCredsRUB']         = __('WebCreds', 'woocommerce'); 
            $methods_list['EasyPayBYR']          = __('EasyPay', 'woocommerce'); 
            $methods_list['OkpayUSD']            = __('OKPAY', 'woocommerce'); 
            $methods_list['BeelineRUB']          = __('Мобильный платеж «Билайн» (Россия)', 'woocommerce'); 
            $methods_list['MtsRUB']              = __('Мобильный платеж «МТС» (Россия)', 'woocommerce'); 
            $methods_list['MegafonRUB']          = __('Мобильный платеж «Мегафон» (Россия)', 'woocommerce'); 
            $methods_list['CashTerminal']        = __('Платежные терминалы', 'woocommerce'); 
            $methods_list['MobileRetails']       = __('Салоны связи', 'woocommerce');
            $methods_list['BankOffice']          = __('Отделения банков', 'woocommerce');
            $methods_list['MoneyTransfer']       = __('Денежные переводы', 'woocommerce');
            $methods_list['OnlineBank']          = __('Интернет-банки', 'woocommerce');
            $methods_list['VISA']                = __('VISA', 'woocommerce');
            $methods_list['MasterCard']          = __('MasterCard', 'woocommerce');
            $methods_list['Maestro']             = __('Maestro', 'woocommerce');
            $methods_list['NsmepUAH']            = __('Банковские карты НСМЭП Украины', 'woocommerce');
            $methods_list['GiropayDeEUR']        = __('Giropay (Германия)', 'woocommerce');            
            $methods_list['Przelewy24PLN']       = __('Przelewy24', 'woocommerce');            
            $methods_list['IdealNlEUR']          = __('iDEAL (Нидерланды)', 'woocommerce');            
            $methods_list['TeleingresoEsEUR']    = __('Teleingreso', 'woocommerce');
            $methods_list['TeleingresoEsEUR']    = __('TeleingresoEsEUR', 'woocommerce');            
            $methods_list['SofortBanking']       = __('Sofort Banking', 'woocommerce');                        
            $methods_list['TrustPay']            = __('TrustPay', 'woocommerce');            
            $methods_list['PaysafecardEUR']        = __('Paysafecard', 'woocommerce');            
            return $methods_list;
        }
        
        function is_valid_for_use(){
            if (!in_array(get_option('woocommerce_currency'), array('RUB', 'UAH', 'USD', 'EUR', 'KZT'))){
                return false;
            }
		return true;
	}

        function process_payment($order_id){
                $order = new WC_Order($order_id);
				return array(
        			'result' => 'success',
        			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
        		);
                
         }

        public function receipt_page($order){
            echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
            echo $this->generate_form($order);
        }

        public function generate_form($order_id){
			global $woocommerce;
			
            $order = new WC_Order( $order_id );
            $action_adr = "https://merchant.w1.ru/checkout/default.aspx";
            $result_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_gateway_onecheckout', home_url( '/' ) ) );
			
            switch (get_woocommerce_currency()) {
				case 'UAH':
					$currency = 980;
					break;
				case 'USD':
					$currency = 840;
					break;
				case 'KZT':
					$currency = 398;
					break;
				case 'EUR':
					$currency = 978;
					break;
                case 'RUB':
					$currency = 643;
					break;    
			}
            $args = array(
                            'WMI_PAYMENT_AMOUNT' => $order->order_total,
                            'WMI_CURRENCY_ID'    => $currency,
                            'WMI_MERCHANT_ID'    => $this->merchant_id,
                            'WMI_PAYMENT_NO'     => $order_id,
                            'WMI_DESCRIPTION'    => "BASE64:".base64_encode("Оплата за заказ - $order_id"),
                            'WMI_SUCCESS_URL'    =>  $result_url,
                            'WMI_FAIL_URL'       =>  $result_url,
                            'WMI_EXPIRED_DATE'   =>  date("Y-m-d H:i:s", time() + (int)$this->paymenttime*3600),
                            'WMI_CULTURE_ID'     =>  $this->language
            			);
						
			foreach ($args as $name => $val) {
				if (is_array($val)) {
					usort($val, "strcasecmp");
					$args[$name] = $val;
				}
			}	
			
			uksort($args, "strcasecmp");
			$fieldValues = "";	
			
			foreach ($args as $value) {
				if (is_array($value))
					foreach ($value as $v) {
						$v = iconv("utf-8", "windows-1251", $v);
						$fieldValues .= $v;
					} else {
					$value = iconv("utf-8", "windows-1251", $value);
					$fieldValues .= $value;
				}
			}
            
            $signature = base64_encode(pack("H*", md5($fieldValues . $this->secret)));
			$args["WMI_SIGNATURE"] = $signature;
            
            $args_array = array();

            foreach ($args as $key => $value){
            	$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
            }
            
            if(!empty($this->payment_method)){
                foreach($this->payment_method as $pay_key => $pay_value){
                    $args_array[] = '<input type="hidden" name="WMI_PTENABLED" value="'.esc_attr($pay_value).'" />';
                }
            }

            return
                    '<form action="'.esc_url($action_adr).'" method="POST" name="onecheckout_form">'.
                    '<input type="submit" class="button alt" id="submit_onecheckout_button" value="'.__('Оплатить', 'woocommerce').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Отказаться от оплаты & вернуться в корзину', 'woocommerce').'</a>'."\n".
                    implode("\n", $args_array).
                    '</form>';
        }

        function check_ipn_response(){
			
            global $woocommerce;
            
            if (!isset($_POST["WMI_SIGNATURE"])){
				print "WMI_RESULT=" . strtoupper("Retry") . "&";
				print "WMI_DESCRIPTION=" .urlencode("Отсутствует параметр WMI_SIGNATURE");
				exit();
			}
             
			
            if (!isset($_POST["WMI_PAYMENT_NO"])){
				print "WMI_RESULT=" . strtoupper("Retry") . "&";
				print "WMI_DESCRIPTION=" .urlencode("Отсутствует параметр WMI_PAYMENT_NO");
				exit();
			}
             
            if (!isset($_POST["WMI_ORDER_STATE"])){
				print "WMI_RESULT=" . strtoupper("Retry") . "&";
				print "WMI_DESCRIPTION=" .urlencode("Отсутствует параметр WMI_ORDER_STATE");
				exit();
			}
            
			
			
            if ( isset($_POST["WMI_SIGNATURE"]) && $this->merchant_id == $_POST["WMI_MERCHANT_ID"]  )
            {	
              if (strtoupper($_POST["WMI_ORDER_STATE"]) == "ACCEPTED")
              {		
                  $order_id = $_POST['WMI_PAYMENT_NO'];
                  $order = new WC_Order($order_id );
                  $order->update_status('on-hold', __('Платеж успешно оплачен', 'woocommerce'));
                  $woocommerce->cart->empty_cart();
                  print "WMI_RESULT=" . strtoupper("Ok") . "&";
				  print "WMI_DESCRIPTION=" .urlencode("Заказ #" . $_POST["WMI_PAYMENT_NO"] . " оплачен!");
				  wp_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_id , get_permalink(get_option('woocommerce_thanks_page_id')))));
                  exit;
              }
              else
              {
                  $inv_id = $_POST['WMI_PAYMENT_NO'];
                  $order = new WC_Order($inv_id);
                  $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
                  print_answer("Retry", "Неверное состояние ". $_POST["WMI_ORDER_STATE"]);
                  wp_redirect($order->get_cancel_order_url());
                  exit;
              }
            }
            else
            {
              print "WMI_RESULT=" . strtoupper("Retry") . "&";
			  print "WMI_DESCRIPTION=" .urlencode("Неверная подпись " . $_POST["WMI_SIGNATURE"]);
			  wp_redirect($order->get_cancel_order_url());
              exit();         
			  
            }

        }

    }

	
	function woocommerce_add_onecheckout_gateway($methods) {
		$methods[] = 'WC_Gateway_Onecheckout';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_onecheckout_gateway' );
	
}


?>