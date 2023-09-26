<b>All your orders</b>

@foreach($orders as $order)
    @if($order->status_id === 2)
        <b>Order {{$order->id}}</b> <i>Waiting for the courier</i>
    @elseif($order->status_id === 3)
        <b>Order {{$order->id}}</b> <i>Sent to courier</i>
    @elseif($order->status_id === 4)
        @switch($order->reason_id)
            @case(1)
            @case(2)
            @case(3)
            @case(4)
                <b>Order {{$order->id}}</b> <i>Canceled</i>
                @break
            @case(5)
                <b>Order {{$order->id}}</b> <i>Waiting to be filled</i>
                @break
        @endswitch
    @endif
@endforeach
