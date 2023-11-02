Order <b>#{{($order_services->first())->order->id}}</b>

🎉Your things are clean and orderly, the courier is already on its way to you.
To contact the courier, use the "Contact the courier" button.

<b>Total weight of washed items:</b>

@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>{{$order_service->amount*$order_service->service->price}}</b> рупий
@endforeach

<b>Order price: {{$price}}</b>

@if(!is_null($payment['method_id']))
    @if($payment['status_id'] !== 3)
        Selected payment method: <b>{{$payment['desc']}}</b>
        @if($payment['method_id'] === 1)
            💸Transfer this amount to our courier or leave it to the staff at the reception. The courier always has change with him.

            To change the payment method, click the <b>"Change payment method"</b> button.
        @elseif($payment['method_id'] === 2 OR $payment['method_id'] === 3)
            @if($payment['status_id'] === 2)
                ✅ After successful confirmation of the payment, you will receive a notification.
            @elseif($payment['status_id'] === 1)
                @if($payment['method_id'] === 2)
                    💳 Here are the details for transferring to an Indonesian BRI bank card in rupees:

                    <b>4628 0100 4036 508</b>
                    <b>Anak Agung Gede Adi Semara</b>
                @elseif($payment['method_id'] === 3)
                    💳 Here are the data for transferring to a Tinkoff card in rubles:

                    <b>2200 7007 7932 1818</b>
                    <b>Olga G.</b>

                    <b>Amount to pay in rubles: {{$payment['ru_price']}}</b>
                @endif

                🧾 After you transfer, click the <b>"Send photo of payment"</b> button and send a screenshot of the transfer.

                ‼️The courier will leave the items only after payment.

                To change the payment method, click the <b>"Change payment method"</b> button.
            @endif
        @endif
    @else
        @if($payment['method_id'] ===  4)
            ✅Paid with bonuses
        @endif
    @endif
@else
    To select a payment method, click the <b>"Select payment method"</b> button.
@endif

