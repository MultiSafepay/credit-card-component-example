<?php
    const API_KEY = 'your-api-key';
    const URL_API_TOKEN = 'https://testapi.multisafepay.com/v1/connect/auth/api_token';
    const URL_ORDERS = 'https://testapi.multisafepay.com/v1/json/orders';

    function doCurlRequest($url, $http_body = null)
    {
        $ch = curl_init();

        $request_headers = [
            'Accept: application/json',
            'api_key:' . API_KEY,
        ];

        if ($http_body !== null) {
            $request_headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $http_body);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $request_headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 1,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    if (isset($_GET['apiToken'])) {
        $apiToken = doCurlRequest(URL_API_TOKEN);
        exit(json_encode($apiToken));
    }

    if (isset($_GET['placeOrder'])) {
        $payload = file_get_contents("php://input");
        $order = [
            "type" => "direct",
            "order_id" => "creditcard_order_" . time(),
            "gateway" => "CREDITCARD",
            "currency" => "EUR",
            "amount" => 10000,
            "description" => "Credit card Component Demo Transaction",
            "payment_options" => [
                "redirect_url" => "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?success",
            ],
            "payment_data" => [
                "payload" => $payload,
            ]
        ];

        $order = json_encode($order);
        $paymentUrl = doCurlRequest(URL_ORDERS, $order);
        exit(json_encode($paymentUrl));
    }

    if (isset($_GET['success'])) {
        exit('Succesfully placed an order');
    }
?>
<!doctype html>
<html lang="en">
<head>
    <style>
        .debug-container {
            min-height:300px;
            background:#efefef;
        }
        .debug-container pre{
            background:#222;
            color: #fff;
            min-height:150px;
            font-size:12px;
            padding:5px;
            max-height:300px;
            overflow: auto;
        }
    </style>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Payment Components | Credit card | MultiSafepay</title>
    <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="https://pay.multisafepay.com/sdk/components/v1/components.css">
</head>

<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <a class="navbar-brand" href="#">Credit card Component - example</a>
        </nav>
        <br>
        <div class="row">
            <div class="col-md-6">
                <div id="MSPPayment"></div>
                 <div class="text-center">
                    <button
                        type="button"
                        id="paymentButton"
                        class="btn btn-success btn-block btn-lg">
                        Pay by Credit Card
                    </button>
                </div>
           </div>
             <div class="col-md-6">
                <div class="debug-container">
                    <div class="col-md-12">
                        <span class="h5">Request</span>
                        <pre class="debug-request-container"></pre>
                        <span class="h5">Response</span>
                        <pre class="debug-response-container"></pre>
                        <span class="h5">Errors</span>
                        <pre class="debug-errors-container"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://pay.multisafepay.com/sdk/components/v1/components.js"></script>
    <script>
        const debugRequestContainer = document.querySelector('.debug-request-container');
        const debugResponseContainer = document.querySelector('.debug-response-container');
        const debugErrorContainer = document.querySelector('.debug-errors-container');
        const paymentButton = document.querySelector('#paymentButton');

        function debugShow(container, output) {
            container.innerText = JSON.stringify(output, null, 2);
        }

        const exampleOrder = {
            customer: {
                locale: 'en_US',
                country: 'NL',
                reference: 'XXX',
            },
            currency: 'EUR',
            amount: 10000,
        };

        const httpPost = (endpoint, data) =>
            fetch('creditcard_component.php?' + endpoint, {
                headers: {
                    'Accept': 'application/json, text/plain, */*',
                    'Content-Type': 'application/json'
                },
                method: 'POST',
                body: JSON.stringify(data)
            }).then(response => response.json());

        const getApiToken = () => {
            debugShow(debugRequestContainer, 'Step 1: GetApiToken')
            return httpPost('apiToken')
                .then(response => {
                    if (response.error) {
                        throw 'Invalid api token';
                    }
                    response = JSON.parse(response);
                    debugShow(debugResponseContainer, response);
                    return response.data.api_token;
                })
                .catch(console.error);
        };

        const placeOrder = (payload) => {
            debugRequestContainer.innerText = 'Step 3: Place order';
            return httpPost('placeOrder', payload)
                .then(response => {
                    response = JSON.parse(response);
                    debugShow(debugResponseContainer, response);
                    return response.data.payment_url;
                })
                .catch(console.error);
        };

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            getApiToken().then(apiToken => {

                const PaymentComponent = new MultiSafepay({
                    env : 'test',
                    apiToken : apiToken,
                    order : exampleOrder
                });

                PaymentComponent.init('payment', {
                    container: '#MSPPayment',
                    gateway: 'CREDITCARD',
                    onError: state => {
                        debugShow(debugErrorContainer, state);
                        console.log('onError', state);
                    }
                });

                paymentButton.addEventListener('click', e => {
                    if (PaymentComponent.hasErrors()) {
                        let errors = PaymentComponent.getErrors();
                        debugShow(debugErrorContainer, errors);
                        return false;
                    }
                    payload = PaymentComponent.getPaymentData().payment_data.payload;

                    placeOrder(payload)
                        .then(paymentUrl => {
                            window.location.href = paymentUrl;
                        });
                });
            });
        }, false);
    </script>
</body>

</html>
