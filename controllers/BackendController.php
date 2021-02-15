<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\web\HttpException;
use yii\filters\VerbFilter;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

use app\models\Notifications;
use app\models\NotificationsReaders;
use app\models\query\NotificationsReadersQuery;


use yii\helpers\Json;
use yii\helpers\Url;
use yii\helpers\Html;

Yii::$classMap['webapp'] = Yii::getAlias('@packages').'/webapp.php';



class BackendController extends Controller
{
	public function beforeAction($action)
	{
    	$this->enableCsrfValidation = false;
    	return parent::beforeAction($action);
	}

	/**
	 * {@inheritdoc}
	 */
	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::className(),
				'only' => [
					'notify',
				],
				'rules' => [
					// [
					// 	'allow' => true,
					// 	'actions' => ['saveSubscription'],
					// 	'roles' => ['?'],
					// ],
					[
						'allow' => true,
						'actions' => [
							'notify',
							'updateAllNews',
						],
						'roles' => ['@'],
					],
				],
			],
			];
	}

	/**
	 * {@inheritdoc}
	 */
	public function actions()
	{
		return [
			'error' => [
				'class' => 'yii\web\ErrorAction',
			],
			'captcha' => [
				'class' => 'yii\captcha\CaptchaAction',
				'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
			],
		];
	}

	// aggiorna tutte le notifiche in "letta"
	// update all rows
	public function actionUpdateAllNews(){
		$update = Yii::$app->db
			->createCommand()
			->update('np_notifications_readers', [
				'alreadyread' => 1
			])->execute();

		Yii::$app->response->format = Response::FORMAT_JSON;

		return ['success'=>true,'response'=>$update];
		// echo CJSON::encode(['success'=>true],true);
	}

	/**
	 * Saves the Subscription for push messages.
	 * @param POST VAPID KEYS
	 * this function NOT REQUIRE user to login
	 */
	public function actionNotify()
	{
		// echo "<pre>".print_r($_POST,true)."</pre>";
		// exit;

		$news = NotificationsReaders::find()
 	     		->andWhere(['id_user'=>Yii::$app->user->identity->id])
				->latest()
				// ->orderBy('id_notification DESC')
 	    		->all();

				// echo "<pre>".print_r($news,true)."</pre>";
				// exit;

	   $response['countedRead'] = 0;
	   $response['countedUnread'] = 0;
	   $response['htmlTitle'] = '';
	   $response['htmlContent'] = ''; // ex content
	   $response['playSound'] = false;
	   $response['playAlarm'] = false;

	   foreach ($news as $key => $item) {
		   ($item->alreadyread == 0 ? $response['countedUnread'] ++ : $response['countedRead'] ++);
	   }

// $x=1;
// 	   foreach ($news as $key => $item){
// 	   	echo "<pre>".print_r($item,true)."</pre>";
// 		$notify = Notifications::find()
// 			->andWhere(['id_notification'=>$item->id_notification])
// 			->one();
// 		echo "<pre>".print_r($notify,true)."</pre>";
//
// 		$x++;
// 		if ($x>3)
// 			break;
// 		}
//
// 	   	exit;

	   $x=1;
	   foreach ($news as $key => $item) {
		   // echo "<pre>".print_r($item,true)."</pre>";
		   // exit;

		   if ($x == 1){

					   $response['htmlTitle'] .= '<li>
		   	 			 <div class="d-flex align-items-center justify-content-between">
		   	 				 <div class="d-flex align-items-center">
		   	 				   <div class="coin-name notify-htmlTitle">'
							   . Html::encode(\Yii::t('lang',
								   'You have {n,plural,=0{read all messages.} =1{one unread message.} other{# unread messages.}}', ['n' => $response['countedUnread']]
							   ))
							   .'</div>
		   	 				 </div>
		   	 				 <div class="notify-readAll">
		   	 				   <a href="#"><small class="text-muted d-block">'. Yii::t('lang','Mark all as read') .'</small></a>
		   	 				 </div>
		   	 			   </div>
		   	 		 </li>';
		   }
		   // Leggo la notifica tramite key
		   $notify = Notifications::find()
 		  			->andWhere(['id_notification'=>$item->id_notification])
 		  			->one();

		   //$notify = Notifications::model()->findByPk($item->id_notification);
		   $notifi__icon = \webapp::Icon($notify->type_notification);
		   $notifi__color = \webapp::Color($notify->status);

		   // verifico che sia un allarme
		   if ($notify->type_notification == 'alarm' && $item->alreadyread == 0)
			   $response['playAlarm'] = true;




			$response['htmlContent'] .= '<li>
			<a href="'.htmlentities($notify->url).'" id="news_'.$notify->id_notification.'">
	   			<div class="d-flex align-items-center justify-content-between">
	                   <div class="d-flex align-items-center">
	                       <div class="notice-icon available">
	                           <i class="'.$notifi__icon.'"></i>
	                       </div>
	                       <div class="ml-10">
	                         <p class="coin-name">'.Yii::t('lang',$notify->description).'</p>

							 <div class="text-right">';
							 // se il tipo notifica è help o contact ovviamente non mostro il prezzo della transazione
							 if ($notify->type_notification <> 'help'
									 && $notify->type_notification <> 'contact'
									 && $notify->type_notification <> 'alarm'
							 ){
								 $response['htmlContent'] .= '<b class="d-block mb-0 float-left txt-dark">'.$notify->price.'</b>';
								 //VERIFICO QUESTE ULTIME 3 TRANSAZIONI PER AGGIORNARE IN REAL-TIME LO STATO (IN CASO CI SI TROVA SULLA PAGINA TRANSACTIONS)
								 $response['status'][$notify->id_tocheck] = $notify->status;
							 }
							 $response['htmlContent'] .= '
								 <small class="text-muted">'.Yii::$app->formatter->asRelativeTime($notify->timestamp).'</small>
							 </div>


	                       </div>
	                   </div>
	               </div>
			   </a>
	   		</li>';

		   // $response['htmlContent'] .= '
			//    <a href="'.htmlentities($notify->url).'" id="news_'.$notify->id_notification.'">
			// 	   <div class="notifi__item">
			// 		   <div class="'.$notifi__color.' img-cir img-40">
			// 			   <i class="'.$notifi__icon.'"></i>
			// 		   </div>
			// 		   <div class="content">
			// 			   <div onclick="backend.openEnvelope('.$notify->id_notification.');" >';
			// 				   if ($item->alreadyread == 0){
			// 					   $response['htmlContent'] .= '<p style="font-weight:bold;">';
			// 				   }else{
			// 					   $response['htmlContent'] .= '<p>';
			// 				   }
		   //
			// 				   $response['htmlContent'] .= Yii::t('lang',$notify->description);
			// 				    // $response['htmlContent'] .= WebApp::translateMsg($notify->description);
			// 				   $response['htmlContent'] .= '</p>';
		   //
			// 				   // se il tipo notifica è help o contact ovviamente non mostro il prezzo della transazione
			// 				   if ($notify->type_notification <> 'help'
			// 						   && $notify->type_notification <> 'contact'
			// 						   && $notify->type_notification <> 'alarm'
			// 				   ){
			// 					   $response['htmlContent'] .= '<p>'.$notify->price.'</p>';
			// 					   //VERIFICO QUESTE ULTIME 3 TRANSAZIONI PER AGGIORNARE IN REAL-TIME LO STATO (IN CASO CI SI TROVA SULLA PAGINA TRANSACTIONS)
			// 					   $response['status'][$notify->id_tocheck] = $notify->status;
			// 				   }
			// 				   // $time = new DateTime($notify->timestamp, new DateTimeZone('UTC'));
    		// 	   			   $response['htmlContent'] .= '
			// 				   <span class="date text-primary">'. date('d M Y - H:i:s',$notify->timestamp) .'</span>
			// 			   </div>
			// 		   </div>
			// 	   </div>
			//    </a>
		   // ';


		   $x++;
		   if ($x>5)
			   break;
	   }
	   if ($response['countedRead'] == 0 && $response['countedUnread'] == 0){
		   $response['htmlContent'] .= '<div class="notifi__title">';
		   $response['htmlContent'] .= '<p>' . Yii::t('lang','You have no messages to read.') . '</p>';
		   $response['htmlContent'] .= '</div>';
	   }else{
		   // $response['htmlContent'] .= '
			//    <div class="notifi__footer">
			// 	   <a id="seeAllMessages" onclick="backend.openAllEnvelopes();" href="'.htmlentities(Url::to(['messages/index'])).'">'.Yii::t('lang','See all messages').'</a>
			//    </div>
		   // ';
		   $response['htmlContent'] .= '<li>
   			<div class="d-flex align-items-center justify-content-between">
                   <div class="d-flex align-items-center">
                       <a href="'.htmlentities(Url::to(['messages/index'])).'" class="text-muted">'.Yii::t('lang','Manage notifications').'</a>
                   </div>
               </div>
   		</li>';
	   }

	   Yii::$app->response->format = Response::FORMAT_JSON;
	   return $response;

	}

	/**
	 * This function return the user wallet address
	 */
	 private function userAddress() {
 		$wallet = BoltWallets::find()
 	     		->andWhere(['id_user'=>Yii::$app->user->id])
 	    		->one();

		if (null === $wallet){
			$this->redirect(['wallet/wizard']);
		} else {
			return $wallet->wallet_address;
		}
	}













	private static function json ($data)
	{
		Yii::$app->response->format = Response::FORMAT_JSON;
		return $data;
	}







}