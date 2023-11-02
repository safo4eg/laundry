Your completed orders:

@if($orders->isNotEmpty())
    @foreach($orders as $order)
        Order <b>#{{$order->id}}</b>
        Price: <b>{{$order->price}} IDR</b>
        Paid with bonuses: <b>{{isset($order->bonuses)? $order->bonuses: 0}} IDR</b>
        Rating: <b>{{isset($order->rating)? $order->rating: 'No rating'}}</b>
        -----------------------------------------------

    @endforeach
@else
    You have no completed orders yet.
@endif
