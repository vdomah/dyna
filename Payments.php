<?php namespace Vdomah\Shoptober\Controllers;

use App;
use Payum\LaravelPackage\Controller\PayumController;
//use Payum\Core\Model\Payout;
use Vdomah\OsSeller\Classes\Payout;
use Payum\Core\Storage\FilesystemStorage;
use Payum\LaravelPackage\Storage\EloquentStorage;
use Payum\LaravelPackage\Model\Token;
use Vdomah\Shoptober\Models\Payment;
use Illuminate\Http\Request;

class Payments extends PayumController {

    public $request;

    public $gatewayName = 'paypal_masspay';

    public function __construct(Request $request)
    {
        $this->request = $request;

        App::resolving('payum.builder', function(\Payum\Core\PayumBuilder $payumBuilder) {
            $payumBuilder
                // this method registers filesystem storages, consider to change them to something more
                // sophisticated, like eloquent storage
                ->addDefaultStorages()
                ->addStorage(Payout::class, new FilesystemStorage(sys_get_temp_dir(), Payout::class, 'id'))

                ->setGenericTokenFactoryPaths(['capture' => 'payoutCapture'])

                ->addGateway($this->gatewayName, [
                    'factory' => $this->gatewayName,
                    'username'  => 'firebatt-facilitator_api1.ukr.net',
                    'password'  => 'MEPBYHN5F9VC6R6R',
                    'signature' => 'AZdIyd0DhJfaDurGQLKwe.TiHq0YA40CNHN.iTQNsvrqWpJo0-FQ-dvs',
                    'sandbox' => true
                ]);
        });

        App::resolving('payum.builder', function(\Payum\Core\PayumBuilder $payumBuilder) {
            $payumBuilder
                ->setTokenStorage(new EloquentStorage(Token::class))
            ;
        });
    }
    
    public function index()
    {
        $storage = $this->getPayum()->getStorage(Payout::class);
        /** @var \Payum\LaravelPackage\Model\Payment $payment */
        $payment = $storage->create();
        $payment->setCurrencyCode('EUR');
        $payment->setTotalAmount(123);
        $storage->update($payment);
        $captureToken = $this->getPayum()->getTokenFactory()->createCaptureToken($this->gatewayName, $payment, 'payoutDone');

        return \Redirect::to($captureToken->getTargetUrl());
    }

    public function payoutCapture()
    {
        $token = $this->getPayum()->getHttpRequestVerifier()->verify($this->request);
        $gateway = $this->getPayum()->getGateway($token->getGatewayName());

        try {
            $gateway->execute(new Payout($token));
            dd($gateway);
            if (false == isset($_REQUEST['noinvalidate'])) {
                $this->getPayum()->getHttpRequestVerifier()->invalidate($token);
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
    }

    public function payoutDone()
    {
        dd($_REQUEST);
    }
}