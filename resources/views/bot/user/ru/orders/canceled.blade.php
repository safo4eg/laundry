<b>Ваши отменённые заказы: </b>

@foreach($orders as $order)
    <b>Заказ {{$order->id}}</b> <i>Отменен</i>
@endforeach
