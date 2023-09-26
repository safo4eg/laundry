<b>Ваши отменённые заказы: </b>

@foreach($orders as $order)
    <b>Заказ {{$order->id}}</b> <i>Отменен</i>
@endforeach

Чтобы посмотреть подробную информацию о заказе введите:
/order <b> id</b>, например: /order <b>123</b>
