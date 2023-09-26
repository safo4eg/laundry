<b>Your active orders:</b>

@foreach($orders as $order)
    @if($order->status_id === 2)
        <b>Заказ {{$order->id}}</b> <i>Waiting for the courier</i>
    @elseif($order->status_id === 3)
        <b>Заказ {{$order->id}}</b> <i>Sent to courier</i>
    @elseif($order->status_id === 4)
        @switch($order->reason_id)
            @case(5)
                <b>Заказ {{$order->id}}</b> <i>Waiting to be filled</i>
                @break
        @endswitch
    @endif
@endforeach
