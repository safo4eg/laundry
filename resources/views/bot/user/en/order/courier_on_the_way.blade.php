Your things are clean and in order, the courier is already moving to you.
To contact the courier, use the "Contact the courier" button.

<b>Total weight of washed items:</b>

@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>@price($order_service->amount*$order_service->service->price)</b> rupees
@endforeach

<b>Order amount - @price($price)</b>

@if(isset($payment))
    Selected payment method: <b>{{$payment['desc']}}</b>

    @if($payment['id'] !== 4)
        @if($payment['id'] === 1)
            💸Transfer this amount to our courier or leave it to staff at the reception. The courier always has change with him.
        @elseif($payment['id'] === 2 OR $payment['id'] === 3)
            @if($payment['id'] === 2)
                💳 Here is the data for transfer to the card of the Indonesian bank BRI in rupees:

                <b>4628 0100 4036 508</b>
                <b>Anak Agung Gede Adi Semara</b>
            @elseif($payment['id'] === 3)
                💳 Here is the data for the transfer to the Tinkoff card in rubles:

                <b>2200 7007 7932 1818</b>
                <b>Olga G.</b>

                <b>Amount to be paid in rubles: {{$price_in_rubles}}</b>
            @endif

            🧾 After you translate, click <b>"Send a payment photo"</b> and send a screenshot of the transfer.
        @endif

        To change the payment method, click <b>"Change payment method"</b>.

        The courier will leave the items only after payment.
    @else
        ✅Paid with bonuses
    @endif
@else
    To select a payment method, click <b>"Select the payment method"</b>.

    The courier will leave the items only after payment.
@endif
