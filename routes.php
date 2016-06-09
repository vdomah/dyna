<?php
use Payum\Core\PayumBuilder;
use Payum\Core\Payum;
use Payum\Core\Model\Payout;
use Payum\Core\Storage\FilesystemStorage;
use Payum\Core\Request\Capture;
use Payum\Core\Reply\HttpRedirect;

Route::get('/adaptive', function() {
    $builder = new PayumBuilder();
    $builder->setGenericTokenFactoryPaths(['payout' => 'payout']);
    $payum = $builder
        ->addDefaultStorages()
        ->addStorage(Payout::class, new FilesystemStorage(sys_get_temp_dir(), Payout::class))

        ->addGateway('aGateway', [
            'factory' => 'paypal_masspay',
            'username'  => 'firebatt-facilitator_api1.ukr.net',
            'password'  => 'MEPBYHN5F9VC6R6R',
            'signature' => 'AZdIyd0DhJfaDurGQLKwe.TiHq0YA40CNHN.iTQNsvrqWpJo0-FQ-dvs',
            'sandbox'   => true,
        ])

        ->getPayum()
    ;

    $gatewayName = 'aGateway';

    $storage = $payum->getStorage(Payout::class);

    $payout = $storage->create();
    $payout->setCurrencyCode('USD');
    $payout->setRecipientEmail('firebatt-buyer@ukr.net');
    $payout->setTotalAmount(100); // 1$
    $storage->update($payout);

    $payoutToken = $payum->getTokenFactory()->createPayoutToken($gatewayName, $payout, 'done.php');
    //dd($payoutToken);
    header("Location: ".$payoutToken->getTargetUrl());
});

Route::get('/adaptive/payout', function() {
    $builder = new PayumBuilder();
    $builder->setGenericTokenFactoryPaths(['payout' => 'payout']);
    $payum = $builder
        ->addDefaultStorages()
        ->addStorage(Payout::class, new FilesystemStorage(sys_get_temp_dir(), Payout::class))

        ->addGateway('aGateway', [
            'factory' => 'paypal_masspay',
            'username'  => 'firebatt-facilitator_api1.ukr.net',
            'password'  => 'MEPBYHN5F9VC6R6R',
            'signature' => 'AZdIyd0DhJfaDurGQLKwe.TiHq0YA40CNHN.iTQNsvrqWpJo0-FQ-dvs',
            'sandbox'   => true,
        ])

        ->getPayum()
    ;

    $token = $payum->getHttpRequestVerifier()->verify($_REQUEST);
    $gateway = $payum->getGateway($token->getGatewayName());

    try {
        $gateway->execute(new Payout($token));

        if (false == isset($_REQUEST['noinvalidate'])) {
            $payum->getHttpRequestVerifier()->invalidate($token);
        }

        header("Location: ".$token->getAfterUrl());
    } catch (HttpResponse $reply) {
        foreach ($reply->getHeaders() as $name => $value) {
            header("$name: $value");
        }

        http_response_code($reply->getStatusCode());
        echo ($reply->getContent());

        exit;
    } catch (ReplyInterface $reply) {
        throw new \LogicException('Unsupported reply', null, $reply);
    }
});
