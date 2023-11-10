Ваши завершенные заказы:

@if($orders->isNotEmpty())
    @foreach($orders as $order)
        Заказ <b>#{{$order->id}}</b>
        Цена: <b>@price($order->price) IDR</b>
        Оплачено бонусами: <b>{{isset($order->bonuses)? $order->bonuses: 0}} IDR</b>
        Рейтинг: <b>{{isset($order->rating)? $order->rating: 'Не указан'}}</b>
        -----------------------------------------------

    @endforeach
@else
    У вас пока нет завершенных заказов.
@endif
