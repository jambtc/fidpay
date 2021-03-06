<?php

use yii\helpers\Url;
use yii\web\View;

use app\components\Settings;

$blockchain = Settings::poa();

$options = [

    'baseUrl' => Yii::$app->request->baseUrl,
    'language' => Yii::$app->language,
    'sendURL' => Url::to(['/send/generate-transaction']),
    'gasLimitUrl' => Url::to(['/send/gas-limit']),
    'poaDecimals' => $blockchain->smartContract->decimals,
    'invalidAmountError' => Yii::t('app', 'Invalid amount!'),
    'decimalError' => Yii::t('app','Use a maximum of {count} decimal places.',[
        'count' => $blockchain->smartContract->decimals,
    ]),
    'higherError' => Yii::t('app','Amount is higher than Balance.'),
    'nogasError' => Yii::t('app','You have no gas to generate transaction.'),
    'enoughgasError' => Yii::t('app','You have no enough gas to generate transaction.'),
    'recipientError' => Yii::t('app','Recipient address not entered.'),
    'htmlTransactionBody' => '<div class="alert alert-warning">
                                <p class="generating">'.Yii::t('app','Generating transaction...').'</p>
                                <div class="button-spinner spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
                                </div>',
    'spinner' => '<div class="button-spinner spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
    'zerogas' => $blockchain->blockchain->zerogas,
    //'textClose' => Yii::t('app','Close'),
    // ...
];
$this->registerJs(
    "var yiiOptions = ".\yii\helpers\Json::htmlEncode($options).";",
    View::POS_HEAD,
    'yiiOptions'
);



$wallet_send = <<<JS

    var countDecimals = function(value) {
        // console.log('[countDecimals]',Math.floor(value),value);
        if (Math.floor(value) != value)
            return value.toString().split(".")[1].length || 0;
        return 0;
    }

    var sendForm = document.querySelector('#send-form');
    var stepButton = document.querySelector('#getCheckedButton1');
    var submitButton = document.querySelector('.pay-submit');

    stepButton.addEventListener('click', function(event){
        $('.amount-to-send').text($('#sendform-amount').val());
        $('#error-summary').hide().text('');

        if ($("#sendform-amount").val() <= 0 ){
            $('#error-summary').show().text(yiiOptions.invalidAmountError);
            event.stopPropagation();
		}

        if (countDecimals($("#sendform-amount").val()) > yiiOptions.poaDecimals){
			$('#error-summary').show().text(yiiOptions.decimalError);
			event.stopPropagation();
		}

		if (eval($("#sendform-amount").val()) > eval($("#sendform-balance").val())){
            $('#error-summary').show().text(yiiOptions.higherError);
            event.stopPropagation();
		}

		if ($("#sendform-to").val() == ''){
            $('#error-summary').show().text(yiiOptions.recipientError);
            event.stopPropagation();
		}

        if (yiiOptions.zerogas != 1){
            if ($("#sendform-balance_gas").val() <= 0  ){
                $('#error-summary').show().text(yiiOptions.nogasError);
                event.stopPropagation();
            }

            if (gasLimit() == false){
                $('#error-summary').show().text(yiiOptions.enoughgasError);
                event.stopPropagation();
            }
        } else {
            var outcome = gasLimit();
        }

	});


    gasLimit = function () {
        $.ajax({
            url	: yiiOptions.gasLimitUrl, // gasLimitUrl  url,
            type: "POST",
            data: {
                'to' : $("#sendform-to").val(),
                'from' : $("#sendform-from").val(),
                'amount' : $("#sendform-amount").val(),
            },
            dataType: "json",
            beforeSend: function(){
                $('#amount-to-send-gas').html(yiiOptions.spinner);
            },
            success:function(data){
                console.log('[gaslimit]: data from send/gaslimit controller',data);
                if (data.success){
                    $('#amount-to-send-gas').text(data.gasLimit);
                    $('.js-confirm-submit').removeClass('disabled')
                } else {
                    return false;
                }
                return true;
            },
            error: function(j){
                console.log(j);
            }
        });
    }

    submitButton.addEventListener('click', function(event){
        event.preventDefault();
		event.stopPropagation();
        console.log('[Send Confirm]: button pressed');

        if ($('.js-confirm-submit').hasClass('disabled')){
            return false;
        }

        my_wallet = $('#sendform-from').val();

		var sendPost = {
			id		: new Date().toISOString(), // id of indexedDB
			from	: my_wallet,
			to		: $('#sendform-to').val(),
			amount	: $('#sendform-amount').val(),
			memo 	: $('#sendform-memo').val(),
			prv_key : null,
			prv_pas : null,
		};
		console.log('[Send]: sendPost senza chiave',sendPost);

		// USO IL SERVICE WORKER
        // if ('serviceWorker' in navigator && 'SyncManager' in window){
        if ('serviceWorker' in navigator){
			navigator.serviceWorker.ready
			.then(function(sw) {
				var serWork = sw; // firefox fix
				//leggo la priv_key dallo storage
				var prv_key = null;
				readAllData('wallet').then(function(data) {
                    if (typeof data[0] !== 'undefined') {
						sendPost.prv_key = data[0].prv_php;
						sendPost.prv_pas = data[0].prv_pas;
						console.log('[Send]: sendPost con chiave',sendPost);

                        $.ajax({
            				url	: yiiOptions.sendURL, // ERC20 send url,
            				type: "POST",
            				data: sendPost,
            				dataType: "json",
                            beforeSend: function() {
                                $('.hide-content').hide();
                                $('.transaction-details').show();
        						$('.transaction-details').html(yiiOptions.htmlTransactionBody);
        					},
            				success:function(data){
            					console.log('[send]: data from generate-transaction controller',data);
                                $('.generating').parent().removeClass('alert alert-warning');
                                $('.button-spinner').remove();
                                $('.generating').html(data.row);
                                $('.pay-close').show();
                                console.log('[Send]: loaded gas is: ', data.gas.balance);

                                writeData('sync-send-erc20', data).then(function() {
        							console.log('[Send]: Registered sync-send-erc20 request in indexedDB', data);
                                    if ('SyncManager' in window){
                                        return serWork.sync.register('sync-send-erc20');
                                    } else {
                                        erc20.bypassIos('sync-send-erc20');
                                        return true;
                                    }
        						})
                                .then(function() {
        							erc20.isReadySent(data.id);
        						})

            				},
            				error: function(j){
            					console.log(j);
            				}
            			});
					} else {
						console.log('Chiave privata non trovata!');
						return;
					}
				})
			});
		} else {
            alert('Your browser don`t support service worker!');
        }
	});

    

JS;

$this->registerJs(
    $wallet_send,
    View::POS_READY, //POS_END
    'wallet_send'
);
