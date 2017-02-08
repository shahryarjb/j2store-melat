<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_j2store
 * @subpackage 	Trangell_Mellat
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/j2store/payment_trangellmellat/trangell_inputcheck.php');
}

class plgJ2StorePayment_trangellmellat extends J2StorePaymentPlugin
{

    var $_element    = 'payment_trangellmellat';

	function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage( 'com_j2store', JPATH_ADMINISTRATOR );
	}


	function onJ2StoreCalculateFees($order) {
		$payment_method = $order->get_payment_method ();

		if ($payment_method == $this->_element) {
			$total = $order->order_subtotal + $order->order_shipping + $order->order_shipping_tax;
			$surcharge = 0;
			$surcharge_percent = $this->params->get ( 'surcharge_percent', 0 );
			$surcharge_fixed = $this->params->get ( 'surcharge_fixed', 0 );
			if (( float ) $surcharge_percent > 0 || ( float ) $surcharge_fixed > 0) {
				// percentage
				if (( float ) $surcharge_percent > 0) {
					$surcharge += ($total * ( float ) $surcharge_percent) / 100;
				}

				if (( float ) $surcharge_fixed > 0) {
					$surcharge += ( float ) $surcharge_fixed;
				}

				$name = $this->params->get ( 'surcharge_name', JText::_ ( 'J2STORE_CART_SURCHARGE' ) );
				$tax_class_id = $this->params->get ( 'surcharge_tax_class_id', '' );
				$taxable = false;
				if ($tax_class_id && $tax_class_id > 0)
					$taxable = true;
				if ($surcharge > 0) {
					$order->add_fee ( $name, round ( $surcharge, 2 ), $taxable, $tax_class_id );
				}
			}
		}
	}

    function _prePayment( $data )
    {	$app	= JFactory::getApplication();
		$dateTime = JFactory::getDate();
        $vars = new JObject();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type = $this->_element;
        $vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');
        //============================================================================
		$vars->display_name = 'TrangellMellat';
		$vars->melatuser = $this->params->get('melatuser', '');
		$vars->melatpass = $this->params->get('melatpass', '');
		$vars->melatterminalId = $this->params->get('melatterminalId', '');
		if (
			($vars->melatterminalId == null || $vars->melatterminalId == '') and
			($vars->melatuser == null || $vars->melatuser == '') and
			($vars->melatpass == null || $vars->melatpass == '')
		){
			$link = JRoute::_(JURI::root(). "index.php?option=com_j2store" );
			$app->redirect($link, '<h2>لطفا تنظیمات درگاه ملت را بررسی کنید</h2>', $msgType='Error'); 
		}
		else {
			$fields = array(
				'terminalId' => $vars->melatterminalId,
				'userName' => $vars->melatuser,
				'userPassword' => $vars->melatpass,
				'orderId' => time(),
				'amount' => round($vars->orderpayment_amount,0),
				'localDate' => $dateTime->format('Ymd'),
				'localTime' => $dateTime->format('His'),
				'additionalData' => '',
				'callBackUrl' => JRoute::_(JURI::root(). "index.php?option=com_j2store&view=checkout" ) .'&orderpayment_id='.$vars->orderpayment_id . '&orderpayment_type=' . $vars->orderpayment_type .'&task=confirmPayment',
				'payerId' => 0,
			);
				
			try {
				$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
				$response = $soap->bpPayRequest($fields);
				
				$response = explode(',', $response->return);
				if ($response[0] != '0') { // if transaction fail
					$msg = $this->getGateMsg($response[0]); 
					$link = JRoute::_( "index.php?option=com_j2store" );
					$app->redirect($link, '<h2>'. $msg .'</h2>', $msgType='Error'); 
				}
				else { // if success
					$vars->trangellmellat =  $response[1];
					$html = $this->_getLayout('prepayment', $vars);
					return $html;
				}
			}
			catch(\SoapFault $e) {
				$msg= $this->getGateMsg('error'); 
				$link = JRoute::_( "index.php?option=com_j2store" );
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
			}
		}
    }

	
	function _postPayment($data) {
		$app = JFactory::getApplication(); 
		$jinput = $app->input;
        $html = '';
		$orderpayment_id = $jinput->get->get('orderpayment_id', '0', 'INT');
        F0FTable::addIncludePath ( JPATH_ADMINISTRATOR . '/components/com_j2store/tables' );
		$orderpayment = F0FTable::getInstance ( 'Order', 'J2StoreTable' )->getClone ();
	    //$this->getShippingAddress()->phone_2; //mobile
		//==========================================================================
		$melatterminalId = $this->params->get('melatterminalId', '');
		$melatuser = $this->params->get('melatuser', '');
		$melatpass = $this->params->get('melatpass', '');
		$ResCode = $jinput->post->get('ResCode', '1', 'INT'); 
		$SaleOrderId = $jinput->post->get('SaleOrderId', '1', 'INT'); 
		$SaleReferenceId = $jinput->post->get('SaleReferenceId', '1', 'INT'); 
		$RefId = $jinput->post->get('RefId', 'empty', 'STRING'); 
		if (checkHack::strip($RefId) != $RefId )
			$RefId = "illegal";
		$CardNumber = $jinput->post->get('CardHolderPan', 'empty', 'STRING'); 
		if (checkHack::strip($CardNumber) != $CardNumber )
			$CardNumber = "illegal";

	    if ($orderpayment->load ($orderpayment_id)){
			$customer_note = $orderpayment->customer_note;
			if($orderpayment->j2store_order_id == $orderpayment_id) {
				if (
					checkHack::checkNum($ResCode) &&
					checkHack::checkNum($SaleOrderId) &&
					checkHack::checkNum($SaleReferenceId) 
				){
					if ($ResCode != '0') {
						$msg= $this->getGateMsg($ResCode); 
						$this->saveStatus($msg,4,$customer_note,'nonok',null,$orderpayment);
						$link = JRoute::_(JURI::root(). "index.php?option=com_k2store" );
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					}
					else {
						$fields = array(
							'terminalId' => $melatterminalId,
							'userName' => $melatuser,
							'userPassword' => $melatpass,
							'orderId' => $SaleOrderId, 
							'saleOrderId' =>  $SaleOrderId, 
							'saleReferenceId' => $SaleReferenceId
						);
						try {
							$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
							$response = $soap->bpVerifyRequest($fields);

							if ($response->return != '0') {
								$msg= $this->getGateMsg($response->return); 
								$this->saveStatus($msg,4,$customer_note,'nonok',null,$orderpayment,$CardNumber);
								$link = JRoute::_( "index.php?option=com_j2store" );
								$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
							}
							else {	
								$response = $soap->bpSettleRequest($fields);
								if ($response->return == '0' || $response->return == '45') {
									$msg= $this->getGateMsg($response->return); 
									$this->saveStatus($msg,1,$customer_note,'ok',$SaleReferenceId,$orderpayment,$CardNumber);
									$app->enqueueMessage($SaleReferenceId . ' کد پیگیری شما', 'message');	
								}
								else {
									$msg= $this->getGateMsg($response->return); 
									$this->saveStatus($msg,3,$customer_note,'nonok',null,$orderpayment,$CardNumber);
									$link = JRoute::_( "index.php?option=com_j2store" );
									$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
								}
							}
						}
						catch(\SoapFault $e)  {
							$msg= $this->getGateMsg('error'); 
							$this->saveStatus($msg,3,$customer_note,'nonok',null,$orderpayment,$CardNumber);
							$link = JRoute::_( "index.php?option=com_j2store" );
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						}
					}
				}
				else {
					$msg= $this->getGateMsg('hck2'); 
					$this->saveStatus($msg,3,$customer_note,'nonok',null,$orderpayment);
					$link = JRoute::_( "index.php?option=com_j2store" );
					$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
				}
			}
			else {
				$msg= $this->getGateMsg('notff'); 
				$link = JRoute::_( "index.php?option=com_j2store" );
				$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			}
	    }
		else {
			$msg= $this->getGateMsg('notff'); 
			$link = JRoute::_( "index.php?option=com_j2store" );
			$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
		}
	}

    function _renderForm( $data )
    {
    	$user = JFactory::getUser();
        $vars = new JObject();
        $vars->onselection_text = $this->params->get('onselection', '');
        $html = $this->_getLayout('form', $vars);
        return $html;
    }

	function getPaymentStatus($payment_status) {
    	$status = '';
    	switch($payment_status) {
			case '1': $status = JText::_('J2STORE_CONFIRMED'); break;
			case '2': $status = JText::_('J2STORE_PROCESSED'); break;
			case '3': $status = JText::_('J2STORE_FAILED'); break;
			case '4': $status = JText::_('J2STORE_PENDING'); break;
			case '5': $status = JText::_('J2STORE_INCOMPLETE'); break;
			default: $status = JText::_('J2STORE_PENDING'); break;	
    	}
    	return $status;
    }

	function saveStatus($msg,$statCode,$customer_note,$emptyCart,$trackingCode,$orderpayment,$CardNumber){
		$html ='<br />';
		$html .='<strong>'.'TrangellMellat'.'</strong>';
		$html .='<br />';
		if (isset($trackingCode)){
			$html .= '<br />';
			$html .= $trackingCode .'شماره پیگری ';
			$html .= '<br />';
			$html .= $CardNumber .' شماره کارت ';
			$html .= '<br />';
		}
		$html .='<br />' . $msg;
		$orderpayment->customer_note =$customer_note.$html;
		$payment_status = $this->getPaymentStatus($statCode); 
		$orderpayment->transaction_status = $payment_status;
		$orderpayment->order_state = $payment_status;
		$orderpayment->order_state_id = $this->params->get('payment_status', $statCode); 
		
		if ($orderpayment->store()) {
			if ($emptyCart == 'ok'){
				$orderpayment->payment_complete ();
				$orderpayment->empty_cart();
			}
		}
		else
		{
			$errors[] = $orderpayment->getError();
		}
	
 		$vars = new JObject();
		$vars->onafterpayment_text = $msg;
		$html = $this->_getLayout('postpayment', $vars);
		$html .= $this->_displayArticle();
		return $html;
	}

    function getGateMsg ($msgId) {
		switch($msgId){
			case '0': $out =  'تراکنش با موفقیت انجام شد'; break;
			case '11': $out =  'شماره کارت نامعتبر است'; break;
			case '12': $out =  'موجودی کافی نیست'; break;
			case '13': $out =  'رمز نادرست است'; break;
			case '14': $out =  'تعداد دفعات وارد کردن رمز بیش از حد مجاز است'; break;
			case '15': $out =  'کارت نامعتبر است'; break;
			case '16': $out =  'دفعات برداشت وجه بیش از حد مجاز است'; break;
			case '17': $out =  'کاربر از انجام تراکنش منصرف شده است'; break;
			case '18': $out =  'تاریخ انقضای کارت گذشته است'; break;
			case '19': $out =  'مبلغ برداشت وجه بیش از حد مجاز است'; break;
			case '21': $out =  'پذیرنده نامعتبر است'; break;
			case '23': $out =  'خطای امنیتی رخ داده است'; break;
			case '24': $out =  'اطلاعات کاربری پذیرنده نادرست است'; break;
			case '25': $out =  'مبلغ نامتعبر است'; break;
			case '31': $out =  'پاسخ نامتعبر است'; break;
			case '32': $out =  'فرمت اطلاعات وارد شده صحیح نمی باشد'; break;
			case '33': $out =  'حساب نامعتبر است'; break;
			case '34': $out =  'خطای سیستمی'; break;
			case '35': $out =  'تاریخ نامعتبر است'; break;
			case '41': $out =  'شماره درخواست تکراری است'; break;
			case '42': $out =  'تراکنش Sale‌ یافت نشد'; break;
			case '43': $out =  'قبلا درخواست Verify‌ داده شده است'; break;
			case '44': $out =  'درخواست Verify‌ یافت نشد'; break;
			case '45': $out =  'تراکنش Settle‌ شده است'; break;
			case '46': $out =  'تراکنش Settle‌ نشده است'; break;
			case '47': $out =  'تراکنش  Settle یافت نشد'; break;
			case '48': $out =  'تراکنش Reverse شده است'; break;
			case '49': $out =  'تراکنش Refund یافت نشد'; break;
			case '51': $out =  'تراکنش تکراری است'; break;
			case '54': $out =  'تراکنش مرجع موجود نیست'; break;
			case '55': $out =  'تراکنش نامعتبر است'; break;
			case '61': $out =  'خطا در واریز'; break;
			case '111': $out =  'صادر کننده کارت نامعتبر است'; break;
			case '112': $out =  'خطا سوییج صادر کننده کارت'; break;
			case '113': $out =  'پاسخی از صادر کننده کارت دریافت نشد'; break;
			case '114': $out =  'دارنده کارت مجاز به انجام این تراکنش نیست'; break;
			case '412': $out =  'شناسه قبض نادرست است'; break;
			case '413': $out =  'شناسه پرداخت نادرست است'; break;
			case '414': $out =  'سازمان صادر کننده قبض نادرست است'; break;
			case '415': $out =  'زمان جلسه کاری به پایان رسیده است'; break;
			case '416': $out =  'خطا در ثبت اطلاعات'; break;
			case '417': $out =  'شناسه پرداخت کننده نامعتبر است'; break;
			case '418': $out =  'اشکال در تعریف اطلاعات مشتری'; break;
			case '419': $out =  'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است'; break;
			case '421': $out =  'IP‌ نامعتبر است';  break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
			case 'notff': $out = 'سفارش پیدا نشد';break;
			default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}
	function getShippingAddress() {

		$user =	JFactory::getUser();
		$db = JFactory::getDBO();

		$query = "SELECT * FROM #__j2store_addresses WHERE user_id={$user->id}";
		$db->setQuery($query);
		return $db->loadObject();

	 }

}
