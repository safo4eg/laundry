🎉Ваши вещи в чистоте и порядке, курьер уже выдвигается к вам.
Для связи с курьером используйте кнопку "Связаться с курьером".

<b>Общий вес постиранных вещей:</b>
@foreach($services_info['services'] as $service_info)
    {{$service_info['title']}} <b>{{$service_info['amount']}}</b> - <b>{{$service_info['price']}}</b> рупий
@endforeach
<b>Сумма заказа {{$services_info['total_price']}}</b>

Как вам удобнее будет произвести оплату?

‼️Курьер оставит вещи только после оплаты.

