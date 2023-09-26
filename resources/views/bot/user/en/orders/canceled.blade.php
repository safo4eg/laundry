<b>Your canceled orders</b>

@foreach($orders as $order)
    <b>Order {{$order->id}}</b> <i>Canceled</i>
@endforeach
