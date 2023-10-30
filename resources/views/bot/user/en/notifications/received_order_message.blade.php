<b>Order #{{$order->id}}:</b> You have received a message from the courier!

<b>The courier reports:</b> {{$order_message->text}} {{$order_message->created_at}}

@if($order->payment->status_id === 1)
    @if($order->payment->method_id !== 1)
        <b>Заказ не оплачен</b> Для оплаты нажмите "Оплатить заказ"
    @else
        <b>Выбранны способ оплаты: </b> Наличными курьеру.
        Для изменения способа оплаты нажмите "Изменить способ оплаты".
    @endif
    ‼️Курьер оставит вещи только после оплаты.
@endif
