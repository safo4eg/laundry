<b>All your orders</b>

@foreach($orders as $order)
    <b>Order {{$order->id}}</b> <i>{{ $order->status->en_desc }}</i>
@endforeach
