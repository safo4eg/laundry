🎉Your things are clean and orderly, the courier is already on its way to you.
To contact the courier, use the "Contact the courier" button.

<b>Total weight of washed items:</b>

@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>{{$order_service->amount*$order_service->service->price}}</b> IDR
@endforeach

<b>Order price: {{$price}}</b>

@if(isset($payment))
    Selected payment method: <b>{{$payment['desc']}}</b>

    @if($payment['id'] !== 4)
        @if($payment['id'] === 1)
            💸 Give this amount to our courier or leave it to the staff at the reception. The courier always has change with him.
        @elseif($payment['id'] === 2 OR $payment['id'] === 3)
            @if($payment['id'] === 2)
                💳 Here are the details for transferring to an Indonesian BRI bank card in rupees:

                <b>4628 0100 4036 508</b>
                <b>Anak Agung Gede Adi Semara</b>
            @elseif($payment['id'] === 3)
                💳 Here are the data for transferring to a Tinkoff card in rubles:

                <b>2200 7007 7932 1818</b>
                <b>Olga G.</b>

                <b>Amount to pay in rubles: {{$price_in_rubles}}</b>
            @endif

            🧾 After you translate, click the button <b>"Send photo of payment"</b> and send a screenshot of the translation.
        @endif

        To change the payment method, click the button <b>"Change payment method"</b>.

        ‼️ The courier will leave the items only after payment.
    @else
        ✅ Paid with bonuses.
    @endif
@else
    To select a payment method, click the button <b>"Select payment method"</b>.

    ‼️ The courier will leave the items only after payment.
@endif

