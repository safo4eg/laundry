🎉Ваши вещи в чистоте и порядке, курьер уже выдвигается к вам.
Для связи с курьером используйте кнопку "Связаться с курьером".

<b>Общий вес постиранных вещей:</b>
@foreach($order_services as $order_service)
    {{$order_service->service->title}} <b>{{$order_service->amount}}</b> - <b>{{$order_service->amount*$order_service->service->price}}</b> рупий
@endforeach
<b>Сумма заказа {{$price}}</b>

Как вам удобнее будет произвести оплату?

‼️Курьер оставит вещи только после оплаты.

