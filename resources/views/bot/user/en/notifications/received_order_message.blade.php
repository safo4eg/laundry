<b>Order #{{$order->id}}:</b> You have received a message from the courier!

<b>The courier reports:</b> {{$order_message->text}} {{$order_message->created_at}}

@if(!$payment_status)
    <b>The order has not been paid!</b>To select a payment method, click “Pay for the order”

    ‼️The courier will leave the items only after payment.
@endif
