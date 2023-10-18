<b>Все Ваши заказы:</b>

@foreach($orders as $order)
    @if($order->status_id === 2)
        <b>Заказ {{$order->id}}</b> <i>Ожидает назначения курьера</i>
    @elseif($order->status_id === 3)
        <b>Заказ {{$order->id}}</b> <i>Отправлен курьеру</i>
    @elseif($order->status_id === 4)
        @switch($order->reason_id)
            @case(1)
            @case(2)
            @case(3)
            @case(4)
                <b>Заказ {{$order->id}}</b> <i>Отменен</i>
                @break
            @case(5)
                <b>Заказ {{$order->id}}</b> <i>Ожидает заполнения</i>
                @break
        @endswitch
    @elseif($order->status_id === 5)
        <b>Заказ {{$order->id}}</b> <i>Курьер забрал вещи</i>
    @endif
@endforeach
