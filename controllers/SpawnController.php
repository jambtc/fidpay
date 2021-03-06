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


use app\models\WizardWalletForm;
use app\models\BoltWallets;

use yii\bootstrap4\ActiveForm;
use yii\helpers\Json;
use yii\helpers\Url;

class SpawnController extends Controller
{

	public function beforeAction($action)
	{
    $this->enableCsrfValidation = false;

		// $session = Yii::$app->session;
		// $token = $session->get('token-spawn');
		// if ($token === null || $token != $_GET['token']) {
		// 	Yii::$app->response->statusCode = 403;
		// 	return false;
		// }
		// $session->remove('token-spawn');

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
					'index',
				],
				'rules' => [
					[
						'allow' => true,
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








	/**
	 * Show new wallet page
	 */
	public function actionIndex()
 	{

		// echo '<pre>'.print_r($_POST,true).'</pre>';
		// exit;
		$this->layout = 'wizard';

		$formModel = new WizardWalletForm; //form di input dei dati

		if (Yii::$app->request->isAjax && $formModel->load(Yii::$app->request->post())) {
		    Yii::$app->response->format = Response::FORMAT_JSON;
			// echo '<pre>'.print_r(ActiveForm::validate($sendTokenForm),true).'</pre>';
		    return ActiveForm::validate($formModel);
		}

		if ($formModel->load(Yii::$app->request->post()) && $formModel->validate()) {
			// se sono giunto qui, l'indirizzo dell'utente non doveva essere in tabella
			// oppure non corrisponde a quello salvato in indexedDB
			$boltWallet = BoltWallets::find()->where( [ 'id_user' => Yii::$app->user->id ] )->one();
			if(null === $boltWallet) {
			  //doesn't exist so create record
				$boltWallet = new BoltWallets;
				$boltWallet->id_user = Yii::$app->user->id;
				$block = Yii::$app->Erc20->getBlockInfo();
				$boltWallet->blocknumber = $block->number;
			}
			$boltWallet->wallet_address = Yii::$app->request->post('WizardWalletForm')['address'];

			if ($boltWallet->save())
        		return $this->redirect(['/wallet/index']);
			else
				var_dump( $boltWallet->getErrors());

			exit;
    	}

 		return $this->render('index', [
			'formModel' => $formModel,
		]);
 	}

	/**
	 * Show new wallet page
	 */
	public function actionConfirm()
 	{
		$this->layout = 'wizard';

		$formModel = new WizardWalletForm; //form di input dei dati

		if (Yii::$app->request->isAjax && $formModel->load(Yii::$app->request->post())) {
		    Yii::$app->response->format = Response::FORMAT_JSON;
			// echo '<pre>'.print_r(ActiveForm::validate($sendTokenForm),true).'</pre>';
		    return ActiveForm::validate($formModel);
		}

		if ($formModel->load(Yii::$app->request->post()) && $formModel->validate()) {
			// se sono giunto qui, l'indirizzo dell'utente non doveva essere in tabella
			// oppure non corrisponde a quello salvato in indexedDB
			$boltWallet = BoltWallets::find()->where( [ 'id_user' => Yii::$app->user->id ] )->one();
			if(null === $boltWallet) {
			  //doesn't exist so create record
				$boltWallet = new BoltWallets;
				$boltWallet->id_user = Yii::$app->user->id;
				$block = Yii::$app->Erc20->getBlockInfo();
				$boltWallet->blocknumber = $block->number;
			}
			$boltWallet->wallet_address = Yii::$app->request->post('WizardWalletForm')['address'];

			if ($boltWallet->save())
        		return $this->redirect(['/wallet/index']);
			else
				var_dump( $boltWallet->getErrors());

			exit;
    	}

 		return $this->render('confirm', [
			'formModel' => $formModel,
		]);
 	}




}