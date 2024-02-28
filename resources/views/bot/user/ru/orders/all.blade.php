<b>Все Ваши заказы:</b>

@foreach($orders as $order)
    <b>Заказ {{$order->id}}</b> <i>{{ $order->status->ru_desc }}</i>
@endforeach
